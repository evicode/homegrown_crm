<?php
declare(strict_types=1);
namespace Dreamsmith\Campaign\Integration;
use PDO;
final class IntegrationRepository{
 public function __construct(private readonly PDO $pdo){}
 public function activeForOwner(int $owner):array{$s=$this->pdo->prepare('SELECT c.*,t.token_prefix,t.expires_at,t.revoked_at token_revoked_at FROM integration_clients c LEFT JOIN integration_tokens t ON t.integration_client_id=c.id WHERE c.owner_user_id=:owner ORDER BY c.revoked_at IS NOT NULL,c.created_at DESC,c.id DESC');$s->execute(['owner'=>$owner]);return $s->fetchAll();}
 public function clientForToken(string $hash,string $environment):?array{$s=$this->pdo->prepare('SELECT c.*,t.id token_id,t.expires_at,t.revoked_at token_revoked_at FROM integration_tokens t JOIN integration_clients c ON c.id=t.integration_client_id WHERE t.token_hash=:hash AND c.environment=:environment');$s->execute(['hash'=>$hash,'environment'=>$environment]);$row=$s->fetch();return is_array($row)?$row:null;}
 public function client(int $id,bool $lock=false):?array{$s=$this->pdo->prepare('SELECT * FROM integration_clients WHERE id=:id'.($lock?' FOR UPDATE':''));$s->execute(['id'=>$id]);$row=$s->fetch();return is_array($row)?$row:null;}
}
