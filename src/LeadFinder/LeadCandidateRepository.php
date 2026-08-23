<?php
declare(strict_types=1);
namespace Dreamsmith\Campaign\LeadFinder;
use PDO;
final class LeadCandidateRepository {
 public function __construct(private readonly PDO $pdo) {}
 /** @return list<array<string,mixed>> */ public function all(string $status='pending'):array{$s=$this->pdo->prepare('SELECT * FROM lead_candidates WHERE status=:status ORDER BY score DESC,created_at DESC,id DESC');$s->execute(['status'=>$status]);return $s->fetchAll();}
 public function find(int $id):?array{$s=$this->pdo->prepare('SELECT * FROM lead_candidates WHERE id=:id');$s->execute(['id'=>$id]);$row=$s->fetch();return is_array($row)?$row:null;}
 public function upsert(array $v):int{$s=$this->pdo->prepare("INSERT INTO lead_candidates(source,source_id,search_query,name,website,address,category,business_status,fit,score,evidence_json) VALUES(:source,:source_id,:query,:name,:website,:address,:category,:business_status,:fit,:score,:evidence) ON DUPLICATE KEY UPDATE search_query=VALUES(search_query),name=VALUES(name),website=VALUES(website),address=VALUES(address),category=VALUES(category),business_status=VALUES(business_status),fit=VALUES(fit),score=VALUES(score),evidence_json=VALUES(evidence_json),status='pending',reviewed_at=NULL,reviewed_by_user_id=NULL,version=version+1");$s->execute($v);$id=(int)$this->pdo->lastInsertId();if($id>0)return $id;$q=$this->pdo->prepare('SELECT id FROM lead_candidates WHERE source=:source AND source_id=:source_id');$q->execute(['source'=>$v['source'],'source_id'=>$v['source_id']]);return(int)$q->fetchColumn();}
 public function review(int $id,string $status,int $user):bool{$s=$this->pdo->prepare('UPDATE lead_candidates SET status=:status,reviewed_at=UTC_TIMESTAMP(6),reviewed_by_user_id=:user,version=version+1 WHERE id=:id AND status=\'pending\'');$s->execute(['status'=>$status,'user'=>$user,'id'=>$id]);return $s->rowCount()===1;}
}
