<?php
declare(strict_types=1);
namespace Dreamsmith\Campaign\Integration;
use DateTimeInterface;use PDO;
final class IntegrationAccessEvents{
 public function write(PDO $pdo,?int $clientId,string $environment,string $transport,string $operation,string $outcome,string $correlationId,?string $networkAddress,DateTimeInterface $at):void{$fingerprint=$networkAddress===null?null:hash('sha256',$networkAddress);$s=$pdo->prepare('INSERT INTO integration_access_events(integration_client_id,environment,transport,operation_key,outcome_code,correlation_id,network_fingerprint,occurred_at) VALUES(:client,:environment,:transport,:operation,:outcome,:correlation,:network,:occurred)');$s->execute(['client'=>$clientId,'environment'=>$environment,'transport'=>$transport,'operation'=>$operation,'outcome'=>$outcome,'correlation'=>$correlationId,'network'=>$fingerprint,'occurred'=>$at->format('Y-m-d H:i:s.u')]);}
}
