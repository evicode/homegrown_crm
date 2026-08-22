# Deployment Requirements

This application targets compatible LAMP environments and does not assume the current XAMPP workstation is the hosting environment.

## Minimum application contract

- PHP 8.2 or newer within the supported 8.x series.
- PHP extensions: JSON, PDO, and the PDO driver for MySQL/MariaDB.
- Apache 2.4-compatible routing, or equivalent front-controller routing supplied by the host.
- MySQL 8+ or a MariaDB version verified by migration and generated-column tests.
- Composer available during build/deployment; `vendor/` may be produced before upload when hosting does not provide Composer.
- HTTPS for every non-local deployment.
- Ability to host the project folder directly, with Apache rewrite rules enabled so the root `.htaccess` can prevent access to source/configuration/runtime directories.
- Writable, non-public runtime locations for logs, temporary imports, and exports.
- Environment or host-supplied configuration for origins, base path, database, sessions, and integrations.

## Deployment-specific values

The following are never inferred from the development machine:

- Filesystem path and operating system.
- Domain, scheme, port, proxy topology, or URL base path.
- Database host, port, name, user, password, or server flavor/version.
- PHP/Apache installation paths and module layout.
- Writable-directory locations and process identity.
- OAuth issuer, audiences, client registration, or external-access availability.

## Verification gate

Before deployment, run configuration validation, PHP/extension checks, database migration compatibility, generated-column/constraint tests, routing/base-path tests, writable-path checks, and public-path denial probes in the actual target environment.
