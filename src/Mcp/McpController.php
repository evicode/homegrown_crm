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
use Dreamsmith\Campaign\Records\RecordService;
use Dreamsmith\Campaign\Records\CompanyInput;
use Dreamsmith\Campaign\Records\ContactInput;
use Dreamsmith\Campaign\Prospect\ProspectService;
use Dreamsmith\Campaign\Prospect\ProspectInput;
use Dreamsmith\Campaign\Prospect\ProspectRules;
use Dreamsmith\Campaign\Application\Audit\AuditWriter;
use Dreamsmith\Campaign\Integration\Idempotency;
use Dreamsmith\Campaign\Support\SystemClock;
use Dreamsmith\Campaign\Interaction\InteractionService;
use Dreamsmith\Campaign\Interaction\InteractionInput;
use Dreamsmith\Campaign\FollowUp\FollowUpService;
use Dreamsmith\Campaign\Opportunity\OpportunityService;
use Dreamsmith\Campaign\LeadFinder\LeadCandidateService;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;

final class McpController
{
    public function __construct(private readonly Database $database, private readonly BearerTokenGuard $tokens, private readonly CapabilityCatalog $capabilities, private readonly string $sessionDirectory, private readonly array $sales, private readonly array $config) {}

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
            if (in_array('company.create', $allowed, true)) $server->addTool(fn (string $idempotency_key, string $name, ?string $website = null): array => $this->createCompany($actor, $idempotency_key, ['name'=>$name,'website'=>$website]), 'create_company', 'Create company', 'Creates a company.', inputSchema: $this->createCompanySchema());
            if (in_array('contact.search', $allowed, true)) {
                $server->addTool(fn (string $query = ''): array => $this->contacts($query), 'search_contacts', 'Search contacts', 'Searches active contacts.', inputSchema: $this->searchSchema());
                $server->addTool(fn (int $id): array => $this->contact($id), 'get_contact', 'Get contact', 'Gets an active contact by ID.', inputSchema: $this->idSchema());
            }
            if (in_array('contact.create', $allowed, true)) $server->addTool(fn (string $idempotency_key, ?int $company_id = null, ?string $first_name = null, ?string $last_name = null, ?string $email = null): array => $this->createContact($actor, $idempotency_key, ['company_id'=>$company_id,'first_name'=>$first_name,'last_name'=>$last_name,'email'=>$email]), 'create_contact', 'Create contact', 'Creates a contact.', inputSchema: $this->createContactSchema());
            if (in_array('prospect.search', $allowed, true)) {
                $server->addTool(fn (string $query = ''): array => $this->prospects($query), 'search_prospects', 'Search prospects', 'Searches prospects in the active campaign.', inputSchema: $this->searchSchema());
                $server->addTool(fn (int $id): array => $this->prospect($id), 'get_prospect', 'Get prospect', 'Gets an active prospect by ID.', inputSchema: $this->idSchema());
            }
            if (in_array('prospect.create', $allowed, true)) $server->addTool(fn (string $idempotency_key, ?int $company_id = null, ?int $primary_contact_id = null, string $segment = '', ?string $source = null, ?string $why_them = null): array => $this->createProspect($actor, $idempotency_key, ['company_id'=>$company_id,'primary_contact_id'=>$primary_contact_id,'segment'=>$segment,'source'=>$source,'why_them'=>$why_them]), 'create_prospect', 'Create prospect', 'Creates a prospect in the active campaign.', inputSchema: $this->createProspectSchema());
            if (in_array('prospect.transition', $allowed, true)) $server->addTool(fn (string $idempotency_key, int $prospect_id, int $prospect_version, string $to_status, string $reason = ''): array => $this->transitionProspect($actor, $idempotency_key, $prospect_id, $prospect_version, $to_status, $reason), 'transition_prospect', 'Transition prospect', 'Moves a prospect to a valid next status.');
            if (in_array('interaction.record', $allowed, true)) $server->addTool(fn (string $idempotency_key, int $prospect_id, int $prospect_version, string $type, string $direction, string $occurred_at, string $summary, string $outcome, ?int $contact_id = null): array => $this->recordInteraction($actor, $idempotency_key, compact('prospect_id','prospect_version','type','direction','occurred_at','summary','outcome','contact_id')), 'record_interaction', 'Record interaction', 'Records an interaction for a prospect.');
            if (in_array('follow_up.schedule', $allowed, true)) $server->addTool(fn (string $idempotency_key, int $prospect_id, int $prospect_version, string $action, string $due_at): array => $this->scheduleFollowUp($actor, $idempotency_key, compact('prospect_id','prospect_version','action','due_at')), 'schedule_follow_up', 'Schedule follow-up', 'Schedules the next action for a prospect.');
            if (in_array('opportunity.create', $allowed, true)) $server->addTool(fn (string $idempotency_key, int $prospect_id, int $prospect_version, string $offer_key, string $value_amount, ?string $expected_close_on = null, bool $attach_follow_up = false): array => $this->createOpportunity($actor, $idempotency_key, compact('prospect_id','prospect_version','offer_key','value_amount','expected_close_on','attach_follow_up')), 'create_opportunity', 'Create opportunity', 'Creates an opportunity for a qualified prospect.');
            if (in_array('opportunity.get', $allowed, true)) $server->addTool(fn (int $id): array => $this->opportunity($id), 'get_opportunity', 'Get opportunity', 'Gets an opportunity by ID.', inputSchema: $this->idSchema());
            if (in_array('lead_candidate.propose', $allowed, true)) $server->addTool(fn (string $idempotency_key,string $source,string $source_id,string $name,string $fit,int $score=0,?string $search_query=null,?string $website=null,?string $address=null,?string $category=null,?string $business_status=null,array $evidence=[]): array => $this->proposeLeadCandidate($actor,$idempotency_key,compact('source','source_id','name','fit','score','search_query','website','address','category','business_status','evidence')), 'propose_lead_candidate', 'Propose lead candidate', 'Adds researched lead evidence to the owner review queue.', inputSchema: $this->leadCandidateSchema());
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
    private function createCompany(object $actor, string $key, array $input): array { [$company,$errors] = CompanyInput::fromArray($input); if ($company === null) return ['ok'=>false,'errors'=>$errors]; return $this->mutation($actor, $key, 'company.create', $input, function () use ($company,$actor): array { $id=(new RecordService($this->database,new AuditWriter(),new SystemClock()))->createCompany($company,false,(int)$actor->ownerUserId,$actor->correlationId); return ['ok'=>true,'company'=>$this->companyRow((new RecordRepository($this->database->pdo()))->company($id))]; }); }
    private function createContact(object $actor, string $key, array $input): array { [$contact,$errors] = ContactInput::fromArray($input); if ($contact === null) return ['ok'=>false,'errors'=>$errors]; return $this->mutation($actor, $key, 'contact.create', $input, function () use ($contact,$actor): array { $id=(new RecordService($this->database,new AuditWriter(),new SystemClock()))->createContact($contact,false,(int)$actor->ownerUserId,$actor->correlationId); return ['ok'=>true,'contact'=>$this->contactRow((new RecordRepository($this->database->pdo()))->contact($id))]; }); }
    private function createProspect(object $actor, string $key, array $input): array { foreach(['problem_understood','timing_understood','buyer_understood','budget_plausible'] as $field)$input[$field]='0'; [$prospect,$errors] = ProspectInput::fromArray($input,$this->sales); if ($prospect === null) return ['ok'=>false,'errors'=>$errors]; return $this->mutation($actor, $key, 'prospect.create', $input, function () use ($prospect,$actor): array { $id=(new ProspectService($this->database,new AuditWriter(),new SystemClock(),new ProspectRules($this->sales)))->create($prospect,(int)$actor->ownerUserId,$actor->correlationId); return ['ok'=>true,'prospect'=>$this->prospectRow((new ProspectRepository($this->database->pdo()))->find($id))]; }); }
    private function transitionProspect(object $actor,string $key,int $id,int $version,string $to,string $reason): array { if(!isset($this->sales['statuses'][$to])) return ['ok'=>false,'errors'=>['to_status'=>'Invalid status.']]; return $this->mutation($actor,$key,'prospect.transition',['id'=>$id,'version'=>$version,'to'=>$to,'reason'=>$reason],function()use($actor,$id,$version,$to,$reason):array{(new ProspectService($this->database,new AuditWriter(),new SystemClock(),new ProspectRules($this->sales)))->transition($id,$to,$reason,$version,(int)$actor->ownerUserId,$actor->correlationId);return['ok'=>true,'prospect'=>$this->prospectRow((new ProspectRepository($this->database->pdo()))->find($id))];}); }
    private function recordInteraction(object $actor,string $key,array $input): array { $timezone=(string)$this->database->pdo()->query('SELECT owner_timezone FROM application_settings WHERE id=1')->fetchColumn(); [$value,$errors]=InteractionInput::fromArray($input,$this->sales,$timezone,(new SystemClock())->now());if($value===null)return['ok'=>false,'errors'=>$errors];return $this->mutation($actor,$key,'interaction.record',$input,function()use($actor,$input,$value):array{$id=(new InteractionService($this->database,new AuditWriter(),new SystemClock()))->record((int)$input['prospect_id'],$value,(int)$input['prospect_version'],(int)$actor->ownerUserId,$actor->correlationId);return['ok'=>true,'interaction_id'=>$id,'prospect'=>$this->prospectRow((new ProspectRepository($this->database->pdo()))->find((int)$input['prospect_id']))];}); }
    private function scheduleFollowUp(object $actor,string $key,array $input): array { try{$due=new \DateTimeImmutable((string)$input['due_at']);}catch(\Throwable){return['ok'=>false,'errors'=>['due_at'=>'Invalid timestamp.']];}return $this->mutation($actor,$key,'follow_up.schedule',$input,function()use($actor,$input,$due):array{$id=(new FollowUpService($this->database,new AuditWriter(),new SystemClock()))->schedule((int)$input['prospect_id'],(string)$input['action'],$due,(int)$input['prospect_version'],(int)$actor->ownerUserId,$actor->correlationId);return['ok'=>true,'follow_up_id'=>$id];}); }
    private function createOpportunity(object $actor,string $key,array $input): array { $input['attach_follow_up']=!empty($input['attach_follow_up'])?'1':'0';return $this->mutation($actor,$key,'opportunity.create',$input,function()use($actor,$input):array{$id=(new OpportunityService($this->database,new AuditWriter(),new SystemClock(),$this->sales))->create((int)$input['prospect_id'],$input,(int)$input['prospect_version'],(int)$actor->ownerUserId,$actor->correlationId);return['ok'=>true,'opportunity_id'=>$id];}); }
    private function proposeLeadCandidate(object $actor,string $key,array $input):array{return $this->mutation($actor,$key,'lead_candidate.propose',$input,function()use($actor,$input):array{return['ok'=>true,'candidate'=>(new LeadCandidateService($this->database,new AuditWriter(),new SystemClock()))->propose($input,$actor)];});}
    private function mutation(object $actor,string $key,string $operation,array $input,callable $work): array { $idempotency=new Idempotency();$claim=$idempotency->claim($this->database->pdo(),(int)$actor->integrationClientId,$key,$operation,Idempotency::fingerprint($operation,$input),(int)$this->config['idempotency_retention_hours'],new \DateTimeImmutable());if($claim['state']==='replay')return($claim['replay']??[])+['replayed'=>true];$outcome=$work();$idempotency->complete($this->database->pdo(),$claim['record_id'],'200',$outcome,new \DateTimeImmutable());return$outcome+['replayed'=>false]; }
    private function searchSchema(): array { return ['type'=>'object','properties'=>['query'=>['type'=>'string','maxLength'=>200]],'additionalProperties'=>false]; }
    private function idSchema(): array { return ['type'=>'object','properties'=>['id'=>['type'=>'integer','minimum'=>1]],'required'=>['id'],'additionalProperties'=>false]; }
    private function createCompanySchema(): array { return ['type'=>'object','properties'=>['idempotency_key'=>['type'=>'string','minLength'=>16],'name'=>['type'=>'string'],'website'=>['type'=>['string','null']]],'required'=>['idempotency_key','name'],'additionalProperties'=>false]; }
    private function createContactSchema(): array { return ['type'=>'object','properties'=>['idempotency_key'=>['type'=>'string','minLength'=>16],'company_id'=>['type'=>['integer','null']],'first_name'=>['type'=>['string','null']],'last_name'=>['type'=>['string','null']],'email'=>['type'=>['string','null']]],'required'=>['idempotency_key'],'additionalProperties'=>false]; }
    private function createProspectSchema(): array { return ['type'=>'object','properties'=>['idempotency_key'=>['type'=>'string','minLength'=>16],'company_id'=>['type'=>['integer','null']],'primary_contact_id'=>['type'=>['integer','null']],'segment'=>['type'=>'string'],'source'=>['type'=>['string','null']],'why_them'=>['type'=>['string','null']]],'required'=>['idempotency_key','segment'],'additionalProperties'=>false]; }
    private function leadCandidateSchema():array{return ['type'=>'object','properties'=>['idempotency_key'=>['type'=>'string','minLength'=>16],'source'=>['type'=>'string'],'source_id'=>['type'=>'string'],'search_query'=>['type'=>['string','null']],'name'=>['type'=>'string'],'website'=>['type'=>['string','null']],'address'=>['type'=>['string','null']],'category'=>['type'=>['string','null']],'business_status'=>['type'=>['string','null']],'fit'=>['type'=>'string','enum'=>['strong_fit','possible_fit','not_fit']],'score'=>['type'=>'integer'],'evidence'=>['type'=>'array','items'=>['type'=>'string']]],'required'=>['idempotency_key','source','source_id','name','fit','score','evidence'],'additionalProperties'=>false];}
}
