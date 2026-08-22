<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Api;

use DateTimeImmutable;
use DateTimeZone;
use Dreamsmith\Campaign\Campaign\CampaignRepository;
use Dreamsmith\Campaign\Http\Request;
use Dreamsmith\Campaign\Http\Response;
use Dreamsmith\Campaign\Integration\BearerTokenGuard;
use Dreamsmith\Campaign\Integration\CapabilityCatalog;
use Dreamsmith\Campaign\Integration\FixedWindowRateLimiter;
use Dreamsmith\Campaign\Integration\Idempotency;
use Dreamsmith\Campaign\Integration\IntegrationAccessEvents;
use Dreamsmith\Campaign\Interaction\InteractionInput;
use Dreamsmith\Campaign\Interaction\InteractionService;
use Dreamsmith\Campaign\Opportunity\OpportunityService;
use Dreamsmith\Campaign\Opportunity\OpportunityRepository;
use Dreamsmith\Campaign\Persistence\Database;
use Dreamsmith\Campaign\Prospect\ProspectInput;
use Dreamsmith\Campaign\Prospect\ProspectRepository;
use Dreamsmith\Campaign\Prospect\ProspectService;
use Dreamsmith\Campaign\Records\StaleRecordVersion;
use Dreamsmith\Campaign\Records\CompanyInput;
use Dreamsmith\Campaign\Records\ContactInput;
use Dreamsmith\Campaign\Records\RecordRepository;
use Dreamsmith\Campaign\Records\RecordService;
use Dreamsmith\Campaign\Reporting\CampaignReportingService;
use Dreamsmith\Campaign\Data\DataService;
use Dreamsmith\Campaign\FollowUp\FollowUpService;
use Dreamsmith\Campaign\Support\Clock;

/** The intentionally small, useful first public API surface. */
final class ApiController
{
    public function __construct(
        private readonly Database $database,
        private readonly BearerTokenGuard $tokens,
        private readonly CapabilityCatalog $capabilities,
        private readonly ProspectService $prospects,
        private readonly InteractionService $interactions,
        private readonly FollowUpService $followUps,
        private readonly OpportunityService $opportunities,
        private readonly RecordService $records,
        private readonly DataService $data,
        private readonly array $sales,
        private readonly array $config,
        private readonly Clock $clock,
    ) {}

    public function openApi(Request $request): Response
    {
        return Response::json([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Dreamsmith Campaign API', 'version' => '1.0.0'],
            'paths' => [
                '/api/v1/capabilities' => ['get' => ['operationId' => 'capability.discover']],
                '/api/v1/campaigns/active' => ['get' => ['operationId' => 'campaign.get_active']],
                '/api/v1/reports/campaign' => ['get' => ['operationId' => 'report.get_campaign']],
                '/api/v1/exports/{type}' => ['get' => ['operationId' => 'data.export']],
                '/api/v1/companies' => ['get' => ['operationId' => 'company.search'], 'post' => ['operationId' => 'company.create']],
                '/api/v1/contacts' => ['get' => ['operationId' => 'contact.search'], 'post' => ['operationId' => 'contact.create']],
                '/api/v1/prospects' => ['get' => ['operationId' => 'prospect.search'], 'post' => ['operationId' => 'prospect.create']],
                '/api/v1/prospects/{id}/interactions' => ['post' => ['operationId' => 'interaction.record']],
                '/api/v1/prospects/{id}/follow-ups' => ['post' => ['operationId' => 'follow_up.schedule']],
                '/api/v1/prospects/{id}/opportunities' => ['post' => ['operationId' => 'opportunity.create']],
            ],
            'components' => ['securitySchemes' => ['bearerToken' => ['type' => 'http', 'scheme' => 'bearer']]],
        ], 200, ['Cache-Control' => 'public, max-age=300']);
    }

    public function capabilities(Request $request): Response
    {
        return $this->read($request, 'capability.discover', fn ($actor): array => ['capabilities' => $this->capabilities->discover($actor)]);
    }

    public function activeCampaign(Request $request): Response
    {
        return $this->read($request, 'campaign.get_active', function (): array {
            $repository = new CampaignRepository($this->database->pdo());
            $settings = $repository->settings();
            $campaign = $settings['active_campaign_id'] === null ? null : $repository->find((int) $settings['active_campaign_id']);
            if ($campaign === null) throw new \OutOfBoundsException('No active campaign is configured.');
            return ['campaign' => $this->campaign($campaign)];
        });
    }

    public function campaignReport(Request $request): Response
    {
        return $this->read($request, 'report.get_campaign', function (): array {
            $repository = new CampaignRepository($this->database->pdo()); $settings = $repository->settings(); $campaign = $settings['active_campaign_id'] === null ? null : $repository->find((int) $settings['active_campaign_id']);
            if ($campaign === null) throw new \OutOfBoundsException('No active campaign is configured.');
            return ['campaign' => $this->campaign($campaign), 'report' => (new CampaignReportingService($this->database->pdo()))->dashboard($campaign, (string) $settings['owner_timezone'])];
        });
    }

    public function export(Request $request, string $type): Response
    {
        $actor = null;
        try {
            $actor = $this->tokens->authenticate($request); $this->capabilities->authorize($actor, 'data.export');
            $export = $this->data->export($type); $this->event($request, $actor->integrationClientId, 'data.export', '200');
            return new Response($export['body'], 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="' . $export['filename'] . '"'] + $this->headers());
        } catch (\DomainException $error) { $status = $actor === null ? 401 : (str_contains($error->getMessage(), 'scope') ? 403 : 409); return $this->failed($request, $actor?->integrationClientId, 'data.export', $status, $status === 401 ? 'authentication_required' : ($status === 403 ? 'insufficient_scope' : 'conflict'), $status === 401 ? 'Bearer authentication is required.' : $error->getMessage());
        } catch (\InvalidArgumentException $error) { return $this->failed($request, $actor?->integrationClientId, 'data.export', 404, 'not_found', $error->getMessage());
        } catch (\Throwable) { return $this->failed($request, $actor?->integrationClientId, 'data.export', 500, 'internal_error', 'An unexpected API error occurred.'); }
    }

    public function prospects(Request $request): Response
    {
        return $this->read($request, 'prospect.search', function () use ($request): array {
            $campaign = $this->active();
            $perPage = max(1, min(100, (int) ($request->query['per_page'] ?? 25)));
            $page = max(1, (int) ($request->query['page'] ?? 1));
            $repository = new ProspectRepository($this->database->pdo());
            $items = $repository->all((int) $campaign['id'], false, $this->text($request->query['q'] ?? ''), $this->text($request->query['segment'] ?? ''), $this->text($request->query['status'] ?? ''), 'updated', 'desc', $perPage, ($page - 1) * $perPage);
            return ['items' => array_map(fn (array $item): array => $this->prospectData($item), $items), 'page' => $page, 'per_page' => $perPage, 'next_page' => count($items) === $perPage ? $page + 1 : null];
        });
    }

    public function companies(Request $request): Response
    {
        return $this->read($request, 'company.search', function () use ($request): array {
            $perPage = max(1, min(100, (int) ($request->query['per_page'] ?? 25))); $page = max(1, (int) ($request->query['page'] ?? 1));
            $items = (new RecordRepository($this->database->pdo()))->companies(false, $this->text($request->query['q'] ?? ''), 'name', 'asc', $perPage, ($page - 1) * $perPage);
            return ['items' => array_map(fn (array $item): array => $this->companyData($item), $items), 'page' => $page, 'per_page' => $perPage, 'next_page' => count($items) === $perPage ? $page + 1 : null];
        });
    }

    public function company(Request $request, int $id): Response
    {
        return $this->read($request, 'company.search', function () use ($id): array { $value = (new RecordRepository($this->database->pdo()))->company($id); if ($value === null || $value['archived_at'] !== null) throw new \OutOfBoundsException('Company not found.'); return ['company' => $this->companyData($value), '_etag' => $this->etag('company', $id, (int) $value['version'])]; });
    }

    public function createCompany(Request $request): Response
    {
        return $this->mutate($request, 'company.create', function ($actor, array $body): array {
            $this->strict($body, ['name', 'website', 'location', 'industry', 'employee_range', 'revenue_range', 'notes', 'confirm_duplicate']); [$input, $errors] = CompanyInput::fromArray($body); if ($input === null) return $this->validation($errors);
            $id = $this->records->createCompany($input, !empty($body['confirm_duplicate']), (int) $actor->ownerUserId, $actor->correlationId);
            return ['status' => 201, 'location' => '/api/v1/companies/' . $id, 'company' => $this->companyData((new RecordRepository($this->database->pdo()))->company($id))];
        });
    }

    public function updateCompany(Request $request, int $id): Response
    {
        return $this->mutate($request, 'company.update', function ($actor, array $body) use ($request, $id): array {
            $this->strict($body, ['name', 'website', 'location', 'industry', 'employee_range', 'revenue_range', 'notes', 'confirm_duplicate']); [$input, $errors] = CompanyInput::fromArray($body); if ($input === null) return $this->validation($errors);
            $this->records->updateCompany($id, $input, $this->ifMatch($request, 'company', $id), !empty($body['confirm_duplicate']), (int) $actor->ownerUserId, $actor->correlationId);
            $company = (new RecordRepository($this->database->pdo()))->company($id); return ['company' => $this->companyData($company), '_etag' => $this->etag('company', $id, (int) $company['version'])];
        });
    }

    public function archiveCompany(Request $request, int $id, bool $restore = false): Response
    {
        return $this->mutate($request, $restore ? 'company.restore' : 'company.archive', function ($actor, array $body) use ($request, $id, $restore): array {
            $this->strict($body, $restore ? ['confirm_duplicate'] : ['confirm_contacts']); $version = $this->ifMatch($request, 'company', $id);
            if ($restore) $this->records->restoreCompany($id, $version, !empty($body['confirm_duplicate']), (int) $actor->ownerUserId, $actor->correlationId); else $this->records->archiveCompany($id, $version, !empty($body['confirm_contacts']), (int) $actor->ownerUserId, $actor->correlationId);
            $company = (new RecordRepository($this->database->pdo()))->company($id); return ['company' => $this->companyData($company), '_etag' => $this->etag('company', $id, (int) $company['version'])];
        });
    }

    public function contacts(Request $request): Response
    {
        return $this->read($request, 'contact.search', function () use ($request): array {
            $perPage = max(1, min(100, (int) ($request->query['per_page'] ?? 25))); $page = max(1, (int) ($request->query['page'] ?? 1));
            $items = (new RecordRepository($this->database->pdo()))->contacts($this->text($request->query['q'] ?? ''), $perPage, ($page - 1) * $perPage);
            return ['items' => array_map(fn (array $item): array => $this->contactData($item), $items), 'page' => $page, 'per_page' => $perPage, 'next_page' => count($items) === $perPage ? $page + 1 : null];
        });
    }

    public function contact(Request $request, int $id): Response
    {
        return $this->read($request, 'contact.search', function () use ($id): array { $value = (new RecordRepository($this->database->pdo()))->contact($id); if ($value === null || $value['archived_at'] !== null) throw new \OutOfBoundsException('Contact not found.'); return ['contact' => $this->contactData($value), '_etag' => $this->etag('contact', $id, (int) $value['version'])]; });
    }

    public function createContact(Request $request): Response
    {
        return $this->mutate($request, 'contact.create', function ($actor, array $body): array {
            $this->strict($body, ['company_id', 'first_name', 'last_name', 'role', 'email', 'phone', 'linkedin_url', 'confirm_duplicate']); [$input, $errors] = ContactInput::fromArray($body); if ($input === null) return $this->validation($errors);
            $id = $this->records->createContact($input, !empty($body['confirm_duplicate']), (int) $actor->ownerUserId, $actor->correlationId);
            return ['status' => 201, 'location' => '/api/v1/contacts/' . $id, 'contact' => $this->contactData((new RecordRepository($this->database->pdo()))->contact($id))];
        });
    }

    public function updateContact(Request $request, int $id): Response
    {
        return $this->mutate($request, 'contact.update', function ($actor, array $body) use ($request, $id): array {
            $this->strict($body, ['company_id', 'first_name', 'last_name', 'role', 'email', 'phone', 'linkedin_url', 'confirm_duplicate', 'confirm_archived_reassociation']); [$input, $errors] = ContactInput::fromArray($body); if ($input === null) return $this->validation($errors);
            $this->records->updateContact($id, $input, $this->ifMatch($request, 'contact', $id), !empty($body['confirm_duplicate']), !empty($body['confirm_archived_reassociation']), (int) $actor->ownerUserId, $actor->correlationId);
            $contact = (new RecordRepository($this->database->pdo()))->contact($id); return ['contact' => $this->contactData($contact), '_etag' => $this->etag('contact', $id, (int) $contact['version'])];
        });
    }

    public function archiveContact(Request $request, int $id, bool $restore = false): Response
    {
        return $this->mutate($request, $restore ? 'contact.restore' : 'contact.archive', function ($actor, array $body) use ($request, $id, $restore): array {
            $this->strict($body, $restore ? ['confirm_duplicate'] : []); $version = $this->ifMatch($request, 'contact', $id);
            if ($restore) $this->records->restoreContact($id, $version, !empty($body['confirm_duplicate']), (int) $actor->ownerUserId, $actor->correlationId); else $this->records->archiveContact($id, $version, (int) $actor->ownerUserId, $actor->correlationId);
            $contact = (new RecordRepository($this->database->pdo()))->contact($id); return ['contact' => $this->contactData($contact), '_etag' => $this->etag('contact', $id, (int) $contact['version'])];
        });
    }

    public function prospect(Request $request, int $id): Response
    {
        return $this->read($request, 'prospect.search', function () use ($id): array {
            $prospect = (new ProspectRepository($this->database->pdo()))->find($id);
            if ($prospect === null || $prospect['archived_at'] !== null) throw new \OutOfBoundsException('Prospect not found.');
            return ['prospect' => $this->prospectData($prospect), '_etag' => $this->etag('prospect', $id, (int) $prospect['version'])];
        });
    }

    public function createProspect(Request $request): Response
    {
        return $this->mutate($request, 'prospect.create', function ($actor, array $body): array {
            $this->strict($body, ['company_id', 'primary_contact_id', 'segment', 'source', 'why_them', 'business_problem', 'qualification_notes', 'problem_understood', 'timing_understood', 'buyer_understood', 'budget_plausible']);
            foreach (['problem_understood', 'timing_understood', 'buyer_understood', 'budget_plausible'] as $key) $body[$key] = !empty($body[$key]) ? '1' : '0';
            [$input, $errors] = ProspectInput::fromArray($body, $this->sales);
            if ($input === null) return $this->validation($errors);
            $id = $this->prospects->create($input, (int) $actor->ownerUserId, $actor->correlationId);
            return ['status' => 201, 'location' => '/api/v1/prospects/' . $id, 'prospect' => $this->prospectData((new ProspectRepository($this->database->pdo()))->find($id))];
        });
    }

    public function updateProspect(Request $request, int $id): Response
    {
        return $this->mutate($request, 'prospect.update', function ($actor, array $body) use ($request, $id): array {
            $this->strict($body, ['company_id', 'primary_contact_id', 'segment', 'source', 'why_them', 'business_problem', 'qualification_notes', 'problem_understood', 'timing_understood', 'buyer_understood', 'budget_plausible']);
            foreach (['problem_understood', 'timing_understood', 'buyer_understood', 'budget_plausible'] as $key) $body[$key] = !empty($body[$key]) ? '1' : '0'; [$input, $errors] = ProspectInput::fromArray($body, $this->sales); if ($input === null) return $this->validation($errors);
            $this->prospects->update($id, $input, $this->ifMatch($request, 'prospect', $id), (int) $actor->ownerUserId, $actor->correlationId);
            $prospect = (new ProspectRepository($this->database->pdo()))->find($id); return ['prospect' => $this->prospectData($prospect), '_etag' => $this->etag('prospect', $id, (int) $prospect['version'])];
        });
    }

    public function transitionProspect(Request $request, int $id): Response
    {
        return $this->mutate($request, 'prospect.transition', function ($actor, array $body) use ($request, $id): array {
            $this->strict($body, ['to_status', 'reason']); $to = $this->text($body['to_status'] ?? ''); if (!isset($this->sales['statuses'][$to])) throw new \InvalidArgumentException('to_status is invalid.');
            $this->prospects->transition($id, $to, $this->text($body['reason'] ?? ''), $this->ifMatch($request, 'prospect', $id), (int) $actor->ownerUserId, $actor->correlationId);
            $prospect = (new ProspectRepository($this->database->pdo()))->find($id); return ['prospect' => $this->prospectData($prospect), '_etag' => $this->etag('prospect', $id, (int) $prospect['version'])];
        });
    }

    public function addProspectSignal(Request $request, int $id): Response
    {
        return $this->mutate($request, 'prospect.signal_create', function ($actor, array $body) use ($request, $id): array {
            $this->strict($body, ['signal_id', 'evidence_note', 'observed_on']); $signal = $this->version($body['signal_id'] ?? null, 'signal_id');
            $this->prospects->addSignal($id, $signal, $this->text($body['evidence_note'] ?? ''), isset($body['observed_on']) ? $this->text($body['observed_on']) : null, $this->ifMatch($request, 'prospect', $id), (int) $actor->ownerUserId, $actor->correlationId);
            $repository = new ProspectRepository($this->database->pdo()); $prospect = $repository->find($id); return ['prospect' => $this->prospectData($prospect), 'signals' => $repository->signals($id), '_etag' => $this->etag('prospect', $id, (int) $prospect['version'])];
        });
    }

    public function archiveProspect(Request $request, int $id, bool $restore = false): Response
    {
        return $this->mutate($request, $restore ? 'prospect.restore' : 'prospect.archive', function ($actor, array $body) use ($request, $id, $restore): array {
            $this->strict($body, []); $this->prospects->archive($id, $this->ifMatch($request, 'prospect', $id), $restore, (int) $actor->ownerUserId, $actor->correlationId);
            $prospect = (new ProspectRepository($this->database->pdo()))->find($id); return ['prospect' => $this->prospectData($prospect), '_etag' => $this->etag('prospect', $id, (int) $prospect['version'])];
        });
    }

    public function recordInteraction(Request $request, int $id): Response
    {
        return $this->mutate($request, 'interaction.record', function ($actor, array $body) use ($id): array {
            $this->strict($body, ['prospect_version', 'contact_id', 'type', 'direction', 'occurred_at', 'summary', 'outcome']);
            $timezone = (string) $this->database->pdo()->query('SELECT owner_timezone FROM application_settings WHERE id=1')->fetchColumn();
            [$input, $errors] = InteractionInput::fromArray($body, $this->sales, $timezone, $this->clock->now());
            if ($input === null) return $this->validation($errors);
            $interactionId = $this->interactions->record($id, $input, $this->version($body['prospect_version'] ?? null, 'prospect_version'), (int) $actor->ownerUserId, $actor->correlationId);
            return ['status' => 201, 'location' => '/api/v1/prospects/' . $id . '/interactions/' . $interactionId, 'interaction' => ['id' => $interactionId], 'prospect' => $this->prospectData((new ProspectRepository($this->database->pdo()))->find($id))];
        });
    }

    public function scheduleFollowUp(Request $request, int $id): Response
    {
        return $this->mutate($request, 'follow_up.schedule', function ($actor, array $body) use ($id): array {
            $this->strict($body, ['prospect_version', 'action', 'due_at']);
            $rawDue = (string) ($body['due_at'] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?(?:Z|[+-]\d{2}:\d{2})$/', $rawDue) !== 1) throw new \InvalidArgumentException('due_at must be an ISO-8601 timestamp with a timezone.');
            $due = new DateTimeImmutable($rawDue, new DateTimeZone('UTC'));
            $followUpId = $this->followUps->schedule($id, $this->text($body['action'] ?? ''), $due->setTimezone(new DateTimeZone('UTC')), $this->version($body['prospect_version'] ?? null, 'prospect_version'), (int) $actor->ownerUserId, $actor->correlationId);
            return ['status' => 201, 'location' => '/api/v1/follow-ups/' . $followUpId, 'follow_up' => ['id' => $followUpId]];
        });
    }

    public function createOpportunity(Request $request, int $id): Response
    {
        return $this->mutate($request, 'opportunity.create', function ($actor, array $body) use ($id): array {
            $this->strict($body, ['prospect_version', 'offer_key', 'value_amount', 'expected_close_on', 'attach_follow_up']);
            $body['attach_follow_up'] = !empty($body['attach_follow_up']) ? '1' : '0';
            $opportunityId = $this->opportunities->create($id, $body, $this->version($body['prospect_version'] ?? null, 'prospect_version'), (int) $actor->ownerUserId, $actor->correlationId);
            return ['status' => 201, 'location' => '/api/v1/opportunities/' . $opportunityId, 'opportunity' => ['id' => $opportunityId]];
        });
    }

    public function followUp(Request $request, int $id): Response
    {
        return $this->read($request, 'follow_up.get', function () use ($id): array { $followUp = $this->followUpData($id); return ['follow_up' => $followUp, '_etag' => $this->etag('follow-up', $id, (int) $followUp['version'])]; });
    }

    public function rescheduleFollowUp(Request $request, int $id): Response
    {
        return $this->mutate($request, 'follow_up.reschedule', function ($actor, array $body) use ($request, $id): array {
            $this->strict($body, ['prospect_version', 'action', 'due_at']); $due = $this->isoTime($body['due_at'] ?? null);
            $this->followUps->reschedule($id, $this->text($body['action'] ?? ''), $due, $this->ifMatch($request, 'follow-up', $id), $this->version($body['prospect_version'] ?? null, 'prospect_version'), (int) $actor->ownerUserId, $actor->correlationId);
            $followUp = $this->followUpData($id); return ['follow_up' => $followUp, '_etag' => $this->etag('follow-up', $id, (int) $followUp['version'])];
        });
    }

    public function closeFollowUp(Request $request, int $id, bool $cancel = false): Response
    {
        return $this->mutate($request, $cancel ? 'follow_up.cancel' : 'follow_up.complete', function ($actor, array $body) use ($request, $id, $cancel): array {
            $this->strict($body, $cancel ? ['reason'] : []); $this->followUps->close($id, $this->ifMatch($request, 'follow-up', $id), $cancel, $this->text($body['reason'] ?? ''), (int) $actor->ownerUserId, $actor->correlationId);
            $followUp = $this->followUpData($id); return ['follow_up' => $followUp, '_etag' => $this->etag('follow-up', $id, (int) $followUp['version'])];
        });
    }

    public function opportunity(Request $request, int $id): Response
    {
        return $this->read($request, 'opportunity.get', function () use ($id): array { $opportunity = (new OpportunityRepository($this->database->pdo()))->find($id); if ($opportunity === null) throw new \OutOfBoundsException('Opportunity not found.'); return ['opportunity' => $this->opportunityData($opportunity), '_etag' => $this->etag('opportunity', $id, (int) $opportunity['version'])]; });
    }

    public function updateOpportunity(Request $request, int $id): Response
    {
        return $this->mutate($request, 'opportunity.update', function ($actor, array $body) use ($request, $id): array {
            $this->strict($body, ['offer_key', 'value_amount', 'expected_close_on']); $this->opportunities->update($id, $body, $this->ifMatch($request, 'opportunity', $id), (int) $actor->ownerUserId, $actor->correlationId);
            $opportunity = (new OpportunityRepository($this->database->pdo()))->find($id); return ['opportunity' => $this->opportunityData($opportunity), '_etag' => $this->etag('opportunity', $id, (int) $opportunity['version'])];
        });
    }

    public function transitionOpportunity(Request $request, int $id): Response
    {
        return $this->mutate($request, 'opportunity.transition', function ($actor, array $body) use ($request, $id): array {
            $this->strict($body, ['to_stage', 'reason', 'follow_up_disposition']); $to = $this->text($body['to_stage'] ?? ''); if (!isset($this->sales['opportunity_stages'][$to])) throw new \InvalidArgumentException('to_stage is invalid.');
            $this->opportunities->transition($id, $to, $this->text($body['reason'] ?? ''), $this->ifMatch($request, 'opportunity', $id), $this->text($body['follow_up_disposition'] ?? ''), (int) $actor->ownerUserId, $actor->correlationId);
            $opportunity = (new OpportunityRepository($this->database->pdo()))->find($id); return ['opportunity' => $this->opportunityData($opportunity), '_etag' => $this->etag('opportunity', $id, (int) $opportunity['version'])];
        });
    }

    private function read(Request $request, string $capability, callable $operation): Response
    {
        return $this->run($request, $capability, false, fn ($actor): array => $operation($actor));
    }

    private function mutate(Request $request, string $capability, callable $operation): Response
    {
        if (strtolower(explode(';', (string) ($request->headers['content-type'] ?? ''))[0]) !== 'application/json') return $this->problem(415, 'unsupported_media_type', 'Use application/json for API mutations.');
        if ($request->rawBody === '' || json_decode($request->rawBody, true) === null) return $this->problem(400, 'invalid_json', 'Request body must be valid JSON.');
        return $this->run($request, $capability, true, fn ($actor): array => $operation($actor, $request->body));
    }

    private function run(Request $request, string $capability, bool $mutation, callable $operation): Response
    {
        $actor = null;
        try {
            $actor = $this->tokens->authenticate($request);
            $this->capabilities->authorize($actor, $capability);
            $rate = (new FixedWindowRateLimiter())->consume($this->database->pdo(), 'api.' . ($mutation ? 'write' : 'read'), (string) $actor->integrationClientId, $mutation ? 60 : 300, 60, $this->clock->now());
            if (!$rate['allowed']) return $this->problem(429, 'rate_limited', 'Too many API requests.', ['Retry-After' => (string) $rate['retry_after']]);
            $claim = null;
            if ($mutation) {
                $key = (string) ($request->headers['idempotency-key'] ?? '');
                $claim = (new Idempotency())->claim($this->database->pdo(), (int) $actor->integrationClientId, $key, $capability, Idempotency::fingerprint($capability, $request->body), (int) $this->config['idempotency_retention_hours'], $this->clock->now());
                if ($claim['state'] === 'replay') return Response::json($claim['replay'] ?? [], 200, $this->headers());
            }
            $result = $operation($actor);
            if (isset($result['status']) && $result['status'] === 422) return Response::json($result, 422, $this->headers());
            $status = (int) ($result['status'] ?? 200); unset($result['status']);
            $headers = $this->headers(); if (isset($result['location'])) { $headers['Location'] = (string) $result['location']; unset($result['location']); } if (isset($result['_etag'])) { $headers['ETag'] = (string) $result['_etag']; unset($result['_etag']); }
            if ($claim !== null) (new Idempotency())->complete($this->database->pdo(), $claim['record_id'], (string) $status, $result, $this->clock->now());
            $this->event($request, $actor->integrationClientId, $capability, (string) $status);
            return Response::json($result, $status, $headers);
        } catch (\OutOfBoundsException $error) { return $this->failed($request, $actor?->integrationClientId, $capability, 404, 'not_found', $error->getMessage());
        } catch (StaleRecordVersion $error) { return $this->failed($request, $actor?->integrationClientId, $capability, 412, 'stale_version', $error->getMessage());
        } catch (\LogicException $error) { return $this->failed($request, $actor?->integrationClientId, $capability, 428, 'precondition_required', $error->getMessage());
        } catch (\InvalidArgumentException $error) { return $this->failed($request, $actor?->integrationClientId, $capability, 422, 'validation_failed', $error->getMessage());
        } catch (\DomainException $error) {
            $status = $actor === null ? 401 : (str_contains($error->getMessage(), 'scope') ? 403 : 409);
            return $this->failed($request, $actor?->integrationClientId, $capability, $status, $status === 401 ? 'authentication_required' : ($status === 403 ? 'insufficient_scope' : 'conflict'), $status === 401 ? 'Bearer authentication is required.' : $error->getMessage());
        } catch (\Throwable $error) { return $this->failed($request, $actor?->integrationClientId, $capability, 500, 'internal_error', 'An unexpected API error occurred.'); }
    }

    private function failed(Request $request, ?int $clientId, string $operation, int $status, string $code, string $detail): Response
    {
        $this->event($request, $clientId, $operation, $code);
        $headers = $this->headers(); if ($status === 401) $headers['WWW-Authenticate'] = 'Bearer';
        return $this->problem($status, $code, $detail, $headers);
    }
    private function event(Request $request, ?int $clientId, string $operation, string $outcome): void { try { (new IntegrationAccessEvents())->write($this->database->pdo(), $clientId, (string) $this->config['environment'], 'api', $operation, $outcome, $request->requestId, $request->clientIp, $this->clock->now()); } catch (\Throwable) {} }
    private function active(): array { $repository = new CampaignRepository($this->database->pdo()); $settings = $repository->settings(); $campaign = $settings['active_campaign_id'] === null ? null : $repository->find((int) $settings['active_campaign_id']); if ($campaign === null) throw new \OutOfBoundsException('No active campaign is configured.'); return $campaign; }
    private function strict(array $body, array $allowed): void { foreach (array_keys($body) as $key) if (!in_array($key, $allowed, true)) throw new \InvalidArgumentException('Unknown field: ' . $key . '.'); }
    private function version(mixed $value, string $field): int { $version = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]); if ($version === false) throw new \InvalidArgumentException($field . ' must be a positive integer.'); return $version; }
    private function etag(string $type, int $id, int $version): string { return '"' . $type . '-' . $id . '-' . $version . '"'; }
    private function ifMatch(Request $request, string $type, int $id): int { $header = (string) ($request->headers['if-match'] ?? ''); if (preg_match('/^"' . preg_quote($type, '/') . '-' . $id . '-([1-9][0-9]*)"$/', $header, $matches) !== 1) throw new \LogicException('A current If-Match ETag is required.'); return (int) $matches[1]; }
    private function text(mixed $value): string { return is_string($value) ? trim($value) : ''; }
    private function isoTime(mixed $value): DateTimeImmutable { $raw = $this->text($value); if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?(?:Z|[+-]\d{2}:\d{2})$/', $raw) !== 1) throw new \InvalidArgumentException('due_at must be an ISO-8601 timestamp with a timezone.'); return new DateTimeImmutable($raw, new DateTimeZone('UTC')); }
    private function validation(array $errors): array { return ['status' => 422, 'code' => 'validation_failed', 'errors' => $errors]; }
    private function headers(): array { return ['Cache-Control' => 'private, no-store', 'X-API-Version' => '1']; }
    private function problem(int $status, string $code, string $detail, array $headers = []): Response { return Response::json(['type' => 'https://dreamsmith.local/problems/' . $code, 'title' => str_replace('_', ' ', $code), 'status' => $status, 'code' => $code, 'detail' => $detail], $status, $headers + $this->headers()); }
    private function campaign(array $value): array { return ['id' => (int) $value['id'], 'version' => (int) $value['version'], 'name' => $value['name'], 'start_date' => $value['start_date'], 'end_date' => $value['end_date']]; }
    private function companyData(?array $value): array { if ($value === null) throw new \OutOfBoundsException('Company not found.'); return ['id' => (int) $value['id'], 'version' => (int) $value['version'], 'name' => $value['name'], 'website' => $value['website'], 'location' => $value['location'], 'industry' => $value['industry'], 'employee_range' => $value['employee_range'], 'revenue_range' => $value['revenue_range'], 'notes' => $value['notes']]; }
    private function contactData(?array $value): array { if ($value === null) throw new \OutOfBoundsException('Contact not found.'); return ['id' => (int) $value['id'], 'version' => (int) $value['version'], 'company_id' => $value['company_id'] === null ? null : (int) $value['company_id'], 'company_name' => $value['company_name'] ?? null, 'first_name' => $value['first_name'], 'last_name' => $value['last_name'], 'role' => $value['role'], 'email' => $value['email'], 'phone' => $value['phone'], 'linkedin_url' => $value['linkedin_url']]; }
    private function followUpData(int $id): array { $s = $this->database->pdo()->prepare('SELECT * FROM follow_ups WHERE id=:id'); $s->execute(['id' => $id]); $value = $s->fetch(); if (!is_array($value)) throw new \OutOfBoundsException('Follow-up not found.'); return ['id' => (int) $value['id'], 'version' => (int) $value['version'], 'prospect_id' => (int) $value['prospect_id'], 'opportunity_id' => $value['opportunity_id'] === null ? null : (int) $value['opportunity_id'], 'action' => $value['action'], 'due_at' => $value['due_at'], 'status' => $value['status'], 'cancellation_reason' => $value['cancellation_reason']]; }
    private function opportunityData(?array $value): array { if ($value === null) throw new \OutOfBoundsException('Opportunity not found.'); return ['id' => (int) $value['id'], 'version' => (int) $value['version'], 'prospect_id' => (int) $value['prospect_id'], 'stage' => $value['stage'], 'offer_key' => $value['offer_key'], 'value_amount' => $value['value_amount'], 'expected_close_on' => $value['expected_close_on'], 'closed_at' => $value['closed_at'], 'lost_reason' => $value['lost_reason']]; }
    private function prospectData(?array $value): array { if ($value === null) throw new \OutOfBoundsException('Prospect not found.'); return ['id' => (int) $value['id'], 'version' => (int) $value['version'], 'campaign_id' => (int) $value['campaign_id'], 'company_id' => $value['company_id'] === null ? null : (int) $value['company_id'], 'primary_contact_id' => $value['primary_contact_id'] === null ? null : (int) $value['primary_contact_id'], 'segment' => $value['segment'], 'status' => $value['status'], 'source' => $value['source'], 'why_them' => $value['why_them'], 'business_problem' => $value['business_problem'], 'qualification_notes' => $value['qualification_notes'], 'company_name' => $value['company_name'] ?? null, 'contact_name' => trim((string) (($value['first_name'] ?? '') . ' ' . ($value['last_name'] ?? ''))) ?: null]; }
}
