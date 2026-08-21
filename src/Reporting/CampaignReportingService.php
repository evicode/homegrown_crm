<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Reporting;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Dreamsmith\Campaign\Campaign\CampaignMetrics;
use PDO;

final class CampaignReportingService
{
    public function __construct(private readonly PDO $pdo) {}

    /** @param array<string,mixed> $campaign @return array<string,mixed> */
    public function dashboard(array $campaign, string $timezone): array
    {
        $window = $this->window($campaign, $timezone);
        $campaignId = (int) $campaign['id'];
        $metrics = $this->metrics($campaignId, $window);
        return [
            'campaign' => $this->campaignContext($campaign, $window),
            'funnel' => $this->funnel($campaignId),
            'pipeline' => $this->pipeline($campaignId),
            'queues' => $this->queues($campaignId, $window),
            'targets' => $this->targets($campaignId, $metrics),
            'metrics' => $metrics,
            'rates' => $this->rates($metrics),
            'today' => $window['today']->format('Y-m-d'),
        ];
    }

    /** @param array<string,mixed> $campaign @return list<array<string,mixed>> */
    public function drillDown(array $campaign, string $timezone, string $metric): array
    {
        $window = $this->window($campaign, $timezone);
        $id = (int) $campaign['id'];
        $base = ' FROM prospects p LEFT JOIN companies c ON c.id=p.company_id LEFT JOIN contacts ct ON ct.id=p.primary_contact_id ';
        $identity = "COALESCE(c.name, NULLIF(TRIM(CONCAT_WS(' ', ct.first_name, ct.last_name)), '')) relationship_name";
        $queries = [
            'selected_prospects' => ["SELECT p.id,p.version,{$identity}{$base} WHERE p.campaign_id=:campaign AND p.archived_at IS NULL ORDER BY relationship_name,p.id", ['campaign' => $id], 'prospect'],
            'personalized_contacts' => ["SELECT p.id,p.version,{$identity}{$base} JOIN (SELECT i.prospect_id,MIN(i.occurred_at) first_at FROM interactions i JOIN prospects ip ON ip.id=i.prospect_id WHERE ip.campaign_id=:campaign AND i.voided_at IS NULL AND i.direction='outbound' AND i.qualifies_as_contact=1 GROUP BY i.prospect_id) e ON e.prospect_id=p.id WHERE e.first_at>=:start_at AND e.first_at<:end_at ORDER BY e.first_at,p.id", $this->periodParams($id, $window), 'prospect'],
            'partners_contacted' => ["SELECT p.id,p.version,{$identity}{$base} JOIN (SELECT i.prospect_id,MIN(i.occurred_at) first_at FROM interactions i JOIN prospects ip ON ip.id=i.prospect_id WHERE ip.campaign_id=:campaign AND ip.segment='partner' AND i.voided_at IS NULL AND i.direction='outbound' AND i.qualifies_as_contact=1 GROUP BY i.prospect_id) e ON e.prospect_id=p.id WHERE e.first_at>=:start_at AND e.first_at<:end_at ORDER BY e.first_at,p.id", $this->periodParams($id, $window), 'prospect'],
            'network_contacts' => ["SELECT p.id,p.version,{$identity}{$base} JOIN (SELECT i.prospect_id,MIN(i.occurred_at) first_at FROM interactions i JOIN prospects ip ON ip.id=i.prospect_id WHERE ip.campaign_id=:campaign AND ip.segment='network' AND i.voided_at IS NULL AND i.direction='outbound' AND i.qualifies_as_contact=1 GROUP BY i.prospect_id) e ON e.prospect_id=p.id WHERE e.first_at>=:start_at AND e.first_at<:end_at ORDER BY e.first_at,p.id", $this->periodParams($id, $window), 'prospect'],
            'sales_conversations' => ["SELECT p.id,p.version,{$identity}{$base} JOIN (SELECT e.prospect_id,MIN(e.occurred_at) first_at FROM prospect_status_events e JOIN prospects ip ON ip.id=e.prospect_id WHERE ip.campaign_id=:campaign AND e.voided_at IS NULL AND e.to_status='conversation' GROUP BY e.prospect_id) e ON e.prospect_id=p.id WHERE e.first_at>=:start_at AND e.first_at<:end_at ORDER BY e.first_at,p.id", $this->periodParams($id, $window), 'prospect'],
        ];
        if (isset($queries[$metric])) {
            [$sql, $params, $type] = $queries[$metric];
            return $this->rows($sql, $params, $type);
        }
        $stages = match ($metric) {
            'qualified_opportunities' => ['qualified'],
            'offers_sent' => ['discovery_offered', 'proposal_sent'],
            'contracts_won' => ['won'],
            default => null,
        };
        if ($stages === null) return [];
        $params = $this->periodParams($id, $window);
        $placeholders = [];
        foreach ($stages as $index => $stage) { $params['stage' . $index] = $stage; $placeholders[] = ':stage' . $index; }
        return $this->rows("SELECT o.id,o.version,{$identity} FROM opportunities o JOIN prospects p ON p.id=o.prospect_id LEFT JOIN companies c ON c.id=p.company_id LEFT JOIN contacts ct ON ct.id=p.primary_contact_id JOIN (SELECT e.opportunity_id,MIN(e.occurred_at) first_at FROM opportunity_stage_events e JOIN opportunities io ON io.id=e.opportunity_id JOIN prospects ip ON ip.id=io.prospect_id WHERE ip.campaign_id=:campaign AND e.voided_at IS NULL AND e.to_stage IN (" . implode(',', $placeholders) . ") GROUP BY e.opportunity_id) e ON e.opportunity_id=o.id WHERE e.first_at>=:start_at AND e.first_at<:end_at ORDER BY e.first_at,o.id", $params, 'opportunity');
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $campaignId): array
    {
        return $this->rows("SELECT * FROM (SELECT i.occurred_at at_time,'Interaction' kind,i.summary detail,i.prospect_id FROM interactions i JOIN prospects p ON p.id=i.prospect_id WHERE p.campaign_id=:id AND i.voided_at IS NULL UNION ALL SELECT e.occurred_at,'Prospect stage',e.to_status,e.prospect_id FROM prospect_status_events e JOIN prospects p ON p.id=e.prospect_id WHERE p.campaign_id=:id AND e.voided_at IS NULL UNION ALL SELECT e.occurred_at,'Opportunity stage',e.to_stage,o.prospect_id FROM opportunity_stage_events e JOIN opportunities o ON o.id=e.opportunity_id JOIN prospects p ON p.id=o.prospect_id WHERE p.campaign_id=:id AND e.voided_at IS NULL) activity ORDER BY at_time DESC LIMIT 15", ['id' => $campaignId]);
    }

    /** @param array<string,mixed> $campaign @return array<string,mixed> */
    private function window(array $campaign, string $timezone): array
    {
        $zone = new DateTimeZone($timezone); $now = new DateTimeImmutable('now', $zone);
        $start = new DateTimeImmutable($campaign['start_date'] . ' 00:00:00', $zone);
        $end = (new DateTimeImmutable($campaign['end_date'] . ' 00:00:00', $zone))->add(new DateInterval('P1D'));
        return ['today' => $now->setTime(0, 0), 'start' => $start->setTimezone(new DateTimeZone('UTC')), 'end' => $end->setTimezone(new DateTimeZone('UTC'))];
    }

    /** @param array<string,mixed> $campaign @param array<string,mixed> $window @return array<string,mixed> */
    private function campaignContext(array $campaign, array $window): array
    {
        $start = new DateTimeImmutable($campaign['start_date']); $end = new DateTimeImmutable($campaign['end_date']);
        $today = new DateTimeImmutable($window['today']->format('Y-m-d'));
        $state = $today < $start ? 'upcoming' : ($today > $end ? 'ended' : 'active');
        return ['day_number' => $state === 'active' ? $start->diff($today)->days + 1 : null, 'state' => $state];
    }

    /** @param array<string,mixed> $window @return array<string,int> */
    private function metrics(int $campaignId, array $window): array
    {
        $period = $this->periodParams($campaignId, $window);
        return [
            'selected_prospects' => $this->count('SELECT COUNT(*) FROM prospects WHERE campaign_id=:campaign AND archived_at IS NULL', ['campaign' => $campaignId]),
            'personalized_contacts' => $this->firstEventCount('interactions i JOIN prospects p ON p.id=i.prospect_id', 'i.prospect_id', "p.campaign_id=:campaign AND i.voided_at IS NULL AND i.direction='outbound' AND i.qualifies_as_contact=1", $period),
            'partners_contacted' => $this->firstEventCount('interactions i JOIN prospects p ON p.id=i.prospect_id', 'i.prospect_id', "p.campaign_id=:campaign AND p.segment='partner' AND i.voided_at IS NULL AND i.direction='outbound' AND i.qualifies_as_contact=1", $period),
            'network_contacts' => $this->firstEventCount('interactions i JOIN prospects p ON p.id=i.prospect_id', 'i.prospect_id', "p.campaign_id=:campaign AND p.segment='network' AND i.voided_at IS NULL AND i.direction='outbound' AND i.qualifies_as_contact=1", $period),
            'prospects_responding' => $this->firstEventCount('interactions i JOIN prospects p ON p.id=i.prospect_id', 'i.prospect_id', "p.campaign_id=:campaign AND i.voided_at IS NULL AND i.direction='inbound' AND i.qualifies_as_response=1", $period),
            'sales_conversations' => $this->firstEventCount('prospect_status_events e JOIN prospects p ON p.id=e.prospect_id', 'e.prospect_id', "p.campaign_id=:campaign AND e.voided_at IS NULL AND e.to_status='conversation'", $period),
            'qualified_opportunities' => $this->firstEventCount('opportunity_stage_events e JOIN opportunities o ON o.id=e.opportunity_id JOIN prospects p ON p.id=o.prospect_id', 'e.opportunity_id', "p.campaign_id=:campaign AND e.voided_at IS NULL AND e.to_stage='qualified'", $period),
            'offers_sent' => $this->firstEventCount('opportunity_stage_events e JOIN opportunities o ON o.id=e.opportunity_id JOIN prospects p ON p.id=o.prospect_id', 'e.opportunity_id', "p.campaign_id=:campaign AND e.voided_at IS NULL AND e.to_stage IN ('discovery_offered','proposal_sent')", $period),
            'contracts_won' => $this->firstEventCount('opportunity_stage_events e JOIN opportunities o ON o.id=e.opportunity_id JOIN prospects p ON p.id=o.prospect_id', 'e.opportunity_id', "p.campaign_id=:campaign AND e.voided_at IS NULL AND e.to_stage='won'", $period),
        ];
    }

    /** @param array<string,int> $metrics @return list<array<string,mixed>> */
    private function targets(int $campaignId, array $metrics): array
    {
        $statement = $this->pdo->prepare('SELECT metric_key,target_value FROM campaign_targets WHERE campaign_id=:campaign'); $statement->execute(['campaign' => $campaignId]);
        $stored = []; foreach ($statement->fetchAll() as $row) $stored[$row['metric_key']] = (int) $row['target_value'];
        $targets = []; foreach (CampaignMetrics::definitions() as $key => $definition) $targets[] = ['key' => $key, 'label' => $definition['label'], 'target' => $stored[$key] ?? $definition['default'], 'actual' => $metrics[$key] ?? 0];
        return $targets;
    }

    /** @return list<array<string,mixed>> */
    private function funnel(int $campaignId): array { return $this->rows('SELECT status,COUNT(*) total FROM prospects WHERE campaign_id=:campaign AND archived_at IS NULL GROUP BY status ORDER BY status', ['campaign' => $campaignId]); }
    /** @return array<string,string> */
    private function pipeline(int $campaignId): array { $row = $this->one("SELECT CAST(COALESCE(SUM(CASE WHEN o.stage NOT IN ('won','lost') THEN o.value_amount ELSE 0 END),0) AS CHAR) open_value,CAST(COALESCE(SUM(CASE WHEN o.stage='won' THEN o.value_amount ELSE 0 END),0) AS CHAR) won_value FROM opportunities o JOIN prospects p ON p.id=o.prospect_id WHERE p.campaign_id=:campaign AND (p.archived_at IS NULL OR o.stage='won')", ['campaign' => $campaignId]); return ['open_value' => (string)($row['open_value'] ?? '0.00'), 'won_value' => (string)($row['won_value'] ?? '0.00')]; }

    /** @param array<string,mixed> $window @return array<string,list<array<string,mixed>>> */
    private function queues(int $campaignId, array $window): array
    {
        $base = "SELECT f.*,p.version prospect_version,c.name company_name,CONCAT_WS(' ',ct.first_name,ct.last_name) contact_name FROM follow_ups f JOIN prospects p ON p.id=f.prospect_id LEFT JOIN companies c ON c.id=p.company_id LEFT JOIN contacts ct ON ct.id=p.primary_contact_id WHERE p.campaign_id=:campaign AND p.archived_at IS NULL AND f.status='open'";
        $start = $window['today']->setTimezone(new DateTimeZone('UTC')); $tomorrow = $start->add(new DateInterval('P1D')); $nextWeek = $tomorrow->add(new DateInterval('P7D'));
        return ['overdue' => $this->rows($base.' AND f.due_at<:start ORDER BY f.due_at,f.id LIMIT 10', ['campaign'=>$campaignId,'start'=>$start->format('Y-m-d H:i:s.u')]), 'today' => $this->rows($base.' AND f.due_at>=:start AND f.due_at<:end ORDER BY f.due_at,f.id LIMIT 10', ['campaign'=>$campaignId,'start'=>$start->format('Y-m-d H:i:s.u'),'end'=>$tomorrow->format('Y-m-d H:i:s.u')]), 'next_seven' => $this->rows($base.' AND f.due_at>=:start AND f.due_at<:end ORDER BY f.due_at,f.id LIMIT 10', ['campaign'=>$campaignId,'start'=>$tomorrow->format('Y-m-d H:i:s.u'),'end'=>$nextWeek->format('Y-m-d H:i:s.u')]), 'ready' => $this->rows("SELECT p.*,c.name company_name,CONCAT_WS(' ',ct.first_name,ct.last_name) contact_name FROM prospects p LEFT JOIN companies c ON c.id=p.company_id LEFT JOIN contacts ct ON ct.id=p.primary_contact_id WHERE p.campaign_id=:campaign AND p.archived_at IS NULL AND p.status='ready_to_contact' ORDER BY p.updated_at,p.id LIMIT 10", ['campaign'=>$campaignId])];
    }

    /** @param array<string,int> $metrics @return array<string,array{numerator:int,denominator:int}> */
    private function rates(array $metrics): array { return ['contact_to_response'=>['numerator'=>$metrics['prospects_responding'],'denominator'=>$metrics['personalized_contacts']], 'response_to_conversation'=>['numerator'=>$metrics['sales_conversations'],'denominator'=>$metrics['prospects_responding']], 'contact_to_conversation'=>['numerator'=>$metrics['sales_conversations'],'denominator'=>$metrics['personalized_contacts']], 'conversation_to_qualified'=>['numerator'=>$metrics['qualified_opportunities'],'denominator'=>$metrics['sales_conversations']], 'qualified_to_offer'=>['numerator'=>$metrics['offers_sent'],'denominator'=>$metrics['qualified_opportunities']], 'offer_to_won'=>['numerator'=>$metrics['contracts_won'],'denominator'=>$metrics['offers_sent']]]; }
    /** @param array<string,mixed> $params */
    private function firstEventCount(string $from,string $entity,string $where,array $params): int { $occurredAt = str_starts_with($from, 'interactions ') ? 'i.occurred_at' : 'e.occurred_at'; return $this->count("SELECT COUNT(*) FROM (SELECT {$entity} entity_id,MIN({$occurredAt}) first_at FROM {$from} WHERE {$where} GROUP BY {$entity}) first_events WHERE first_at>=:start_at AND first_at<:end_at", $params); }
    /** @param array<string,mixed> $window @return array<string,string|int> */
    private function periodParams(int $campaignId,array $window): array { return ['campaign'=>$campaignId,'start_at'=>$window['start']->format('Y-m-d H:i:s.u'),'end_at'=>$window['end']->format('Y-m-d H:i:s.u')]; }
    /** @param array<string,mixed> $params */
    private function count(string $sql,array $params): int { $s=$this->pdo->prepare($sql);$s->execute($params);return(int)$s->fetchColumn(); }
    /** @param array<string,mixed> $params @return list<array<string,mixed>> */
    private function rows(string $sql,array $params,?string $entityType=null): array { $s=$this->pdo->prepare($sql);$s->execute($params);$rows=$s->fetchAll();if($entityType!==null)foreach($rows as &$row)$row['entity_type']=$entityType;unset($row);return $rows; }
    /** @param array<string,mixed> $params @return array<string,mixed> */
    private function one(string $sql,array $params): array { $s=$this->pdo->prepare($sql);$s->execute($params);$row=$s->fetch();return is_array($row)?$row:[]; }
}
