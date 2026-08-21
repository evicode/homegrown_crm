<?php
declare(strict_types=1);
namespace Dreamsmith\Campaign\Integration;
use DateTimeImmutable;use PDO;
final class FixedWindowRateLimiter{
 /** @return array{allowed:bool,retry_after:int} */ public function consume(PDO $pdo,string $policy,string $identity,int $limit,int $windowSeconds,DateTimeImmutable $now):array{$start=$now->getTimestamp()-($now->getTimestamp()%$windowSeconds);$window=(new DateTimeImmutable('@'.$start))->format('Y-m-d H:i:s');$expires=(new DateTimeImmutable('@'.($start+$windowSeconds)))->format('Y-m-d H:i:s');$hash=hash('sha256',$identity);$pdo->prepare('INSERT INTO rate_limit_buckets(policy_key,identity_hash,window_started_at,request_count,expires_at) VALUES(:policy,:identity,:start,1,:expires) ON DUPLICATE KEY UPDATE request_count=request_count+1')->execute(['policy'=>$policy,'identity'=>$hash,'start'=>$window,'expires'=>$expires]);$s=$pdo->prepare('SELECT request_count FROM rate_limit_buckets WHERE policy_key=:policy AND identity_hash=:identity AND window_started_at=:start');$s->execute(['policy'=>$policy,'identity'=>$hash,'start'=>$window]);$count=(int)$s->fetchColumn();return['allowed'=>$count<=$limit,'retry_after'=>max(1,$start+$windowSeconds-$now->getTimestamp())];}
}
