<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Records;

final class ContactInput
{
    public function __construct(
        public readonly ?int $companyId,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly string $normalizedName,
        public readonly ?string $role,
        public readonly ?string $email,
        public readonly ?string $emailNormalized,
        public readonly ?string $phone,
        public readonly ?string $linkedinUrl,
    ) {
    }

    /** @param array<string,mixed> $values @return array{self|null,array<string,string>} */
    public static function fromArray(array $values): array
    {
        $errors = [];
        $first = Normalizer::singleLine($values['first_name'] ?? null);
        $last = Normalizer::singleLine($values['last_name'] ?? null);
        if ($first === null && $last === null) {
            $errors['first_name'] = 'Enter at least a first or last name.';
        }
        foreach (['first_name' => [$first, 100], 'last_name' => [$last, 100]] as $key => [$value, $limit]) {
            if ($value !== null && strlen($value) > $limit) {
                $errors[$key] = ucfirst(str_replace('_', ' ', $key)) . " must not exceed {$limit} characters.";
            }
        }
        $companyRaw = $values['company_id'] ?? null;
        $companyId = $companyRaw === null || $companyRaw === '' ? null : filter_var($companyRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($companyRaw !== null && $companyRaw !== '' && $companyId === false) {
            $errors['company_id'] = 'Select a valid company.';
        }
        $role = Normalizer::singleLine($values['role'] ?? null);
        $phone = Normalizer::singleLine($values['phone'] ?? null);
        $email = Normalizer::singleLine($values['email'] ?? null);
        $emailNormalized = $email === null ? null : strtolower($email);
        if ($email !== null && (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($role !== null && strlen($role) > 160) {
            $errors['role'] = 'Role must not exceed 160 characters.';
        }
        if ($phone !== null && (strlen($phone) > 50 || preg_match('/^[0-9+().\- xext]+$/i', $phone) !== 1)) {
            $errors['phone'] = 'Enter a valid phone number.';
        }
        $linkedInValue = Normalizer::text($values['linkedin_url'] ?? null);
        $linkedIn = $linkedInValue === null ? null : Normalizer::url($linkedInValue);
        $linkedInHost = $linkedIn[1] ?? null;
        if ($linkedInValue !== null && ($linkedIn === null || strlen($linkedInValue) > 2048 || ($linkedInHost !== 'linkedin.com' && !str_ends_with((string) $linkedInHost, '.linkedin.com')))) {
            $errors['linkedin_url'] = 'Enter a valid HTTP or HTTPS LinkedIn URL.';
        }
        if ($errors !== []) {
            return [null, $errors];
        }
        return [new self($companyId === false ? null : $companyId, $first, $last, Normalizer::comparison(trim(($first ?? '') . ' ' . ($last ?? ''))), $role, $email, $emailNormalized, $phone, $linkedIn[0] ?? null), []];
    }
}
