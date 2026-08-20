<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Security;

final class SessionManager
{
    private bool $started = false;

    public function __construct(
        private readonly string $name,
        private readonly string $path,
        private readonly bool $secure,
    ) {
    }

    public function start(): void
    {
        if ($this->started) {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name($this->name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $this->path === '' ? '/' : $this->path,
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if (!session_start()) {
            throw new \RuntimeException('Unable to start session.');
        }
        $this->started = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function rotate(): void
    {
        $this->start();
        if (!session_regenerate_id(true)) {
            throw new \RuntimeException('Unable to rotate session.');
        }
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'], $parameters['secure'], $parameters['httponly']);
        }
        session_destroy();
        $this->started = false;
    }
}
