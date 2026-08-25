<?php
declare(strict_types=1);
namespace Dreamsmith\Campaign\Interaction;
use PDO;
final class InteractionRepository {
    public function __construct(private readonly PDO $pdo) {}
    /** @return list<array<string,mixed>> */
    public function forProspect(int $prospectId): array {
        $s=$this->pdo->prepare('SELECT i.*, CONCAT_WS(" ",c.first_name,c.last_name) contact_name FROM interactions i LEFT JOIN contacts c ON c.id=i.contact_id WHERE i.prospect_id=:id ORDER BY i.voided_at IS NOT NULL,i.occurred_at DESC,i.id DESC');$s->execute(['id'=>$prospectId]);return $s->fetchAll();
    }
    /** @return list<array<string,mixed>> */
    public function history(string $text = '', string $sort = 'occurred', string $direction = 'desc', int $limit = 25, int $offset = 0): array {
        $where = '1=1'; $params = [];
        if ($text !== '') { $where .= ' AND (c.name LIKE :q OR CONCAT_WS(" ",ct.first_name,ct.last_name) LIKE :q OR i.summary LIKE :q OR i.outcome LIKE :q OR i.type LIKE :q)'; $params['q'] = '%' . $text . '%'; }
        $columns = ['occurred' => 'i.occurred_at', 'type' => 'i.type', 'outcome' => 'i.outcome']; $column = $columns[$sort] ?? $columns['occurred']; $order = $direction === 'asc' ? 'ASC' : 'DESC';
        $s = $this->pdo->prepare('SELECT i.*,p.status prospect_status,c.name company_name,CONCAT_WS(" ",ct.first_name,ct.last_name) contact_name FROM interactions i JOIN prospects p ON p.id=i.prospect_id LEFT JOIN companies c ON c.id=p.company_id LEFT JOIN contacts ct ON ct.id=COALESCE(i.contact_id,p.primary_contact_id) WHERE ' . $where . ' ORDER BY ' . $column . ' ' . $order . ', i.id ' . $order . ' LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) $s->bindValue(':' . $key, $value); $s->bindValue(':limit', $limit, PDO::PARAM_INT); $s->bindValue(':offset', $offset, PDO::PARAM_INT); $s->execute(); return $s->fetchAll();
    }
    public function count(string $text = ''): int {
        $where = '1=1'; $params = [];
        if ($text !== '') { $where .= ' AND (c.name LIKE :q OR CONCAT_WS(" ",ct.first_name,ct.last_name) LIKE :q OR i.summary LIKE :q OR i.outcome LIKE :q OR i.type LIKE :q)'; $params['q'] = '%' . $text . '%'; }
        $s = $this->pdo->prepare('SELECT COUNT(*) FROM interactions i JOIN prospects p ON p.id=i.prospect_id LEFT JOIN companies c ON c.id=p.company_id LEFT JOIN contacts ct ON ct.id=COALESCE(i.contact_id,p.primary_contact_id) WHERE ' . $where); $s->execute($params); return (int) $s->fetchColumn();
    }
    /** @return array<string,mixed>|null */
    public function find(int $id,bool $lock=false): ?array {$s=$this->pdo->prepare('SELECT * FROM interactions WHERE id=:id'.($lock?' FOR UPDATE':''));$s->execute(['id'=>$id]);$r=$s->fetch();return is_array($r)?$r:null;}
}
