<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Presentation;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class Formatter
{
    private readonly DateTimeZone $timezone;

    public function __construct(string $ownerTimezone)
    {
        $this->timezone = new DateTimeZone($ownerTimezone);
    }

    public function date(DateTimeInterface|string|null $value, string $format = 'M j, Y g:i a'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $date = is_string($value) ? new DateTimeImmutable($value, new DateTimeZone('UTC')) : DateTimeImmutable::createFromInterface($value);
        return $date->setTimezone($this->timezone)->format($format);
    }

    public function money(int $minorUnits, string $currency = 'USD'): string
    {
        $amount = number_format($minorUnits / 100, 2, '.', ',');
        return $currency === 'USD' ? '$' . $amount : $currency . ' ' . $amount;
    }
}
