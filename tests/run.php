<?php

declare(strict_types=1);

use Dreamsmith\Campaign\Application;
use Dreamsmith\Campaign\Authentication\AuthenticationService;
use Dreamsmith\Campaign\Campaign\CampaignInput;
use Dreamsmith\Campaign\Campaign\CampaignMetrics;
use Dreamsmith\Campaign\Campaign\CampaignRepository;
use Dreamsmith\Campaign\Campaign\CampaignService;
use Dreamsmith\Campaign\Campaign\StaleCampaignVersion;
use Dreamsmith\Campaign\Application\Audit\AuditWriter;
use Dreamsmith\Campaign\Http\Request;
use Dreamsmith\Campaign\Http\Response;
use Dreamsmith\Campaign\Http\Router;
use Dreamsmith\Campaign\Persistence\Database;
use Dreamsmith\Campaign\Persistence\MigrationRunner;
use Dreamsmith\Campaign\Persistence\TransactionManager;
use Dreamsmith\Campaign\Presentation\Formatter;
use Dreamsmith\Campaign\Presentation\NavigationBuilder;
use Dreamsmith\Campaign\Records\CompanyInput;
use Dreamsmith\Campaign\Records\ContactInput;
use Dreamsmith\Campaign\Records\Normalizer;
use Dreamsmith\Campaign\Records\DuplicateWarning;
use Dreamsmith\Campaign\Records\RecordRepository;
use Dreamsmith\Campaign\Records\RecordService;
use Dreamsmith\Campaign\Records\StaleRecordVersion;
use Dreamsmith\Campaign\Prospect\ProspectInput;
use Dreamsmith\Campaign\Prospect\ProspectRules;
use Dreamsmith\Campaign\FollowUp\DueClassifier;
use Dreamsmith\Campaign\Reporting\ListQuery;
use Dreamsmith\Campaign\Integration\ScopeSet;
use Dreamsmith\Campaign\Integration\CapabilityCatalog;
use Dreamsmith\Campaign\Integration\CursorCodec;
use Dreamsmith\Campaign\Integration\Idempotency;
use Dreamsmith\Campaign\Application\Audit\ActorContext;
use Dreamsmith\Campaign\Support\FrozenClock;
use Dreamsmith\Campaign\Support\Logger;
use Dreamsmith\Campaign\Support\View;
use Dreamsmith\Campaign\LeadFinder\ProfileFieldUpload;

require dirname(__DIR__) . '/vendor/autoload.php';

$tests = [];
$test = static function (string $name, callable $body) use (&$tests): void {
    $tests[$name] = $body;
};
$assert = static function (bool $condition, string $message = 'Assertion failed'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$test('router supports base paths, parameters, and named URLs', static function () use ($assert): void {
    $router = new Router('/campaign');
    $router->add('GET', '/companies/{id}', static fn (Request $request, array $parameters): Response => Response::json($parameters), 'company.show');
    $response = $router->dispatch(new Request('GET', '/campaign/companies/a%20b', requestId: 'request-123'));
    $assert($response->status === 200);
    $assert(json_decode($response->body, true, flags: JSON_THROW_ON_ERROR)['id'] === 'a%20b');
    $assert($router->url('company.show', ['id' => 'a b']) === '/campaign/companies/a%20b');
});

$test('router returns 404 and 405 with Allow', static function () use ($assert): void {
    $router = new Router();
    $router->add('GET', '/known', static fn (): Response => Response::json([]), 'known');
    $assert($router->dispatch(new Request('GET', '/missing'))->status === 404);
    $response = $router->dispatch(new Request('POST', '/known'));
    $assert($response->status === 405 && $response->headers['Allow'] === 'GET');
});

$test('application maps exceptions without exposing details', static function () use ($assert): void {
    $router = new Router();
    $router->add('GET', '/fail', static function (): Response { throw new RuntimeException('sensitive detail'); }, 'fail');
    $log = sys_get_temp_dir() . '/dreamsmith-test-' . bin2hex(random_bytes(6)) . '.log';
    $response = (new Application($router, new Logger($log), false))->run(new Request('GET', '/fail', requestId: 'request-456'));
    $assert($response->status === 500);
    $assert(!str_contains($response->body, 'sensitive detail'));
    $assert($response->headers['X-Request-ID'] === 'request-456');
    @unlink($log);
});

$test('application applies operational security headers', static function () use ($assert): void {
    $router = new Router();
    $router->add('GET', '/health/live', static fn (): Response => Response::json(['status' => 'ok']), 'health.live');
    $log = sys_get_temp_dir() . '/dreamsmith-header-' . bin2hex(random_bytes(6)) . '.log';
    $response = (new Application($router, new Logger($log), false, null, ['X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'DENY']))->run(new Request('GET', '/health/live', requestId: 'request-headers'));
    $assert($response->headers['X-Content-Type-Options'] === 'nosniff' && $response->headers['X-Frame-Options'] === 'DENY');
    @unlink($log);
});

$test('logger redacts sensitive operational context', static function () use ($assert): void {
    $log = sys_get_temp_dir() . '/dreamsmith-log-' . bin2hex(random_bytes(6)) . '.log';
    (new Logger($log))->error('Test', ['password' => 'secret-value', 'body' => 'contact body', 'safe' => 'kept', 'exception' => new RuntimeException('sensitive exception')]);
    $content = (string) file_get_contents($log);
    $assert(!str_contains($content, 'secret-value') && !str_contains($content, 'contact body') && !str_contains($content, 'sensitive exception'));
    $assert(str_contains($content, '"safe":"kept"') && str_contains($content, 'RuntimeException'));
    @unlink($log);
});

$test('view provides escaping and rejects traversal', static function () use ($assert): void {
    $view = new View(dirname(__DIR__) . '/templates');
    $html = $view->render('errors/500.php', ['requestId' => '<unsafe>']);
    $assert(str_contains($html, '&lt;unsafe&gt;'));
    try {
        $view->render('../composer.json');
        throw new RuntimeException('Traversal was accepted.');
    } catch (RuntimeException $exception) {
        $assert($exception->getMessage() === 'Template not found.');
    }
});

$test('frozen clock is deterministic', static function () use ($assert): void {
    $instant = new DateTimeImmutable('2026-08-19T12:00:00Z');
    $clock = new FrozenClock($instant);
    $assert($clock->now() === $instant);
});

$test('authentication normalizes and validates owner email', static function () use ($assert): void {
    $assert(AuthenticationService::normalizeEmail('  Owner@Example.COM ') === 'owner@example.com');
    $assert(AuthenticationService::normalizeEmail('not-an-email') === null);
    $assert(AuthenticationService::normalizeEmail(str_repeat('a', 250) . '@x.test') === null);
});

$test('navigation keeps prospects and opportunities reachable when campaign setup is incomplete', static function () use ($assert): void {
    $router = new Router('/workspace');
    foreach ([
        'dashboard' => '/', 'work.index' => '/work', 'followups.index' => '/follow-ups', 'interactions.index' => '/interactions', 'prospects.index' => '/prospects', 'opportunities.index' => '/opportunities',
        'companies.index' => '/companies', 'contacts.index' => '/contacts', 'lead-finder.index' => '/lead-finder', 'lead-finder.profile' => '/lead-finder/profile', 'data.index' => '/data', 'campaign.settings' => '/settings/campaign',
        'integrations.index' => '/integrations',
    ] as $name => $path) {
        $router->add('GET', $path, static fn (): Response => Response::html(''), $name);
    }
    $withoutCampaign = (new NavigationBuilder($router))->build('dashboard', false);
    $assert($withoutCampaign[4]['url'] === '/workspace/prospects');
    $assert($withoutCampaign[5]['url'] === '/workspace/opportunities');
    $assert(count(array_filter($withoutCampaign, static fn (array $item): bool => $item['current'])) === 1);
    $withCampaign = (new NavigationBuilder($router))->build('prospects.index', true);
    $assert($withCampaign[4]['url'] === '/workspace/prospects' && $withCampaign[4]['current']);
});

$test('campaign metrics have canonical ordered defaults', static function () use ($assert): void {
    $assert(CampaignMetrics::defaults() === [
        'selected_prospects' => 175,
        'personalized_contacts' => 125,
        'partners_contacted' => 50,
        'network_contacts' => 40,
        'sales_conversations' => 16,
        'qualified_opportunities' => 6,
        'offers_sent' => 3,
        'contracts_won' => 1,
    ]);
    $assert(!array_key_exists('response_rate', CampaignMetrics::definitions()));
});

$test('campaign input rejects invalid dates and targets as one validation result', static function () use ($assert): void {
    [$valid, $errors] = CampaignInput::fromArray([
        'name' => 'Campaign', 'start_date' => '2026-09-30', 'end_date' => '2026-09-01',
        'targets' => array_replace(CampaignMetrics::defaults(), ['selected_prospects' => -1]),
    ]);
    $assert($valid === null);
    $assert(isset($errors['end_date'], $errors['target_selected_prospects']));

    [$valid, $errors] = CampaignInput::fromArray([
        'name' => ' Campaign ', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30',
        'targets' => CampaignMetrics::defaults(),
    ]);
    $assert($errors === [] && $valid?->name === 'Campaign');
});

$test('shared components escape records and format owner-facing values', static function () use ($assert): void {
    $view = new View(dirname(__DIR__) . '/templates');
    $html = $view->render('components/empty-state.php', [
        'heading' => '<script>alert(1)</script>',
        'message' => 'Nothing here',
        'actionUrl' => '/create?value=<unsafe>',
        'actionLabel' => 'Create',
    ]);
    $assert(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'));
    $formatter = new Formatter('America/Los_Angeles');
    $assert($formatter->money(123456) === '$1,234.56');
    $assert($formatter->date('2026-08-19 20:00:00', 'Y-m-d H:i') === '2026-08-19 13:00');
    $passwordField = $view->render('components/field.php', [
        'id' => 'password', 'name' => 'password', 'label' => 'Password', 'type' => 'password',
        'value' => 'must-not-render', 'required' => true,
    ]);
    $assert(!str_contains($passwordField, 'must-not-render'));
    $conflict = $view->render('components/conflict.php', [
        'message' => '<unsafe>', 'reloadUrl' => '/record/1',
    ]);
    $assert(str_contains($conflict, '&lt;unsafe&gt;') && str_contains($conflict, 'Review the current record'));
});

$test('company and contact inputs normalize identities without losing display values', static function () use ($assert): void {
    [$company, $errors] = CompanyInput::fromArray([
        'name' => "  Acme\n Labs  ", 'website' => 'https://www.Example.com/work', 'notes' => "Line one\nLine two",
    ]);
    $assert($errors === [] && $company?->name === 'Acme Labs');
    $assert($company?->websiteDomain === 'example.com' && $company?->notes === "Line one\nLine two");

    [$contact, $errors] = ContactInput::fromArray([
        'first_name' => '  Ada ', 'last_name' => ' Lovelace ', 'email' => 'Ada@Example.COM',
        'linkedin_url' => 'https://www.linkedin.com/in/ada',
    ]);
    $assert($errors === [] && $contact?->companyId === null);
    $assert($contact?->email === 'Ada@Example.COM' && $contact?->emailNormalized === 'ada@example.com');
    $assert($contact?->normalizedName === 'ada lovelace');
});

$test('record inputs reject unsafe URLs and incomplete people', static function () use ($assert): void {
    [$company, $errors] = CompanyInput::fromArray(['name' => 'Unsafe', 'website' => 'https://user:secret@example.com']);
    $assert($company === null && isset($errors['website']));
    [$contact, $errors] = ContactInput::fromArray(['linkedin_url' => 'https://example.com/not-linkedin']);
    $assert($contact === null && isset($errors['first_name'], $errors['linkedin_url']));
    $assert(Normalizer::text('   ') === null);
});

$test('prospect rules enforce direct research and qualification gates', static function () use ($assert): void {
    $sales = require dirname(__DIR__) . '/config/sales.php';
    $rules = new ProspectRules($sales);
    $assert($rules->unmet('direct','ready_to_contact',null,0,null,false,false,false,false) === ['why_them','signal']);
    $assert($rules->unmet('network','ready_to_contact',null,0,null,false,false,false,false) === []);
    $assert($rules->unmet('direct','qualified','Reason',1,'Problem',true,true,true,false) === ['budget_plausible']);
    try {$rules->assertTransition('researching','qualified',[]);throw new RuntimeException('Skipped transition accepted.');} catch (DomainException) {}
    $rules->assertTransition('researching','ready_to_contact',[]);
});

$test('prospect input requires a valid identity and canonical segment', static function () use ($assert): void {
    $sales = require dirname(__DIR__) . '/config/sales.php';
    [$input,$errors]=ProspectInput::fromArray(['segment'=>'unknown'],$sales);
    $assert($input===null&&isset($errors['identity'],$errors['segment']));
    [$input,$errors]=ProspectInput::fromArray(['primary_contact_id'=>'12','segment'=>'network','source'=>' Referral '],$sales);
    $assert($errors===[]&&$input?->companyId===null&&$input?->contactId===12&&$input?->source==='Referral');
});

$test('follow-up due classification uses owner-local calendar days', static function () use ($assert): void {
    $classifier = new DueClassifier(new DateTimeZone('America/Los_Angeles'));
    $now = new DateTimeImmutable('2026-08-19T16:00:00Z'); // 9am local
    $assert($classifier->classify(new DateTimeImmutable('2026-08-19T06:00:00Z'), $now) === 'overdue');
    $assert($classifier->classify(new DateTimeImmutable('2026-08-20T03:00:00Z'), $now) === 'today');
    $assert($classifier->classify(new DateTimeImmutable('2026-08-20T08:00:00Z'), $now) === 'upcoming');
});

$test('list queries bound text, pagination, sorts, and directions', static function () use ($assert): void {
    $query = ListQuery::from([
        'q' => '  needle  ', 'page' => '-9', 'sort' => 'unsafe SQL', 'direction' => 'sideways',
    ], ['name', 'updated'], 'name');
    $assert($query->text === 'needle' && $query->page === 1);
    $assert($query->sort === 'name' && $query->direction === 'desc');
    $valid = ListQuery::from(['page' => '4', 'sort' => 'updated', 'direction' => 'asc'], ['name', 'updated'], 'name');
    $assert($valid->offset() === 75 && $valid->sort === 'updated' && $valid->direction === 'asc');
});

$test('integration scopes are allowlisted and require every requested permission', static function () use ($assert): void {
    $scopes = ScopeSet::validated(['prospects:read', 'unknown:write', 'prospects:read', 'reports:read'], ['prospects:read' => 'Read', 'reports:read' => 'Reports']);
    $assert($scopes->all() === ['prospects:read', 'reports:read']);
    $assert($scopes->allowsAll(['prospects:read']) && !$scopes->allowsAll(['prospects:write']));
});

$test('capability catalog filters discovery and rejects under-scoped calls', static function () use ($assert): void {
    $catalog = new CapabilityCatalog(['prospect.search' => ['scopes' => ['prospects:read'], 'mutation' => false], 'prospect.create' => ['scopes' => ['prospects:write'], 'mutation' => true]]);
    $actor = new ActorContext('integration', effectiveScopes: ['prospects:read']);
    $assert($catalog->discover($actor) === ['prospect.search']);
    $catalog->authorize($actor, 'prospect.search');
    try { $catalog->authorize($actor, 'prospect.create'); throw new RuntimeException('Under-scoped capability was allowed.'); } catch (DomainException) {}
    $assert($catalog->requiresIdempotency('prospect.create'));
});

$test('cursor and idempotency helpers reject tampering and unstable keys', static function () use ($assert): void {
    $codec = new CursorCodec('test-signing-key');
    $cursor = $codec->encode(['client' => 1, 'expires_at' => time() + 60]);
    $assert($codec->decode($cursor)['client'] === 1);
    try { $codec->decode($cursor . 'x'); throw new RuntimeException('Tampered cursor was accepted.'); } catch (DomainException) {}
    $assert(Idempotency::validKey('0123456789abcdef') && !Idempotency::validKey('too short'));
    $assert(Idempotency::fingerprint('prospect.create', ['a' => 1, 'b' => 2]) === Idempotency::fingerprint('prospect.create', ['b' => 2, 'a' => 1]));
});

$testDsn = getenv('TEST_DB_DSN');
if (is_string($testDsn) && $testDsn !== '') {
    $test('database migration is repeatable and transactions roll back', static function () use ($assert, $testDsn): void {
        $database = new Database([
            'dsn' => $testDsn,
            'user' => getenv('TEST_DB_USER') ?: '',
            'password' => getenv('TEST_DB_PASSWORD') ?: '',
        ]);
        $pdo = $database->pdo();
        $runner = new MigrationRunner($pdo, dirname(__DIR__) . '/database/migrations');
        $runner->migrate();
        $assert($runner->migrate() === []);
        $before = (int) $pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();
        try {
            (new TransactionManager($pdo))->run(static function (PDO $pdo): void {
                $pdo->exec("INSERT INTO audit_events (actor_type, operation, correlation_id, metadata_json, occurred_at) VALUES ('owner', 'test', 'test-request', '{}', UTC_TIMESTAMP(6))");
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $exception) {
            $assert($exception->getMessage() === 'rollback');
        }
        $after = (int) $pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();
        $assert($before === $after, 'Transaction did not roll back.');
    });

    $test('campaign writes activate once and roll back every stale target update', static function () use ($assert, $testDsn): void {
        $database = new Database([
            'dsn' => $testDsn,
            'user' => getenv('TEST_DB_USER') ?: '',
            'password' => getenv('TEST_DB_PASSWORD') ?: '',
        ]);
        $pdo = $database->pdo();
        (new MigrationRunner($pdo, dirname(__DIR__) . '/database/migrations'))->migrate();
        $repository = new CampaignRepository($pdo);
        $originalSettings = $repository->settings();
        $service = new CampaignService($database, new AuditWriter(), new FrozenClock(new DateTimeImmutable('2026-08-19T12:00:00Z')));
        $input = new CampaignInput('Integration ' . bin2hex(random_bytes(4)), '2026-09-01', '2026-09-30', CampaignMetrics::defaults());
        $campaignId = $service->create($input, 1, 'test-campaign-create');
        try {
            $targets = $repository->targets($campaignId);
            $assert(count($targets) === count(CampaignMetrics::definitions()));
            $versions = array_map(static fn (array $target): int => (int) $target['version'], $targets);
            $versions['offers_sent'] = 0;
            $changedTargets = array_replace(CampaignMetrics::defaults(), ['selected_prospects' => 200]);
            try {
                $service->update($campaignId, new CampaignInput('Should roll back', '2026-09-01', '2026-09-30', $changedTargets), 1, $versions, 1, 'test-campaign-stale');
                throw new RuntimeException('Stale target update was accepted.');
            } catch (StaleCampaignVersion) {
            }
            $assert((int) $repository->find($campaignId)['version'] === 1);
            $assert((int) $repository->targets($campaignId)['selected_prospects']['target_value'] === 175);
            $service->activate($campaignId, (int) $originalSettings['version'], 1, 'test-campaign-activate');
            $assert((int) $repository->settings()['active_campaign_id'] === $campaignId);
        } finally {
            $restore = $pdo->prepare('UPDATE application_settings SET active_campaign_id = :active, owner_timezone = :timezone, version = :version WHERE id = 1');
            $restore->execute(['active' => $originalSettings['active_campaign_id'], 'timezone' => $originalSettings['owner_timezone'], 'version' => $originalSettings['version']]);
            $pdo->prepare('DELETE FROM campaign_targets WHERE campaign_id = :id')->execute(['id' => $campaignId]);
            $pdo->prepare('DELETE FROM campaigns WHERE id = :id')->execute(['id' => $campaignId]);
            $pdo->exec("DELETE FROM audit_events WHERE correlation_id LIKE 'test-campaign-%'");
        }
    });

    $test('company and contact persistence preserves independence, warnings, and archive dependencies', static function () use ($assert, $testDsn): void {
        $database = new Database(['dsn'=>$testDsn,'user'=>getenv('TEST_DB_USER')?:'','password'=>getenv('TEST_DB_PASSWORD')?:'']);
        $pdo = $database->pdo();
        (new MigrationRunner($pdo, dirname(__DIR__) . '/database/migrations'))->migrate();
        $service = new RecordService($database, new AuditWriter(), new FrozenClock(new DateTimeImmutable('2026-08-19T12:00:00Z')));
        [$company] = CompanyInput::fromArray(['name'=>'F05 Integration '.bin2hex(random_bytes(4)),'website'=>'https://integration.example.test']);
        $companyId = $service->createCompany($company, false, 1, 'test-record-company');
        $contactIds = [];
        try {
            [$linked] = ContactInput::fromArray(['company_id'=>$companyId,'first_name'=>'Linked','last_name'=>'Person','email'=>'Linked@example.test']);
            [$independent] = ContactInput::fromArray(['first_name'=>'Independent','last_name'=>'Person','email'=>'Independent@example.test']);
            $contactIds[] = $service->createContact($linked, false, 1, 'test-record-linked');
            $contactIds[] = $service->createContact($independent, false, 1, 'test-record-independent');
            $assert((new RecordRepository($pdo))->contact($contactIds[1])['company_id'] === null);
            try {
                $service->createContact($linked, false, 1, 'test-record-duplicate');
                throw new RuntimeException('Duplicate warning was not raised.');
            } catch (DuplicateWarning) {
            }
            try {
                $service->archiveCompany($companyId, 1, false, 1, 'test-record-archive-blocked');
                throw new RuntimeException('Company dependency confirmation was bypassed.');
            } catch (DomainException) {
            }
            $service->archiveCompany($companyId, 1, true, 1, 'test-record-archive');
            $assert((new RecordRepository($pdo))->company($companyId)['archived_at'] !== null);
            try {
                $service->restoreCompany($companyId, 1, true, 1, 'test-record-stale');
                throw new RuntimeException('Stale company version was accepted.');
            } catch (StaleRecordVersion) {
            }
        } finally {
            foreach ($contactIds as $contactId) $pdo->prepare('DELETE FROM contacts WHERE id=:id')->execute(['id'=>$contactId]);
            $pdo->prepare('DELETE FROM companies WHERE id=:id')->execute(['id'=>$companyId]);
            $pdo->exec("DELETE FROM audit_events WHERE correlation_id LIKE 'test-record-%'");
        }
    });
}

$test('lead profile uploads read plain text and spreadsheet-style CSV rows', static function () use ($assert): void {
    $path = tempnam(sys_get_temp_dir(), 'lead-profile-upload-'); if ($path === false) throw new RuntimeException('Could not create test file.');
    try {
        $upload = new ProfileFieldUpload();
        file_put_contents($path, "custom software\nmanual workflow\n");
        $assert($upload->text(['error'=>UPLOAD_ERR_OK,'tmp_name'=>$path,'size'=>filesize($path),'name'=>'traits.txt']) === "custom software\nmanual workflow");
        file_put_contents($path, "custom software,5\nmanual workflow,3\n");
        $assert($upload->text(['error'=>UPLOAD_ERR_OK,'tmp_name'=>$path,'size'=>filesize($path),'name'=>'weights.csv']) === "custom software | 5\nmanual workflow | 3");
    } finally { @unlink($path); }
});

$failed = 0;
foreach ($tests as $name => $body) {
    try {
        $body();
        echo "PASS {$name}\n";
    } catch (Throwable $exception) {
        $failed++;
        fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n");
    }
}
echo sprintf("%d passed, %d failed%s\n", count($tests) - $failed, $failed, $testDsn ? '' : ' (database integration skipped: TEST_DB_DSN not set)');
exit($failed === 0 ? 0 : 1);
