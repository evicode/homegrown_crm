<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Http;

final class Route
{
    /** @param callable(Request, array<string, string>): Response $handler */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly mixed $handler,
        public readonly string $name,
    ) {
    }
}
