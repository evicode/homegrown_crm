<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Support;

use Throwable;

final class Logger
{
    public function __construct(private readonly string $path)
    {
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $safe = [];
        foreach ($context as $key => $value) {
            if ($value instanceof Throwable) {
                $safe[$key] = ['type' => $value::class, 'message' => $value->getMessage()];
            } elseif (!preg_match('/password|token|secret|authorization|cookie/i', $key)) {
                $safe[$key] = $value;
            }
        }
        $directory = dirname($this->path);
        if (!is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        error_log(json_encode(['time' => gmdate(DATE_ATOM), 'level' => 'error', 'message' => $message, 'context' => $safe], JSON_UNESCAPED_SLASHES) . PHP_EOL, 3, $this->path);
    }
}
