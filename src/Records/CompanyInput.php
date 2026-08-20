<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Records;

final class CompanyInput
{
    public function __construct(
        public readonly string $name,
        public readonly string $normalizedName,
        public readonly ?string $website,
        public readonly ?string $websiteDomain,
        public readonly ?string $location,
        public readonly ?string $industry,
        public readonly ?string $employeeRange,
        public readonly ?string $revenueRange,
        public readonly ?string $notes,
    ) {
    }

    /** @param array<string,mixed> $values @return array{self|null,array<string,string>} */
    public static function fromArray(array $values): array
    {
        $errors = [];
        $name = Normalizer::singleLine($values['name'] ?? null);
        if ($name === null || strlen($name) > 160) {
            $errors['name'] = 'Enter a company name no longer than 160 characters.';
        }
        $websiteValue = Normalizer::text($values['website'] ?? null);
        $website = $websiteValue === null ? null : Normalizer::url($websiteValue);
        if ($websiteValue !== null && ($website === null || strlen($websiteValue) > 2048)) {
            $errors['website'] = 'Enter a valid HTTP or HTTPS website URL.';
        }
        $fields = [];
        foreach (['location' => 160, 'industry' => 160, 'employee_range' => 80, 'revenue_range' => 80, 'notes' => 10000] as $key => $limit) {
            $fields[$key] = $key === 'notes' ? Normalizer::text($values[$key] ?? null) : Normalizer::singleLine($values[$key] ?? null);
            if ($fields[$key] !== null && strlen($fields[$key]) > $limit) {
                $errors[$key] = ucfirst(str_replace('_', ' ', $key)) . " must not exceed {$limit} characters.";
            }
        }
        if ($errors !== []) {
            return [null, $errors];
        }
        return [new self($name, Normalizer::comparison($name), $website[0] ?? null, $website[1] ?? null, $fields['location'], $fields['industry'], $fields['employee_range'], $fields['revenue_range'], $fields['notes']), []];
    }
}
