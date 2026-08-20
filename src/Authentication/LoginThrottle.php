<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Authentication;

use DateTimeImmutable;
use PDO;

final class LoginThrottle
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly int $limit,
        private readonly int $windowSeconds,
        private readonly string $key,
    ) {
    }

    public function blocked(string $email, string $ip, DateTimeImmutable $now): bool
    {
        [$emailFingerprint, $ipFingerprint] = $this->fingerprints($email, $ip);
        $since = $now->modify('-' . $this->windowSeconds . ' seconds')->format('Y-m-d H:i:s.u');
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE succeeded = 0 AND attempted_at >= :since
             AND (email_fingerprint = :email OR ip_fingerprint = :ip)'
        );
        $statement->execute(['since' => $since, 'email' => $emailFingerprint, 'ip' => $ipFingerprint]);
        return (int) $statement->fetchColumn() >= $this->limit;
    }

    public function record(string $email, string $ip, bool $succeeded, DateTimeImmutable $now): void
    {
        [$emailFingerprint, $ipFingerprint] = $this->fingerprints($email, $ip);
        $statement = $this->pdo->prepare(
            'INSERT INTO login_attempts (email_fingerprint, ip_fingerprint, succeeded, attempted_at)
             VALUES (:email, :ip, :succeeded, :attempted_at)'
        );
        $statement->execute([
            'email' => $emailFingerprint,
            'ip' => $ipFingerprint,
            'succeeded' => $succeeded ? 1 : 0,
            'attempted_at' => $now->format('Y-m-d H:i:s.u'),
        ]);
    }

    /** @return array{string, string} */
    private function fingerprints(string $email, string $ip): array
    {
        return [hash_hmac('sha256', $email, $this->key), hash_hmac('sha256', $ip, $this->key)];
    }
}
