<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Records;

final class Normalizer
{
    public static function text(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim(preg_replace('/[\t ]+/', ' ', $value) ?? $value);
        return $value === '' ? null : $value;
    }

    public static function comparison(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? $value));
    }

    public static function singleLine(mixed $value): ?string
    {
        $value = self::text($value);
        if ($value === null) {
            return null;
        }
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    public static function email(mixed $value): ?string
    {
        $email = self::singleLine($value);
        return $email === null ? null : strtolower($email);
    }

    /** @return array{string,string}|null */
    public static function url(mixed $value): ?array
    {
        $url = self::text($value);
        if ($url === null) {
            return null;
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return null;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
            return null;
        }
        return [$url, preg_replace('/^www\./', '', $host) ?? $host];
    }
}
