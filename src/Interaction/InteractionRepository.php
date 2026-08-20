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
    /** @return array<string,mixed>|null */
    public function find(int $id,bool $lock=false): ?array {$s=$this->pdo->prepare('SELECT * FROM interactions WHERE id=:id'.($lock?' FOR UPDATE':''));$s->execute(['id'=>$id]);$r=$s->fetch();return is_array($r)?$r:null;}
}
