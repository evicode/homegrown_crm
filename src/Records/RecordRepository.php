<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Records;

use PDO;

final class RecordRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function companies(bool $archived = false, string $text = '', string $sort = 'name', string $direction = 'asc', int $limit = 25, int $offset = 0): array
    {
        $where = 'c.archived_at IS ' . ($archived ? 'NOT NULL' : 'NULL');
        $params = [];
        if ($text !== '') {
            $where .= ' AND (c.name LIKE :q OR c.website LIKE :q OR c.location LIKE :q OR c.industry LIKE :q OR c.notes LIKE :q)';
            $params['q'] = '%' . $text . '%';
        }
        $columns = ['name' => 'c.name', 'updated' => 'c.updated_at', 'location' => 'c.location', 'industry' => 'c.industry'];
        $column = $columns[$sort] ?? $columns['name'];
        $order = $direction === 'desc' ? 'DESC' : 'ASC';
        $statement = $this->pdo->prepare(
            'SELECT c.*, COUNT(ct.id) AS contact_count FROM companies c
             LEFT JOIN contacts ct ON ct.company_id = c.id AND ct.archived_at IS NULL
             WHERE ' . $where . '
             GROUP BY c.id ORDER BY ' . $column . ' ' . $order . ', c.id ' . $order . ' LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) $statement->bindValue(':' . $key, $value);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function companyCount(bool $archived = false, string $text = ''): int
    {
        $where = 'archived_at IS ' . ($archived ? 'NOT NULL' : 'NULL');
        $params = [];
        if ($text !== '') {
            $where .= ' AND (name LIKE :q OR website LIKE :q OR location LIKE :q OR industry LIKE :q OR notes LIKE :q)';
            $params['q'] = '%' . $text . '%';
        }
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM companies WHERE ' . $where);
        $statement->execute($params);
        return (int) $statement->fetchColumn();
    }

    /** @return list<array<string,mixed>> */
    public function activeCompanies(): array
    {
        return $this->pdo->query('SELECT id, name FROM companies WHERE archived_at IS NULL ORDER BY name, id')->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function activeContacts(): array
    {
        return $this->pdo->query("SELECT ct.id,ct.company_id,ct.first_name,ct.last_name,c.name company_name FROM contacts ct LEFT JOIN companies c ON c.id=ct.company_id WHERE ct.archived_at IS NULL AND (ct.company_id IS NULL OR c.archived_at IS NULL) ORDER BY ct.last_name,ct.first_name,ct.id")->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function contacts(string $text = '', int $limit = 25, int $offset = 0): array
    {
        $where = 'ct.archived_at IS NULL'; $params = [];
        if ($text !== '') { $where .= " AND (ct.first_name LIKE :q OR ct.last_name LIKE :q OR ct.email LIKE :q OR ct.role LIKE :q OR c.name LIKE :q)"; $params['q'] = '%' . $text . '%'; }
        $statement = $this->pdo->prepare('SELECT ct.*,c.name company_name FROM contacts ct LEFT JOIN companies c ON c.id=ct.company_id WHERE ' . $where . ' ORDER BY ct.last_name,ct.first_name,ct.id LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) $statement->bindValue(':' . $key, $value);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT); $statement->bindValue(':offset', $offset, PDO::PARAM_INT); $statement->execute();
        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function company(int $id, bool $lock = false): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM companies WHERE id = :id' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function companyContacts(int $companyId, bool $includeArchived = true): array
    {
        $sql = 'SELECT * FROM contacts WHERE company_id = :company_id' . ($includeArchived ? '' : ' AND archived_at IS NULL') . ' ORDER BY archived_at IS NOT NULL, last_name, first_name, id';
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function contact(int $id, bool $lock = false): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT ct.*, c.name AS company_name, c.archived_at AS company_archived_at FROM contacts ct
             LEFT JOIN companies c ON c.id = ct.company_id WHERE ct.id = :id' . ($lock ? ' FOR UPDATE' : '')
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return list<string> */
    public function companyDuplicateLabels(CompanyInput $input, ?int $excludeId = null): array
    {
        $sql = 'SELECT name, website FROM companies WHERE archived_at IS NULL AND (normalized_name = :name';
        $params = ['name' => $input->normalizedName];
        if ($input->websiteDomain !== null) {
            $sql .= ' OR website_domain = :domain';
            $params['domain'] = $input->websiteDomain;
        }
        $sql .= ')';
        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $statement = $this->pdo->prepare($sql . ' ORDER BY name LIMIT 10');
        $statement->execute($params);
        return array_map(static fn (array $row): string => $row['name'] . ($row['website'] ? ' — ' . $row['website'] : ''), $statement->fetchAll());
    }

    /** @return list<string> */
    public function contactDuplicateLabels(ContactInput $input, ?int $excludeId = null): array
    {
        $conditions = ['(normalized_name = :name AND company_id <=> :company_id)'];
        $params = ['name' => $input->normalizedName, 'company_id' => $input->companyId];
        if ($input->email !== null) {
            $conditions[] = 'email_normalized = :email';
            $params['email'] = $input->emailNormalized;
        }
        $sql = 'SELECT first_name, last_name, email FROM contacts WHERE archived_at IS NULL AND (' . implode(' OR ', $conditions) . ')';
        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $statement = $this->pdo->prepare($sql . ' ORDER BY last_name, first_name LIMIT 10');
        $statement->execute($params);
        return array_map(static fn (array $row): string => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) . ($row['email'] ? ' — ' . $row['email'] : ''), $statement->fetchAll());
    }
}
