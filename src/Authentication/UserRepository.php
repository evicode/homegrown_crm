<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Authentication;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        return is_array($user) ? $user : null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();
        return is_array($user) ? $user : null;
    }

    public function recordLogin(int $id, ?string $replacementHash = null): void
    {
        $sql = 'UPDATE users SET last_login_at = UTC_TIMESTAMP(6)';
        $parameters = ['id' => $id];
        if ($replacementHash !== null) {
            $sql .= ', password_hash = :password_hash';
            $parameters['password_hash'] = $replacementHash;
        }
        $statement = $this->pdo->prepare($sql . ' WHERE id = :id');
        $statement->execute($parameters);
    }

    public function changePassword(int $id, string $hash): int
    {
        $statement = $this->pdo->prepare(
            'UPDATE users SET password_hash = :hash, password_changed_at = UTC_TIMESTAMP(6), session_version = session_version + 1 WHERE id = :id'
        );
        $statement->execute(['hash' => $hash, 'id' => $id]);
        $version = $this->pdo->prepare('SELECT session_version FROM users WHERE id = :id');
        $version->execute(['id' => $id]);
        return (int) $version->fetchColumn();
    }
}
