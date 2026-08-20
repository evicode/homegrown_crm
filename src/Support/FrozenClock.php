<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Support;

use DateTimeImmutable;

final class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $current)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->current;
    }

    public function set(DateTimeImmutable $current): void
    {
        $this->current = $current;
    }
}
