<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\FollowUp;

use PDO;

final class FollowUpRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return list<array<string,mixed>> */
    public function history(string $status, string $text, string $sort, string $direction, int $limit, int $offset): array
    {
        [$where, $params] = $this->filters($status, $text);
        $columns = ['due' => 'f.due_at', 'status' => 'f.status', 'updated' => 'f.updated_at'];
        $column = $columns[$sort] ?? $columns['due']; $order = $direction === 'asc' ? 'ASC' : 'DESC';
        $statement = $this->pdo->prepare('SELECT f.*,c.name company_name,CONCAT_WS(" ",ct.first_name,ct.last_name) contact_name FROM follow_ups f JOIN prospects p ON p.id=f.prospect_id LEFT JOIN companies c ON c.id=p.company_id LEFT JOIN contacts ct ON ct.id=p.primary_contact_id WHERE ' . $where . ' ORDER BY ' . $column . ' ' . $order . ', f.id ' . $order . ' LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) $statement->bindValue(':' . $key, $value);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT); $statement->bindValue(':offset', $offset, PDO::PARAM_INT); $statement->execute();
        return $statement->fetchAll();
    }

    public function count(string $status, string $text): int
    {
        [$where, $params] = $this->filters($status, $text);
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM follow_ups f JOIN prospects p ON p.id=f.prospect_id LEFT JOIN companies c ON c.id=p.company_id LEFT JOIN contacts ct ON ct.id=p.primary_contact_id WHERE ' . $where);
        $statement->execute($params);
        return (int) $statement->fetchColumn();
    }

    /** @return array{0:string,1:array<string,string>} */
    private function filters(string $status, string $text): array
    {
        $where = '1=1'; $params = [];
        if ($status !== '') { $where .= ' AND f.status = :status'; $params['status'] = $status; }
        if ($text !== '') { $where .= ' AND (f.action LIKE :q OR f.cancellation_reason LIKE :q OR c.name LIKE :q OR CONCAT_WS(" ",ct.first_name,ct.last_name) LIKE :q)'; $params['q'] = '%' . $text . '%'; }
        return [$where, $params];
    }
}
