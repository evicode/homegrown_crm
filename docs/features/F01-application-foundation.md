# F01 — Application Foundation

## Purpose

Provide the smallest reliable PHP runtime on which every browser, API, and MCP feature depends: bootstrap, configuration, routing, request/response abstractions, database access, migrations, transactions, errors, logging, clock, and view rendering.

The implementation is portable across compatible LAMP hosting. Local XAMPP versions and paths are development observations, not production assumptions.

## Scope

Included:

- Environment-aware configuration with safe defaults and validation.
- One front controller at `public/index.php`.
- Explicit HTML, API, metadata, and MCP route registration.
- Dependency construction without a framework container.
- PDO connection and transaction runner.
- Migration and seed runner suitable for local and deployed environments.
- Request IDs, trusted clock, structured logging, exception boundary, and generic error pages.
- `application_settings` singleton and `audit_events` infrastructure.

Excluded:

- Business-specific controllers and repositories.
- Authentication behavior beyond shared session/bootstrap support.
- Background queues and scheduled workers.

## Configuration

Required production values:

- Environment name and debug flag.
- Canonical application origin and optional base path.
- Database DSN, user, and secret.
- Owner timezone.
- Session cookie settings.
- Log path/level.
- External-access kill switch; invalid or missing integration configuration defaults to disabled.

The deployment contract defines minimum PHP/extensions, Apache capabilities, and MySQL/MariaDB compatibility. Environment-specific paths, ports, credentials, origins, and base paths are configuration only.

Secrets are loaded from environment/deployment configuration outside `public/` and version control. Startup rejects invalid timezone, origin, database, or production debug settings with a safe operational error.

## Components

- `Bootstrap`: loads configuration, sets timezone behavior, registers error boundary, and constructs dependencies.
- `Router`: exact method/path matching, typed path parameters, 404/405 behavior, and named URL generation with base path.
- `Request`/`Response`: normalized headers, query, parsed form/JSON body, cookies, and response factories.
- `Database`: configured PDO with exceptions, native prepares where supported, and UTF-8 connection.
- `TransactionManager`: begins, commits, rolls back, and supports bounded retry only for explicitly retry-safe deadlocks.
- `Clock`: production system clock and deterministic test implementation.
- `Logger`: structured redacted records with correlation IDs.
- `View`: template rendering with escaped helper functions.
- `AuditWriter`: append-only safe metadata writes used inside mutation transactions.

## Routes

- `GET /health/live`: process-level liveness; no database details.
- `GET /health/ready`: deployment-protected readiness including database/migration/settings checks.
- All other route families are registered by owning features.

Health endpoints never disclose credentials, paths, SQL, versions with known exploit value, or exception traces.

## Database

- `application_settings`: singleton ID, active campaign, owner timezone, external feature flags where appropriate, version, timestamps.
- `audit_events`: actor identifiers, operation, entity reference, correlation ID, allowlisted metadata, timestamp.
- Migration ledger table recording migration identifier and application time.

Migrations are ordered, transactional where the database permits, immutable after application, and fail before serving incompatible application code.

## Error behavior

- Unknown route: 404.
- Known route with wrong method: 405 with `Allow`.
- Invalid request media type/body: 400 or 415.
- Unhandled HTML exception: generic 500 page plus correlation ID.
- Unhandled API exception: problem response plus correlation ID.
- Unhandled MCP exception: protocol-appropriate internal error without implementation details.

## Security

- Production disables display errors.
- Request/header/body limits apply before parsing expensive input.
- Trusted proxy and canonical-host rules follow the root.
- Logs redact authorization, cookies, secrets, password fields, and sensitive bodies.
- Repository directories are unreachable over HTTP.

## Tests

- Configuration validation and production-safe defaults.
- Route matching, URL generation, base path, 404, and 405.
- Transaction commit, rollback, and retry-safe deadlock handling.
- Migration from an empty database and repeat no-op run.
- Generic error mapping and correlation IDs for HTML/API/MCP.
- Audit event rollback when its enclosing mutation fails.
- Health endpoints in ready, database-down, and migration-mismatch states.

## Acceptance

- A clean environment can migrate, seed, boot, and serve a protected placeholder page.
- An exception never exposes stack or secret data to a requester.
- URL generation works at `/` and a configured subdirectory.
- Failed transactions leave no partial business or audit writes.
