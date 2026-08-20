<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Security;

final class Csrf
{
    private const KEY = '_csrf_token';

    public function __construct(private readonly SessionManager $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }
        return $token;
    }

    public function verify(mixed $candidate): bool
    {
        return is_string($candidate) && hash_equals($this->token(), $candidate);
    }

    public function rotate(): void
    {
        $this->session->remove(self::KEY);
        $this->token();
    }
}
