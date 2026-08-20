# Local setup

The application is designed for compatible LAMP environments and does not depend on XAMPP, Windows, a fixed hostname, or a fixed installation path.

## Requirements

Use the versions and extensions in [deployment requirements](deployment_requirements.md). Point the web server document root at `public/`; the root `.htaccess` is only a defensive fallback for hosting that cannot do so.

## Configuration

Provide configuration through the process environment or the hosting platform's secret/configuration facility:

```text
APP_ENV=local
APP_DEBUG=0
APP_ORIGIN=http://localhost
APP_BASE_PATH=
APP_TIMEZONE=America/Los_Angeles
APP_LOG_LEVEL=info
EXTERNAL_ACCESS_ENABLED=0
AUTH_FINGERPRINT_KEY=replace-with-a-long-random-secret
DB_DSN=mysql:host=127.0.0.1;port=3306;dbname=dreamsmith_campaign;charset=utf8mb4
DB_USER=application_user
DB_PASSWORD=replace-me
```

`APP_BASE_PATH` is empty when hosted at the origin root and may be `/some-prefix` when hosted below it. Do not commit real secrets or production values.

## Build and initialize

```shell
composer install --no-dev --optimize-autoloader
php bin/migrate.php
php bin/create-owner.php owner@example.com
```

The database and least-privilege database user must already exist. The migration command changes only the configured database.

The owner command is interactive, accepts the password through the terminal, and refuses to create a second account. Configure session and login-throttle durations with `SESSION_IDLE_SECONDS`, `SESSION_ABSOLUTE_SECONDS`, `LOGIN_ATTEMPT_LIMIT`, and `LOGIN_ATTEMPT_WINDOW_SECONDS` when the defaults are unsuitable.

`AUTH_FINGERPRINT_KEY` protects the pseudonymous email/IP fingerprints used for login throttling. Generate an environment-specific random value, keep it outside version control, and do not reuse a production value in development or test environments.

## Verify

```shell
composer lint
composer test
```

Unit tests require no database. To also exercise migrations and rollback behavior, provide `TEST_DB_DSN`, `TEST_DB_USER`, and `TEST_DB_PASSWORD` for a disposable empty database before running `composer test`. Tests never infer or reuse production database settings.

After deployment, `GET /health/live` confirms the PHP process can serve requests. `GET /health/ready` returns success only when the configured database is reachable and the foundation migration is present.
