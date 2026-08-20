<?php

declare(strict_types=1);

return [
    'environment' => getenv('APP_ENV') ?: 'local',
    'debug' => filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOL),
    'canonical_origin' => rtrim(getenv('APP_ORIGIN') ?: 'http://localhost', '/'),
    'base_path' => rtrim(getenv('APP_BASE_PATH') ?: '', '/'),
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
];
