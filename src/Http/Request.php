<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Http;

final class Request
{
    /** @param array<string, string> $headers @param array<string, mixed> $query @param array<string, mixed> $body */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $headers = [],
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly string $rawBody = '',
        public readonly string $requestId = '',
        public readonly string $clientIp = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_') && is_string($value)) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        $rawBody = file_get_contents('php://input') ?: '';
        $body = $_POST;
        $contentType = strtolower(explode(';', $headers['content-type'] ?? '')[0]);
        if ($contentType === 'application/json' && $rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            $body = is_array($decoded) ? $decoded : [];
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path,
            $headers,
            $_GET,
            $body,
            $rawBody,
            self::requestId($headers['x-request-id'] ?? null),
            is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '',
        );
    }

    private static function requestId(?string $candidate): string
    {
        if ($candidate !== null && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $candidate) === 1) {
            return $candidate;
        }

        return bin2hex(random_bytes(16));
    }
}
