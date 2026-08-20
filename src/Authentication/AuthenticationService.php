<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Authentication;

use Dreamsmith\Campaign\Application\Audit\ActorContext;
use Dreamsmith\Campaign\Application\Audit\AuditWriter;
use Dreamsmith\Campaign\Persistence\Database;
use Dreamsmith\Campaign\Security\Csrf;
use Dreamsmith\Campaign\Security\SessionManager;
use Dreamsmith\Campaign\Support\Clock;

final class AuthenticationService
{
    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function __construct(
        private readonly Database $database,
        private readonly SessionManager $session,
        private readonly Csrf $csrf,
        private readonly Clock $clock,
        private readonly int $idleSeconds,
        private readonly int $absoluteSeconds,
        private readonly int $attemptLimit,
        private readonly int $attemptWindow,
        private readonly string $fingerprintKey,
        private readonly AuditWriter $audit,
    ) {
    }

    public static function normalizeEmail(string $email): ?string
    {
        $email = strtolower(trim($email));
        return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    public function login(string $emailInput, string $password, string $ip, string $correlationId): string
    {
        $email = self::normalizeEmail($emailInput) ?? strtolower(trim(substr($emailInput, 0, 254)));
        $now = $this->clock->now();
        $pdo = $this->database->pdo();
        $users = new UserRepository($pdo);
        $throttle = new LoginThrottle($pdo, $this->attemptLimit, $this->attemptWindow, $this->fingerprintKey);

        if ($throttle->blocked($email, $ip, $now)) {
            $this->audit->write($pdo, new ActorContext('anonymous', correlationId: $correlationId), 'authentication.throttled', null, null, [], $now);
            return 'throttled';
        }

        $user = self::normalizeEmail($emailInput) === null ? null : $users->findByEmail($email);
        $valid = password_verify($password, is_array($user) ? (string) $user['password_hash'] : self::DUMMY_HASH);
        if (!$valid || $user === null || $user['disabled_at'] !== null) {
            $throttle->record($email, $ip, false, $now);
            if ($user !== null && $user['disabled_at'] !== null) {
                $this->audit->write($pdo, new ActorContext('anonymous', correlationId: $correlationId), 'authentication.disabled_attempt', 'user', (int) $user['id'], [], $now);
            }
            return 'invalid';
        }

        $replacement = password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)
            ? password_hash($password, PASSWORD_DEFAULT)
            : null;
        $users->recordLogin((int) $user['id'], $replacement);
        $throttle->record($email, $ip, true, $now);
        $this->session->rotate();
        $this->session->set('auth', [
            'user_id' => (int) $user['id'],
            'session_version' => (int) $user['session_version'],
            'authenticated_at' => $now->getTimestamp(),
            'last_activity_at' => $now->getTimestamp(),
        ]);
        $this->csrf->rotate();
        $this->audit->write($pdo, new ActorContext('owner', ownerUserId: (int) $user['id'], correlationId: $correlationId), 'authentication.login', 'user', (int) $user['id'], [], $now);
        return 'authenticated';
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $auth = $this->session->get('auth');
        if (!is_array($auth)) {
            return null;
        }
        $now = $this->clock->now()->getTimestamp();
        if ($now - (int) ($auth['last_activity_at'] ?? 0) > $this->idleSeconds
            || $now - (int) ($auth['authenticated_at'] ?? 0) > $this->absoluteSeconds) {
            $this->session->destroy();
            return null;
        }
        $user = (new UserRepository($this->database->pdo()))->findById((int) ($auth['user_id'] ?? 0));
        if ($user === null || $user['disabled_at'] !== null || (int) $user['session_version'] !== (int) ($auth['session_version'] ?? 0)) {
            $this->session->destroy();
            return null;
        }
        $auth['last_activity_at'] = $now;
        $this->session->set('auth', $auth);
        return $user;
    }

    public function logout(int $userId, string $correlationId): void
    {
        $this->audit->write($this->database->pdo(), new ActorContext('owner', ownerUserId: $userId, correlationId: $correlationId), 'authentication.logout', 'user', $userId, [], $this->clock->now());
        $this->session->destroy();
    }

    public function changePassword(array $user, string $current, string $new, string $confirmation, string $correlationId): ?string
    {
        if (!password_verify($current, (string) $user['password_hash'])) {
            return 'The current password is incorrect.';
        }
        if (strlen($new) < 12) {
            return 'The new password must contain at least 12 characters.';
        }
        if (!hash_equals($new, $confirmation)) {
            return 'The new password confirmation does not match.';
        }
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $version = (new UserRepository($this->database->pdo()))->changePassword((int) $user['id'], $hash);
        $this->session->rotate();
        $now = $this->clock->now();
        $this->session->set('auth', [
            'user_id' => (int) $user['id'],
            'session_version' => $version,
            'authenticated_at' => $now->getTimestamp(),
            'last_activity_at' => $now->getTimestamp(),
        ]);
        $this->csrf->rotate();
        $this->audit->write($this->database->pdo(), new ActorContext('owner', ownerUserId: (int) $user['id'], correlationId: $correlationId), 'authentication.password_changed', 'user', (int) $user['id'], [], $now);
        return null;
    }
}
