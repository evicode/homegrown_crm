<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Mcp;

use Dreamsmith\Campaign\Campaign\CampaignRepository;
use Dreamsmith\Campaign\Http\Request;
use Dreamsmith\Campaign\Http\Response;
use Dreamsmith\Campaign\Integration\BearerTokenGuard;
use Dreamsmith\Campaign\Integration\CapabilityCatalog;
use Dreamsmith\Campaign\Persistence\Database;
use Dreamsmith\Campaign\Reporting\CampaignReportingService;
use Dreamsmith\Campaign\Records\RecordRepository;
use Dreamsmith\Campaign\Prospect\ProspectRepository;
use Dreamsmith\Campaign\Opportunity\OpportunityRepository;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;

final class McpController
{
    public function __construct(private readonly Database $database, private readonly BearerTokenGuard $tokens, private readonly CapabilityCatalog $capabilities, private readonly string $sessionDirectory) {}

    public function handle(Request $request): Response
    {
        try {
            $actor = $this->tokens->authenticate($request);
            $server = Server::builder()->setServerInfo('Dreamsmith Campaign', '1.0.0')->setProtocolVersion(ProtocolVersion::V2025_11_25)->setSession(new FileSessionStore($this->sessionDirectory, 900));
            $allowed = $this->capabilities->discover($actor);
            if (in_array('campaign.get_active', $allowed, true)) $server->addTool(fn (): array => $this->campaign(), 'get_active_campaign', 'Active campaign', 'Returns active campaign context.');
            if (in_array('report.get_campaign', $allowed, true)) $server->addTool(fn (): array => $this->report(), 'get_campaign_report', 'Campaign report', 'Returns the active campaign dashboard report.');
            if (in_array('company.search', $allowed, true)) {
                $server->addTool(fn (string $query = ''): array => $this->companies($query), 'search_companies', 'Search companies', 'Searches active companies.', inputSchema: $this->searchSchema());
                $server->addTool(fn (int $id): array => $this->company($id), 'get_company', 'Get company', 'Gets an active company by ID.', inputSchema: $this->idSchema());
            }
            if (in_array('contact.search', $allowed, true)) {
                $server->addTool(fn (string $query = ''): array => $this->contacts($query), 'search_contacts', 'Search contacts', 'Searches active contacts.', inputSchema: $this->searchSchema());
                $server->addTool(fn (int $id): array => $this->contact($id), 'get_contact', 'Get contact', 'Gets an active contact by ID.', inputSchema: $this->idSchema());
            }
            if (in_array('prospect.search', $allowed, true)) {
                $server->addTool(fn (string $query = ''): array => $this->prospects($query), 'search_prospects', 'Search prospects', 'Searches prospects in the active campaign.', inputSchema: $this->searchSchema());
                $server->addTool(fn (int $id): array => $this->prospect($id), 'get_prospect', 'Get prospect', 'Gets an active prospect by ID.', inputSchema: $this->idSchema());
            }
            if (in_array('opportunity.get', $allowed, true)) $server->addTool(fn (int $id): array => $this->opportunity($id), 'get_opportunity', 'Get opportunity', 'Gets an opportunity by ID.', inputSchema: $this->idSchema());
            $factory = new Psr17Factory(); $psr = $factory->createServerRequest($request->method, 'http://localhost' . $request->path, $_SERVER)->withBody($factory->createStream($request->rawBody));
            foreach ($request->headers as $name => $value) $psr = $psr->withHeader($name, $value);
            $mcpResponse = $server->build()->run(new StreamableHttpTransport($psr, $factory, $factory));
            $headers = []; foreach ($mcpResponse->getHeaders() as $name => $values) $headers[$name] = implode(', ', $values);
            return new Response((string) $mcpResponse->getBody(), $mcpResponse->getStatusCode(), $headers + ['Cache-Control' => 'private, no-store']);
        } catch (\DomainException) {
            return Response::json(['jsonrpc' => '2.0', 'error' => ['code' => -32001, 'message' => 'Bearer authentication is required.'], 'id' => null], 401, ['WWW-Authenticate' => 'Bearer', 'Cache-Control' => 'private, no-store']);
        } catch (\Throwable) {
            return Response::json(['jsonrpc' => '2.0', 'error' => ['code' => -32603, 'message' => 'Internal MCP error.'], 'id' => null], 500, ['Cache-Control' => 'private, no-store']);
        }
    }

    private function campaign(): array { $repository = new CampaignRepository($this->database->pdo()); $settings = $repository->settings(); $campaign = $settings['active_campaign_id'] === null ? null : $repository->find((int) $settings['active_campaign_id']); if ($campaign === null) return ['available' => false]; return ['available' => true, 'id' => (int) $campaign['id'], 'version' => (int) $campaign['version'], 'name' => $campaign['name'], 'start_date' => $campaign['start_date'], 'end_date' => $campaign['end_date']]; }
    private function report(): array { $repository = new CampaignRepository($this->database->pdo()); $settings = $repository->settings(); $campaign = $settings['active_campaign_id'] === null ? null : $repository->find((int) $settings['active_campaign_id']); if ($campaign === null) return ['available' => false]; return ['available' => true, 'campaign_id' => (int) $campaign['id'], 'report' => (new CampaignReportingService($this->database->pdo()))->dashboard($campaign, (string) $settings['owner_timezone'])]; }
    private function companies(string $query): array { $rows = (new RecordRepository($this->database->pdo()))->companies(false, trim($query), 'name', 'asc', 50); return ['items' => array_map(fn (array $r): array => $this->companyRow($r), $rows)]; }
    private function company(int $id): array { $row = (new RecordRepository($this->database->pdo()))->company($id); if ($row === null || $row['archived_at'] !== null) return ['found' => false]; return ['found' => true, 'company' => $this->companyRow($row)]; }
    private function contacts(string $query): array { $rows = (new RecordRepository($this->database->pdo()))->contacts(trim($query), 50); return ['items' => array_map(fn (array $r): array => $this->contactRow($r), $rows)]; }
    private function contact(int $id): array { $row = (new RecordRepository($this->database->pdo()))->contact($id); if ($row === null || $row['archived_at'] !== null) return ['found' => false]; return ['found' => true, 'contact' => $this->contactRow($row)]; }
    private function prospects(string $query): array { $campaign = $this->activeCampaignId(); if ($campaign === null) return ['items' => []]; $rows = (new ProspectRepository($this->database->pdo()))->all($campaign, false, trim($query), '', '', 'updated', 'desc', 50); return ['items' => array_map(fn (array $r): array => $this->prospectRow($r), $rows)]; }
    private function prospect(int $id): array { $row = (new ProspectRepository($this->database->pdo()))->find($id); if ($row === null || $row['archived_at'] !== null) return ['found' => false]; return ['found' => true, 'prospect' => $this->prospectRow($row)]; }
    private function opportunity(int $id): array { $row = (new OpportunityRepository($this->database->pdo()))->find($id); if ($row === null) return ['found' => false]; return ['found' => true, 'opportunity' => ['id'=>(int)$row['id'],'version'=>(int)$row['version'],'prospect_id'=>(int)$row['prospect_id'],'stage'=>$row['stage'],'offer_key'=>$row['offer_key'],'value_amount'=>$row['value_amount'],'expected_close_on'=>$row['expected_close_on']]]; }
    private function activeCampaignId(): ?int { $settings = (new CampaignRepository($this->database->pdo()))->settings(); return $settings['active_campaign_id'] === null ? null : (int) $settings['active_campaign_id']; }
    private function companyRow(array $r): array { return ['id'=>(int)$r['id'],'version'=>(int)$r['version'],'name'=>$r['name'],'website'=>$r['website'],'location'=>$r['location'],'industry'=>$r['industry']]; }
    private function contactRow(array $r): array { return ['id'=>(int)$r['id'],'version'=>(int)$r['version'],'company_id'=>$r['company_id']===null?null:(int)$r['company_id'],'company_name'=>$r['company_name']??null,'first_name'=>$r['first_name'],'last_name'=>$r['last_name'],'role'=>$r['role'],'email'=>$r['email']]; }
    private function prospectRow(array $r): array { return ['id'=>(int)$r['id'],'version'=>(int)$r['version'],'company_id'=>$r['company_id']===null?null:(int)$r['company_id'],'primary_contact_id'=>$r['primary_contact_id']===null?null:(int)$r['primary_contact_id'],'segment'=>$r['segment'],'status'=>$r['status'],'company_name'=>$r['company_name']??null,'source'=>$r['source'],'why_them'=>$r['why_them']]; }
    private function searchSchema(): array { return ['type'=>'object','properties'=>['query'=>['type'=>'string','maxLength'=>200]],'additionalProperties'=>false]; }
    private function idSchema(): array { return ['type'=>'object','properties'=>['id'=>['type'=>'integer','minimum'=>1]],'required'=>['id'],'additionalProperties'=>false]; }
}
