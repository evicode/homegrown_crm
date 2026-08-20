<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Campaign;

use DateTimeImmutable;

final class CampaignInput
{
    /** @param array<string, int> $targets */
    public function __construct(
        public readonly string $name,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly array $targets,
    ) {
    }

    /** @param array<string, mixed> $values @return array{self|null,array<string,string>} */
    public static function fromArray(array $values): array
    {
        $errors = [];
        $name = trim(is_string($values['name'] ?? null) ? $values['name'] : '');
        $nameLength = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        if ($name === '' || $nameLength > 120) {
            $errors['name'] = 'Enter a campaign name no longer than 120 characters.';
        }
        $start = self::date(is_string($values['start_date'] ?? null) ? $values['start_date'] : '');
        $end = self::date(is_string($values['end_date'] ?? null) ? $values['end_date'] : '');
        if ($start === null) {
            $errors['start_date'] = 'Enter a valid start date.';
        }
        if ($end === null) {
            $errors['end_date'] = 'Enter a valid end date.';
        }
        if ($start !== null && $end !== null && $end < $start) {
            $errors['end_date'] = 'The end date must be on or after the start date.';
        }
        $submittedTargets = is_array($values['targets'] ?? null) ? $values['targets'] : [];
        $targets = [];
        foreach (CampaignMetrics::definitions() as $key => $definition) {
            $value = $submittedTargets[$key] ?? null;
            $validInteger = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($validInteger === false) {
                $errors['target_' . $key] = $definition['label'] . ' must be a nonnegative whole number.';
            } else {
                $targets[$key] = (int) $validInteger;
            }
        }
        return [$errors === [] ? new self($name, $start?->format('Y-m-d') ?? '', $end?->format('Y-m-d') ?? '', $targets) : null, $errors];
    }

    private static function date(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) ? $date : null;
    }
}
