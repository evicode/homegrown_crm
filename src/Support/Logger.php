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
                $safe[$key] = ['type' => $value::class];
            } elseif (!preg_match('/password|token|secret|authorization|cookie|body|upload|summary|note|email|phone/i', $key)) {
                $safe[$key] = is_scalar($value) || $value === null ? $value : '[redacted non-scalar context]';
            }
        }
        $directory = dirname($this->path);
        if (!is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        try {
            error_log(json_encode(['time' => gmdate(DATE_ATOM), 'level' => 'error', 'message' => $message, 'context' => $safe], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, 3, $this->path);
        } catch (Throwable) {
            error_log('Campaign application logging failure.' . PHP_EOL);
        }
    }
}
