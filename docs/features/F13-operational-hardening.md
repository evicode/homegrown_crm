# F13 — Operational Hardening

## Purpose

Make the browser CRM deployable, observable, recoverable, accessible, and safe before external API/MCP access is enabled.

## Scope

- Deployment configuration and release checklist.
- Logging, health, backup/restore, maintenance, retention, and incident controls.
- Accessibility/responsive/browser verification.
- Security headers and dependency/runtime audits.
- External interface operations are extended by F14–F16.

## Deployment

- Apache document root points to `public/`.
- HTTPS is required outside localhost.
- Canonical origin/base path and trusted proxies are explicit.
- Production debug/display errors are off.
- Database account has least privilege.
- Writable log/temp/export paths are outside `public/` with constrained permissions.
- Migrations run as a deliberate release step with verified backup.

## Security headers

Define CSP compatible with vanilla assets, frame protection, MIME sniff prevention, referrer policy, permissions policy, and HSTS after HTTPS rollout is verified. Avoid inline script/style exceptions unless documented.

## Logging and telemetry

- Structured application/security logs with request IDs.
- Redaction tests for passwords, cookies, tokens, secrets, contact bodies, and uploaded contents.
- Log rotation, retention, disk-use alarms, and protected access.
- Domain audit events are durable business evidence; operational logs are not a substitute.

## Backup and recovery

- Automated database backup frequency/retention appropriate to campaign activity.
- Secure backup of required deployment configuration separately from secrets-manager/provider state.
- Restore runbook to a separate database/environment.
- Periodic restore test records time, result, and operator.
- CSV export is never called a backup.

## Maintenance

- Purge expired sessions, temporary imports, export files, and operational telemetry on a documented schedule.
- Preserve domain event/audit retention according to policy.
- Monitor database size, failed jobs/cleanup, disk space, error rate, and readiness.
- External-access kill switch is introduced by F14 and must leave browser access available.

## Accessibility and compatibility

- Keyboard-only and screen-reader-oriented manual checks.
- Phone width through desktop layouts.
- Current supported Chrome, Firefox, Safari, and Edge versions defined at implementation time.
- Core workflows function without JavaScript.

## Release checklist

- Configuration validation and correct environment.
- Backup completed and restore instructions current.
- Migrations tested from clean and previous release.
- PHP syntax/tests pass; Composer audit passes when dependencies exist.
- Public-path probe confirms protected directories return denial.
- Smoke tests: login, campaign, prospect, interaction, follow-up, opportunity, dashboard, import/export.
- Rollback plan identifies code/database compatibility limits.

## Incident behavior

Generic user errors with correlation ID; diagnostics remain protected. Security incidents support session invalidation, owner password rotation, external kill switch, OAuth client revocation, and log/audit preservation.

## Tests

- Security header and public-path checks.
- Production configuration refuses unsafe debug/HTTP assumptions.
- Backup/restore rehearsal.
- Cleanup honors retention and never deletes permanent records accidentally.
- Log redaction and disk-failure behavior.
- Accessibility/no-JavaScript/phone-width acceptance suite.

## Acceptance

- A release can be deployed and recovered from written, tested instructions.
- No protected source/configuration/runtime file is web-accessible.
- Operational failure produces actionable diagnostics without exposing sensitive data.
