<?php

declare(strict_types=1);

$detectedBasePath = '';
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_NAME']) && is_string($_SERVER['SCRIPT_NAME'])) {
    $detectedBasePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/.');
}

return [
    'environment' => getenv('APP_ENV') ?: 'local',
    'debug' => filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOL),
    'canonical_origin' => rtrim(getenv('APP_ORIGIN') ?: 'http://localhost', '/'),
    'base_path' => rtrim(getenv('APP_BASE_PATH') ?: $detectedBasePath, '/'),
    'owner_timezone' => getenv('APP_TIMEZONE') ?: 'America/Los_Angeles',
    'log_level' => getenv('APP_LOG_LEVEL') ?: 'info',
    'external_access_enabled' => filter_var(getenv('EXTERNAL_ACCESS_ENABLED') ?: '0', FILTER_VALIDATE_BOOL),
    'session' => [
        'name' => getenv('SESSION_NAME') ?: 'dreamsmith_session',
        'idle_seconds' => (int) (getenv('SESSION_IDLE_SECONDS') ?: 1800),
        'absolute_seconds' => (int) (getenv('SESSION_ABSOLUTE_SECONDS') ?: 28800),
    ],
    'login_limit' => [
        'attempts' => (int) (getenv('LOGIN_ATTEMPT_LIMIT') ?: 5),
        'window_seconds' => (int) (getenv('LOGIN_ATTEMPT_WINDOW_SECONDS') ?: 900),
    ],
    'auth_fingerprint_key' => getenv('AUTH_FINGERPRINT_KEY') ?: 'local-development-only-key',
    'import_signing_key' => getenv('IMPORT_SIGNING_KEY') ?: 'local-import-development-only-key',
    'runtime_retention_hours' => (int) (getenv('RUNTIME_RETENTION_HOURS') ?: 168),
];
