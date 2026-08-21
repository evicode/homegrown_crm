<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign;

use DateTimeZone;
use Dreamsmith\Campaign\Application\Audit\AuditWriter;
use Dreamsmith\Campaign\Authentication\AuthController;
use Dreamsmith\Campaign\Api\ApiController;
use Dreamsmith\Campaign\Authentication\AuthenticationService;
use Dreamsmith\Campaign\Campaign\CampaignController;
use Dreamsmith\Campaign\Campaign\CampaignService;
use Dreamsmith\Campaign\Data\DataController;
use Dreamsmith\Campaign\Data\DataService;
use Dreamsmith\Campaign\Http\Request;
use Dreamsmith\Campaign\Http\Response;
use Dreamsmith\Campaign\Http\Router;
use Dreamsmith\Campaign\Interaction\InteractionController;
use Dreamsmith\Campaign\Interaction\InteractionService;
use Dreamsmith\Campaign\Integration\IntegrationController;
use Dreamsmith\Campaign\Integration\IntegrationService;
use Dreamsmith\Campaign\Integration\BearerTokenGuard;
use Dreamsmith\Campaign\Integration\CapabilityCatalog;
use Dreamsmith\Campaign\Integration\LocalTokenAuthenticator;
use Dreamsmith\Campaign\Opportunity\OpportunityController;
use Dreamsmith\Campaign\Opportunity\OpportunityService;
use Dreamsmith\Campaign\FollowUp\FollowUpController;
use Dreamsmith\Campaign\FollowUp\FollowUpService;
use Dreamsmith\Campaign\Persistence\Database;
use Dreamsmith\Campaign\Presentation\FlashBag;
use Dreamsmith\Campaign\Presentation\Formatter;
use Dreamsmith\Campaign\Presentation\ShellController;
use Dreamsmith\Campaign\Presentation\ShellRenderer;
use Dreamsmith\Campaign\Prospect\ProspectController;
use Dreamsmith\Campaign\Prospect\ProspectRules;
use Dreamsmith\Campaign\Prospect\ProspectService;
use Dreamsmith\Campaign\Records\RecordController;
use Dreamsmith\Campaign\Records\RecordService;
use Dreamsmith\Campaign\Reporting\ReportingController;
use Dreamsmith\Campaign\Security\Csrf;
use Dreamsmith\Campaign\Security\SessionManager;
use Dreamsmith\Campaign\Support\Logger;
use Dreamsmith\Campaign\Support\SystemClock;
use Dreamsmith\Campaign\Support\View;

final class Bootstrap
{
    public static function create(string $root): Application
    {
        /** @var array<string, mixed> $app */
        $app = require $root . '/config/app.php';
        /** @var array<string, string> $databaseConfig */
        $databaseConfig = require $root . '/config/database.php';

        self::validate($app);
        $logger = new Logger($root . '/var/log/application.log');
        $database = new Database($databaseConfig);
        $router = new Router((string) $app['base_path']);
        $session = new SessionManager(
            (string) $app['session']['name'],
            (string) $app['base_path'],
            str_starts_with((string) $app['canonical_origin'], 'https://'),
        );
        $csrf = new Csrf($session);
        $flash = new FlashBag($session);
        $auth = new AuthenticationService(
            $database,
            $session,
            $csrf,
            new SystemClock(),
            (int) $app['session']['idle_seconds'],
            (int) $app['session']['absolute_seconds'],
            (int) $app['login_limit']['attempts'],
            (int) $app['login_limit']['window_seconds'],
            (string) $app['auth_fingerprint_key'],
            new AuditWriter(),
        );
        $view = new View($root . '/templates');
        $shell = new ShellRenderer(
            $view,
            $router,
            $database,
            $flash,
            $csrf,
            new Formatter((string) $app['owner_timezone']),
            ((string) $app['base_path']) . '/assets',
        );
        $controller = new AuthController($auth, $csrf, $shell, $flash, $router, $database);
        $shellController = new ShellController($auth, $shell, $router, $csrf);
        $campaignController = new CampaignController(
            $auth,
            $database,
            new CampaignService($database, new AuditWriter(), new SystemClock()),
            $shell,
            $flash,
            $csrf,
            $router,
        );
        $recordController = new RecordController(
            $auth,
            $database,
            new RecordService($database, new AuditWriter(), new SystemClock()),
            $shell,
            $flash,
            $csrf,
            $router,
        );
        /** @var array<string,mixed> $sales */
        $sales = require $root . '/config/sales.php';
        $prospectRules = new ProspectRules($sales);
        $prospectController = new ProspectController(
            $auth, $database, new ProspectService($database, new AuditWriter(), new SystemClock(), $prospectRules),
            $prospectRules, $sales, $shell, $flash, $csrf, $router,
        );
        $interactionController = new InteractionController($auth,$database,new InteractionService($database,new AuditWriter(),new SystemClock()),$sales,$csrf,$flash,$router,new SystemClock());
        $followUpController = new FollowUpController($auth,$database,new FollowUpService($database,new AuditWriter(),new SystemClock()),$shell,$flash,$csrf,$router,new SystemClock());
        $opportunityController = new OpportunityController($auth,$database,new OpportunityService($database,new AuditWriter(),new SystemClock(),$sales),$sales,$shell,$flash,$csrf,$router);
        $reportingController = new ReportingController($auth, $database, $shell, $router);
        $dataController = new DataController($auth, new DataService($database, $session, new RecordService($database, new AuditWriter(), new SystemClock()), new ProspectService($database, new AuditWriter(), new SystemClock(), $prospectRules), $sales, (string) $app['import_signing_key']), $shell, $flash, $csrf, $router);
        $integrations = require $root . '/config/integrations.php';
        $integrationController = new IntegrationController($auth, $database, new IntegrationService($database, new AuditWriter(), new SystemClock(), $integrations), $integrations, $shell, $flash, $csrf, $router);
        $apiController = new ApiController(
            $database,
            new BearerTokenGuard(new LocalTokenAuthenticator($database, $integrations)),
            new CapabilityCatalog((array) $integrations['capabilities']),
            new ProspectService($database, new AuditWriter(), new SystemClock(), $prospectRules),
            new InteractionService($database, new AuditWriter(), new SystemClock()),
            new FollowUpService($database, new AuditWriter(), new SystemClock()),
            new OpportunityService($database, new AuditWriter(), new SystemClock(), $sales),
            new RecordService($database, new AuditWriter(), new SystemClock()),
            $sales, $integrations, new SystemClock(),
        );

        $router->add('GET', '/api/openapi.json', static fn (Request $request): Response => $apiController->openApi($request), 'api.openapi');
        $router->add('GET', '/api/v1/capabilities', static fn (Request $request): Response => $apiController->capabilities($request), 'api.capabilities');
        $router->add('GET', '/api/v1/campaigns/active', static fn (Request $request): Response => $apiController->activeCampaign($request), 'api.campaign.active');
        $router->add('GET', '/api/v1/companies', static fn (Request $request): Response => $apiController->companies($request), 'api.companies.index');
        $router->add('POST', '/api/v1/companies', static fn (Request $request): Response => $apiController->createCompany($request), 'api.companies.create');
        $router->add('GET', '/api/v1/companies/{id}', static fn (Request $request, array $parameters): Response => $apiController->company($request, (int) $parameters['id']), 'api.companies.show');
        $router->add('PATCH', '/api/v1/companies/{id}', static fn (Request $request, array $parameters): Response => $apiController->updateCompany($request, (int) $parameters['id']), 'api.companies.update');
        $router->add('POST', '/api/v1/companies/{id}:archive', static fn (Request $request, array $parameters): Response => $apiController->archiveCompany($request, (int) $parameters['id']), 'api.companies.archive');
        $router->add('POST', '/api/v1/companies/{id}:restore', static fn (Request $request, array $parameters): Response => $apiController->archiveCompany($request, (int) $parameters['id'], true), 'api.companies.restore');
        $router->add('GET', '/api/v1/contacts', static fn (Request $request): Response => $apiController->contacts($request), 'api.contacts.index');
        $router->add('POST', '/api/v1/contacts', static fn (Request $request): Response => $apiController->createContact($request), 'api.contacts.create');
        $router->add('GET', '/api/v1/contacts/{id}', static fn (Request $request, array $parameters): Response => $apiController->contact($request, (int) $parameters['id']), 'api.contacts.show');
        $router->add('PATCH', '/api/v1/contacts/{id}', static fn (Request $request, array $parameters): Response => $apiController->updateContact($request, (int) $parameters['id']), 'api.contacts.update');
        $router->add('POST', '/api/v1/contacts/{id}:archive', static fn (Request $request, array $parameters): Response => $apiController->archiveContact($request, (int) $parameters['id']), 'api.contacts.archive');
        $router->add('POST', '/api/v1/contacts/{id}:restore', static fn (Request $request, array $parameters): Response => $apiController->archiveContact($request, (int) $parameters['id'], true), 'api.contacts.restore');
        $router->add('GET', '/api/v1/prospects', static fn (Request $request): Response => $apiController->prospects($request), 'api.prospects.index');
        $router->add('POST', '/api/v1/prospects', static fn (Request $request): Response => $apiController->createProspect($request), 'api.prospects.create');
        $router->add('GET', '/api/v1/prospects/{id}', static fn (Request $request, array $parameters): Response => $apiController->prospect($request, (int) $parameters['id']), 'api.prospects.show');
        $router->add('POST', '/api/v1/prospects/{id}/interactions', static fn (Request $request, array $parameters): Response => $apiController->recordInteraction($request, (int) $parameters['id']), 'api.interactions.create');
        $router->add('POST', '/api/v1/prospects/{id}/follow-ups', static fn (Request $request, array $parameters): Response => $apiController->scheduleFollowUp($request, (int) $parameters['id']), 'api.followups.create');
        $router->add('POST', '/api/v1/prospects/{id}/opportunities', static fn (Request $request, array $parameters): Response => $apiController->createOpportunity($request, (int) $parameters['id']), 'api.opportunities.create');

        $router->add('GET', '/health/live', static fn (Request $request, array $parameters): Response => Response::json([
            'status' => 'ok',
            'request_id' => $request->requestId,
        ]), 'health.live');

        $router->add('GET', '/health/ready', static function (Request $request, array $parameters) use ($database): Response {
            try {
                $settings = $database->pdo()->query('SELECT id FROM application_settings WHERE id = 1')->fetchColumn();
                $authenticationMigration = $database->pdo()->query(
                    "SELECT COUNT(*) FROM schema_migrations WHERE migration_id = '0008_opportunities'"
                )->fetchColumn();
                if ((int) $settings !== 1 || (int) $authenticationMigration !== 1) {
                    throw new \RuntimeException('Required schema is not present.');
                }
                return Response::json(['status' => 'ready', 'request_id' => $request->requestId]);
            } catch (\Throwable) {
                return Response::json(['status' => 'not_ready', 'request_id' => $request->requestId], 503);
            }
        }, 'health.ready');

        $router->add('GET', '/login', static fn (Request $request): Response => $controller->loginForm($request), 'login.form');
        $router->add('POST', '/login', static fn (Request $request): Response => $controller->login($request), 'login.submit');
        $router->add('POST', '/logout', static fn (Request $request): Response => $controller->logout($request), 'logout');
        $router->add('GET', '/', static fn (Request $request): Response => $controller->dashboard($request), 'dashboard');
        $router->add('GET', '/reports/campaign', static fn (Request $request): Response => $reportingController->campaign($request), 'reports.campaign');
        $router->add('GET', '/account/password', static fn (Request $request): Response => $controller->passwordForm($request), 'account.password');
        $router->add('POST', '/account/password', static fn (Request $request): Response => $controller->changePassword($request), 'account.password.update');
        $router->add('GET', '/opportunities', static fn (Request $request): Response => $opportunityController->index($request), 'opportunities.index');
        $router->add('GET', '/work', static fn (): Response => $followUpController->work(), 'work.index');
        $router->add('GET', '/data', static fn (): Response => Response::redirect($router->url('data.import')), 'data.index');
        $router->add('GET', '/data/import', static fn (): Response => $dataController->importForm(), 'data.import');
        $router->add('GET', '/data/import/template', static fn (): Response => $dataController->template(), 'data.import.template');
        $router->add('POST', '/data/import/preview', static fn (Request $request): Response => $dataController->preview($request), 'data.import.preview');
        $router->add('POST', '/data/import/commit', static fn (Request $request): Response => $dataController->commit($request), 'data.import.commit');
        $router->add('POST', '/data/import/cancel', static fn (Request $request): Response => $dataController->cancel($request), 'data.import.cancel');
        $router->add('GET', '/data/export', static fn (): Response => $dataController->exportForm(), 'data.export');
        $router->add('POST', '/data/export/{type}', static fn (Request $request, array $parameters): Response => $dataController->export($request, $parameters['type']), 'data.export.download');
        $router->add('GET', '/integrations', static fn (): Response => $integrationController->index(), 'integrations.index');
        $router->add('GET', '/integrations/new', static fn (): Response => $integrationController->form(), 'integrations.new');
        $router->add('POST', '/integrations', static fn (Request $request): Response => $integrationController->create($request), 'integrations.create');
        $router->add('POST', '/integrations/{id}/revoke', static fn (Request $request, array $parameters): Response => $integrationController->revoke($request, (int) $parameters['id']), 'integrations.revoke');
        $router->add('GET', '/campaigns', static fn (): Response => $campaignController->index(), 'campaigns.index');
        $router->add('GET', '/campaigns/new', static fn (): Response => $campaignController->createForm(), 'campaigns.new');
        $router->add('POST', '/campaigns', static fn (Request $request): Response => $campaignController->create($request), 'campaigns.create');
        $router->add('GET', '/campaigns/{id}/edit', static fn (Request $request, array $parameters): Response => $campaignController->editForm((int) $parameters['id']), 'campaigns.edit');
        $router->add('POST', '/campaigns/{id}/update', static fn (Request $request, array $parameters): Response => $campaignController->update($request, (int) $parameters['id']), 'campaigns.update');
        $router->add('POST', '/campaigns/{id}/activate', static fn (Request $request, array $parameters): Response => $campaignController->activate($request, (int) $parameters['id']), 'campaigns.activate');
        $router->add('GET', '/settings/campaign', static fn (): Response => $campaignController->shortcut(), 'campaign.settings');
        $router->add('POST', '/settings/timezone', static fn (Request $request): Response => $campaignController->timezone($request), 'settings.timezone');
        $router->add('GET', '/companies', static fn (Request $request): Response => $recordController->companies($request), 'companies.index');
        $router->add('GET', '/companies/new', static fn (): Response => $recordController->companyForm(), 'companies.new');
        $router->add('POST', '/companies', static fn (Request $request): Response => $recordController->saveCompany($request), 'companies.create');
        $router->add('GET', '/companies/{id}', static fn (Request $request, array $parameters): Response => $recordController->company((int) $parameters['id']), 'companies.show');
        $router->add('GET', '/companies/{id}/edit', static fn (Request $request, array $parameters): Response => $recordController->companyForm((int) $parameters['id']), 'companies.edit');
        $router->add('POST', '/companies/{id}/update', static fn (Request $request, array $parameters): Response => $recordController->saveCompany($request, (int) $parameters['id']), 'companies.update');
        $router->add('POST', '/companies/{id}/archive', static fn (Request $request, array $parameters): Response => $recordController->toggleCompany($request, (int) $parameters['id'], false), 'companies.archive');
        $router->add('POST', '/companies/{id}/restore', static fn (Request $request, array $parameters): Response => $recordController->toggleCompany($request, (int) $parameters['id'], true), 'companies.restore');
        $router->add('GET', '/contacts/new', static fn (Request $request): Response => $recordController->contactForm(preselectedCompanyId: isset($request->query['company_id']) ? (int) $request->query['company_id'] : null), 'contacts.new');
        $router->add('POST', '/contacts', static fn (Request $request): Response => $recordController->saveContact($request), 'contacts.create');
        $router->add('GET', '/contacts/{id}/edit', static fn (Request $request, array $parameters): Response => $recordController->contactForm((int) $parameters['id']), 'contacts.edit');
        $router->add('POST', '/contacts/{id}/update', static fn (Request $request, array $parameters): Response => $recordController->saveContact($request, (int) $parameters['id']), 'contacts.update');
        $router->add('POST', '/contacts/{id}/archive', static fn (Request $request, array $parameters): Response => $recordController->toggleContact($request, (int) $parameters['id'], false), 'contacts.archive');
        $router->add('POST', '/contacts/{id}/restore', static fn (Request $request, array $parameters): Response => $recordController->toggleContact($request, (int) $parameters['id'], true), 'contacts.restore');
        $router->add('GET', '/prospects', static fn (Request $request): Response => $prospectController->index($request), 'prospects.index');
        $router->add('GET', '/prospects/new', static fn (): Response => $prospectController->form(), 'prospects.new');
        $router->add('POST', '/prospects', static fn (Request $request): Response => $prospectController->save($request), 'prospects.create');
        $router->add('GET', '/prospects/{id}', static fn (Request $request,array $p): Response => $prospectController->show((int)$p['id']), 'prospects.show');
        $router->add('GET', '/prospects/{id}/edit', static fn (Request $request,array $p): Response => $prospectController->form((int)$p['id']), 'prospects.edit');
        $router->add('POST', '/prospects/{id}/update', static fn (Request $request,array $p): Response => $prospectController->save($request,(int)$p['id']), 'prospects.update');
        $router->add('POST', '/prospects/{id}/transition', static fn (Request $request,array $p): Response => $prospectController->transition($request,(int)$p['id']), 'prospects.transition');
        $router->add('POST', '/prospects/{id}/signals', static fn (Request $request,array $p): Response => $prospectController->signal($request,(int)$p['id']), 'prospects.signals.add');
        $router->add('POST', '/prospects/{id}/signals/{signalId}/update', static fn (Request $request,array $p): Response => $prospectController->updateSignal($request,(int)$p['id'],(int)$p['signalId']), 'prospects.signals.update');
        $router->add('POST', '/prospects/{id}/signals/{signalId}/remove', static fn (Request $request,array $p): Response => $prospectController->removeSignal($request,(int)$p['id'],(int)$p['signalId']), 'prospects.signals.remove');
        $router->add('POST', '/prospects/{id}/status-events/{eventId}/void', static fn (Request $request,array $p): Response => $prospectController->voidEvent($request,(int)$p['id'],(int)$p['eventId']), 'prospects.events.void');
        $router->add('POST', '/prospects/{id}/archive', static fn (Request $request,array $p): Response => $prospectController->archive($request,(int)$p['id'],false), 'prospects.archive');
        $router->add('POST', '/prospects/{id}/restore', static fn (Request $request,array $p): Response => $prospectController->archive($request,(int)$p['id'],true), 'prospects.restore');
        $router->add('POST', '/prospects/{id}/interactions', static fn (Request $request,array $p): Response => $interactionController->record($request,(int)$p['id']), 'interactions.create');
        $router->add('POST', '/interactions/{id}/correct', static fn (Request $request,array $p): Response => $interactionController->correct($request,(int)$p['id']), 'interactions.correct');
        $router->add('POST', '/prospects/{id}/follow-ups', static fn (Request $request,array $p): Response => $followUpController->schedule($request,(int)$p['id']), 'followups.schedule');
        $router->add('POST', '/follow-ups/{id}/reschedule', static fn (Request $request,array $p): Response => $followUpController->reschedule($request,(int)$p['id']), 'followups.reschedule');
        $router->add('POST', '/follow-ups/{id}/complete', static fn (Request $request,array $p): Response => $followUpController->close($request,(int)$p['id'],false), 'followups.complete');
        $router->add('POST', '/follow-ups/{id}/cancel', static fn (Request $request,array $p): Response => $followUpController->close($request,(int)$p['id'],true), 'followups.cancel');
        $router->add('POST', '/prospects/{id}/opportunities', static fn (Request $request,array $p): Response => $opportunityController->create($request,(int)$p['id']), 'opportunities.create');
        $router->add('GET', '/opportunities/{id}', static fn (Request $request,array $p): Response => $opportunityController->show((int)$p['id']), 'opportunities.show');
        $router->add('GET', '/opportunities/{id}/edit', static fn (Request $request,array $p): Response => $opportunityController->edit((int)$p['id']), 'opportunities.edit');
        $router->add('POST', '/opportunities/{id}/update', static fn (Request $request,array $p): Response => $opportunityController->update($request,(int)$p['id']), 'opportunities.update');
        $router->add('POST', '/opportunities/{id}/transition', static fn (Request $request,array $p): Response => $opportunityController->transition($request,(int)$p['id']), 'opportunities.transition');
        $router->add('POST', '/opportunities/{id}/stage-events/{eventId}/void', static fn (Request $request,array $p): Response => $opportunityController->voidEvent($request,(int)$p['id'],(int)$p['eventId']), 'opportunities.events.void');

        $headers = [
            'Content-Security-Policy' => "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'",
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Permissions-Policy' => 'camera=(), geolocation=(), microphone=(), payment=(), usb=()',
        ];
        if (str_starts_with((string) $app['canonical_origin'], 'https://')) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        return new Application($router, $logger, (bool) $app['debug'], $view, $headers);
    }

    /** @param array<string, mixed> $app */
    private static function validate(array $app): void
    {
        new DateTimeZone((string) ($app['owner_timezone'] ?? ''));
        $origin = (string) ($app['canonical_origin'] ?? '');
        if (filter_var($origin, FILTER_VALIDATE_URL) === false) {
            throw new \RuntimeException('APP_ORIGIN must be an absolute URL.');
        }
        if (($app['environment'] ?? '') === 'production' && ($app['debug'] ?? false) === true) {
            throw new \RuntimeException('Debug mode must be disabled in production.');
        }
        if (($app['environment'] ?? '') === 'production' && ($app['auth_fingerprint_key'] ?? '') === 'local-development-only-key') {
            throw new \RuntimeException('AUTH_FINGERPRINT_KEY must be configured in production.');
        }
        if (($app['environment'] ?? '') === 'production' && ($app['import_signing_key'] ?? '') === 'local-import-development-only-key') {
            throw new \RuntimeException('IMPORT_SIGNING_KEY must be configured in production.');
        }
        if (($app['environment'] ?? '') === 'production'
            && !str_starts_with($origin, 'https://')
            && !preg_match('#^http://(localhost|127\.0\.0\.1)(?::\d+)?$#', $origin)) {
            throw new \RuntimeException('Production APP_ORIGIN must use HTTPS.');
        }
        if (strlen((string) ($app['auth_fingerprint_key'] ?? '')) < 24) {
            throw new \RuntimeException('AUTH_FINGERPRINT_KEY must contain at least 24 characters.');
        }
        if (strlen((string) ($app['import_signing_key'] ?? '')) < 24) {
            throw new \RuntimeException('IMPORT_SIGNING_KEY must contain at least 24 characters.');
        }
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', (string) ($app['session']['name'] ?? '')) !== 1) {
            throw new \RuntimeException('SESSION_NAME is invalid.');
        }
        $basePath = (string) ($app['base_path'] ?? '');
        if ($basePath !== '' && ($basePath[0] !== '/' || str_contains($basePath, '..'))) {
            throw new \RuntimeException('APP_BASE_PATH must be empty or an absolute URL path.');
        }
        if ((int) ($app['session']['idle_seconds'] ?? 0) < 60
            || (int) ($app['session']['absolute_seconds'] ?? 0) < (int) ($app['session']['idle_seconds'] ?? 0)) {
            throw new \RuntimeException('Session lifetimes are invalid.');
        }
        if ((int) ($app['runtime_retention_hours'] ?? 0) < 24) {
            throw new \RuntimeException('RUNTIME_RETENTION_HOURS must be at least 24.');
        }
    }
}
