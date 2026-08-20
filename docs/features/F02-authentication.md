# F02 — Authentication

## Purpose

Authenticate the single owner for browser use and protect every owner-facing route. External bearer-token authentication belongs to F14.

## Scope

Included:

- Owner account provisioning through seed/CLI setup.
- Login, logout, session renewal, inactivity timeout, and absolute timeout.
- Authentication middleware and safe post-login return location.
- Owner password change through an authenticated browser flow.

Excluded:

- Public registration, email verification, forgotten-password email, teams, roles, and external OAuth login.

## Data

`users` contains:

- `id`, normalized unique `email`, `password_hash`, `password_changed_at`, `last_login_at`, `disabled_at`, timestamps.

Passwords use PHP's current recommended password algorithm. Successful login rehashes when `password_needs_rehash()` returns true.

## Routes

- `GET /login`: login form; redirects authenticated owner to dashboard/setup.
- `POST /login`: verify credentials, rotate session ID, establish owner identity.
- `POST /logout`: CSRF-protected session destruction.
- `GET /account/password`: authenticated password-change form.
- `POST /account/password`: verify current password and update hash.

## Validation and behavior

- Email is trimmed, case-normalized, syntactically validated, and length-limited.
- Password is never trimmed or logged.
- Login failures use one generic message for unknown, disabled, or incorrect credentials.
- Return locations must be same-application named paths; arbitrary URLs are rejected.
- Password change requires current password, a sufficiently long new password, and confirmation.
- Password change invalidates other known sessions where supported and rotates the current session.

## Session contract

- Cookie: `HttpOnly`, `SameSite=Lax`, path-aware, and `Secure` under HTTPS.
- Session identifier rotates on authentication and privilege-sensitive changes.
- Inactivity and absolute lifetimes come from configuration.
- Timed-out requests redirect HTML to login and never authenticate API/MCP.
- Browser sessions are not accepted under `/api/v1` or `/mcp`.

## Abuse protection

Login attempts receive IP/account-aware throttling without confirming account existence. Repeated failures are logged in redacted security telemetry. The MVP does not implement CAPTCHA or account email lockout.

## Audit

Record successful login, logout, password change, disabled-account attempt, and throttling security events. Do not record submitted passwords or full session identifiers.

## Tests

- Correct, incorrect, unknown, and disabled login.
- Session fixation prevention and cookie attributes.
- Inactivity/absolute expiry.
- CSRF-protected logout/password change.
- Safe return-location validation.
- Password rehash and session rotation.
- Browser cookie rejected by API and MCP routes.
- Throttling behavior without account enumeration.

## Acceptance

- Anonymous visitors can access only login and explicitly public operational/integration metadata routes.
- Authentication creates exactly one valid owner session with a rotated identifier.
- Logging out prevents reuse of the prior session.
- No authentication response or log reveals whether an email exists.
