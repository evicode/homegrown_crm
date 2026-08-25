<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Search;

use PDO;

final class SearchRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array{companies:list<array<string,mixed>>,contacts:list<array<string,mixed>>,prospects:list<array<string,mixed>>,opportunities:list<array<string,mixed>>,follow_ups:list<array<string,mixed>>,interactions:list<array<string,mixed>>} */
    public function search(string $query): array
    {
        $params = ['q' => '%' . $query . '%'];
        return [
            'companies' => $this->rows('SELECT id,name,website,location FROM companies WHERE archived_at IS NULL AND (name LIKE :q OR website LIKE :q OR location LIKE :q OR industry LIKE :q) ORDER BY name,id LIMIT 10', $params),
            'contacts' => $this->rows('SELECT ct.id,ct.first_name,ct.last_name,ct.role,ct.email,c.name company_name FROM contacts ct LEFT JOIN companies c ON c.id=ct.company_id WHERE ct.archived_at IS NULL AND (ct.first_name LIKE :q OR ct.last_name LIKE :q OR ct.email LIKE :q OR ct.role LIKE :q OR c.name LIKE :q) ORDER BY ct.last_name,ct.first_name,ct.id LIMIT 10', $params),
            'prospects' => $this->rows('SELECT p.id,p.status,c.name company_name,CONCAT_WS(" ",ct.first_name,ct.last_name) contact_name,p.why_them FROM prospects p LEFT JOIN companies c ON c.id=p.company_id LEFT JOIN contacts ct ON ct.id=p.primary_contact_id WHERE p.archived_at IS NULL AND (c.name LIKE :q OR CONCAT_WS(" ",ct.first_name,ct.last_name) LIKE :q OR p.source LIKE :q OR p.why_them LIKE :q OR p.business_problem LIKE :q) ORDER BY p.updated_at DESC,p.id DESC LIMIT 10', $params),
            'opportunities' => $this->rows('SELECT o.id,o.stage,o.offer_key,o.value_amount,c.name company_name,CONCAT_WS(" ",ct.first_name,ct.last_name) contact_name FROM opportunities o JOIN prospects p ON p.id=o.prospect_id LEFT JOIN companies c ON c.id=p.company_id LEFT JOIN contacts ct ON ct.id=p.primary_contact_id WHERE p.archived_at IS NULL AND (c.name LIKE :q OR CONCAT_WS(" ",ct.first_name,ct.last_name) LIKE :q OR o.offer_key LIKE :q OR o.stage LIKE :q OR o.lost_reason LIKE :q) ORDER BY o.updated_at DESC,o.id DESC LIMIT 10', $params),
            'follow_ups' => $this->rows('SELECT f.id,f.prospect_id,f.status,f.action,f.due_at,c.name company_name,CONCAT_WS(" ",ct.first_name,ct.last_name) contact_name FROM follow_ups f JOIN prospects p ON p.id=f.prospect_id LEFT JOIN companies c ON c.id=p.company_id LEFT JOIN contacts ct ON ct.id=p.primary_contact_id WHERE p.archived_at IS NULL AND (f.action LIKE :q OR f.cancellation_reason LIKE :q OR c.name LIKE :q OR CONCAT_WS(" ",ct.first_name,ct.last_name) LIKE :q) ORDER BY f.updated_at DESC,f.id DESC LIMIT 10', $params),
            'interactions' => $this->rows('SELECT i.id,i.prospect_id,i.type,i.outcome,i.summary,i.occurred_at,c.name company_name,CONCAT_WS(" ",ct.first_name,ct.last_name) contact_name FROM interactions i JOIN prospects p ON p.id=i.prospect_id LEFT JOIN companies c ON c.id=p.company_id LEFT JOIN contacts ct ON ct.id=COALESCE(i.contact_id,p.primary_contact_id) WHERE p.archived_at IS NULL AND (i.summary LIKE :q OR i.type LIKE :q OR i.outcome LIKE :q OR c.name LIKE :q OR CONCAT_WS(" ",ct.first_name,ct.last_name) LIKE :q) ORDER BY i.occurred_at DESC,i.id DESC LIMIT 10', $params),
        ];
    }

    /** @param array<string,string> $params @return list<array<string,mixed>> */
    private function rows(string $sql, array $params): array
    {
        $statement = $this->pdo->prepare($sql); $statement->execute($params); return $statement->fetchAll();
    }
}
