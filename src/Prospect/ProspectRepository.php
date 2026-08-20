<?php
declare(strict_types=1);
namespace Dreamsmith\Campaign\Prospect;
use PDO;
final class ProspectRepository{
 public function __construct(private readonly PDO $pdo){}
 /** @return list<array<string,mixed>> */ public function all(int $campaignId,bool $archived=false):array{$s=$this->pdo->prepare("SELECT p.*,c.name company_name,ct.first_name,ct.last_name FROM prospects p LEFT JOIN companies c ON c.id=p.company_id LEFT JOIN contacts ct ON ct.id=p.primary_contact_id WHERE p.campaign_id=:campaign AND p.archived_at IS ".($archived?'NOT NULL':'NULL')." ORDER BY p.updated_at DESC,p.id DESC");$s->execute(['campaign'=>$campaignId]);return $s->fetchAll();}
 /** @return array<string,mixed>|null */ public function find(int $id,bool $lock=false):?array{$s=$this->pdo->prepare('SELECT p.*,c.name company_name,ct.first_name,ct.last_name,ct.company_id contact_company_id FROM prospects p LEFT JOIN companies c ON c.id=p.company_id LEFT JOIN contacts ct ON ct.id=p.primary_contact_id WHERE p.id=:id'.($lock?' FOR UPDATE':''));$s->execute(['id'=>$id]);$r=$s->fetch();return is_array($r)?$r:null;}
 /** @return list<array<string,mixed>> */ public function signals(int $id,bool $lock=false):array{$s=$this->pdo->prepare('SELECT ps.*,s.signal_key,s.name FROM prospect_signals ps JOIN signals s ON s.id=ps.signal_id WHERE ps.prospect_id=:id ORDER BY ps.observed_on DESC,ps.id DESC'.($lock?' FOR UPDATE':''));$s->execute(['id'=>$id]);return $s->fetchAll();}
 /** @return list<array<string,mixed>> */ public function events(int $id):array{$s=$this->pdo->prepare('SELECT * FROM prospect_status_events WHERE prospect_id=:id ORDER BY occurred_at DESC,id DESC');$s->execute(['id'=>$id]);return $s->fetchAll();}
 /** @return list<array<string,mixed>> */ public function signalTypes():array{return $this->pdo->query('SELECT * FROM signals ORDER BY id')->fetchAll();}
}
