<?php
declare(strict_types=1);
namespace Dreamsmith\Campaign\Integration;
use Dreamsmith\Campaign\Application\Audit\ActorContext;use Dreamsmith\Campaign\Persistence\Database;
final class LocalTokenAuthenticator{
 public function __construct(private readonly Database $database,private readonly array $config){}
 public function authenticate(string $token,string $correlationId):ActorContext{if(!(bool)$this->config['local_tokens_enabled'])throw new \DomainException('External access is disabled.');if(!preg_match('/^crm_[A-Za-z0-9_-]{40,}$/',$token))throw new \DomainException('Invalid integration token.');$row=(new IntegrationRepository($this->database->pdo()))->clientForToken(hash('sha256',$token),(string)$this->config['environment']);if($row===null||$row['revoked_at']!==null||$row['token_revoked_at']!==null||strtotime((string)$row['expires_at'])<=time())throw new \DomainException('Invalid integration token.');$scopes=ScopeSet::validated(json_decode((string)$row['maximum_scopes_json'],true,flags:JSON_THROW_ON_ERROR),(array)$this->config['scopes']);return new ActorContext('integration',(int)$row['owner_user_id'],(int)$row['id'],null,'local-token',$correlationId,$scopes->all(),(string)$row['environment'],'machine');}
}
