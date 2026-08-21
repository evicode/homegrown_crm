# Operations Runbook

## Release

1. Confirm the server document root is `public/`, HTTPS is active, and source/configuration/runtime directories are not web-accessible.
2. Take and verify a database backup before migrations. CSV exports are not backups.
3. Set production environment values outside the repository: database credentials, `AUTH_FINGERPRINT_KEY`, `IMPORT_SIGNING_KEY`, canonical origin, and session settings.
4. Run `composer install --no-dev --optimize-autoloader`, `php bin/lint.php`, and `php tests/run.php` in the release environment.
5. Run `php bin/migrate.php` once as a deliberate release step.
6. Smoke-test login, campaign setup, prospect workflow, interaction, follow-up, opportunity, dashboard, CSV import preview, and CSV export.

## Backup and restore

Take automated database backups at least daily during an active campaign, encrypt them at rest, and retain them according to the organization’s retention policy. Keep deployment configuration backups separately from secrets-manager/provider records.

Test a restore periodically into a separate database/environment. Record the date, operator, source backup, result, and recovery duration. Never restore over production as a test.

## Routine maintenance

Run `php bin/maintenance.php --dry-run` before scheduling `php bin/maintenance.php`. It only removes expired files in `var/tmp` and expired `*.log` files in `var/log`; it never touches database records or audit history. Set `RUNTIME_RETENTION_HOURS` (minimum 24; default 168) in the environment.

Monitor readiness, database size, free disk space, error rate, backup results, and log growth. Rotate logs with the host logging facility where possible.

## Incident response

Use the response `X-Request-ID` to locate the structured operational log. Preserve logs and database audit events. For a suspected credential/session incident, rotate the owner password and session secrets, invalidate sessions as appropriate, and preserve affected evidence before cleanup. External-access revocation and kill-switch steps are added with F14.

## Supported browsers and accessibility

Support current stable Chrome, Firefox, Safari, and Edge. Before release, manually exercise primary workflows with keyboard only, a screen reader pass, JavaScript disabled, and widths from 320px through desktop.
