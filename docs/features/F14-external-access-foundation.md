# F14 — External Access Foundation

## Purpose

Define the transport-neutral capability, identity, authorization, concurrency, idempotency, projection, rate-limit, audit, and integration-development contracts shared by F15 JSON API and F16 MCP.

## Scope

- OAuth protected-resource configuration and token validation.
- Local integration-client approval/revocation.
- Shared capability catalog with schemas, scopes, versions, outcomes, and high-impact policy.
- Idempotency and concurrency orchestration.
- Shared rate limiting, access telemetry, projection, cursor, and audit context.
- Owner Integrations screen and external onboarding documentation.
- No OAuth authorization-server implementation.

## Required deployment decisions

Before F14 acceptance, deployment configuration must name:

- Production and development OAuth/OIDC issuer.
- Canonical API and MCP resource/audience identifiers.
- JWT algorithms and JWKS/discovery endpoints.
- Exact claims for client ID, authorized party, human subject, grant mode, scopes, and audience.
- Client registration/consent process and PKCE support.
- Whether MCP client credentials extension is supported.

Only asymmetric signed JWT access tokens are accepted initially. Opaque-token introspection requires a later extension to this specification.

## Data

### `integration_clients`

Trusted issuer, client ID/authorized-party policy, optional expected owner subject, display name, environment, maximum scopes, revoked time/reason, version, timestamps. Unique environment/issuer/client identity.

### `integration_access_events`

Nullable validated client, environment, API/MCP/metadata transport, operation, outcome class/code, correlation ID, safe network fingerprint when policy permits, occurrence time. Append-only, redacted, bounded retention.

### `idempotency_records`

Client, key, operation, canonical request hash, completed outcome class/code, affected entity IDs/versions, safe replay metadata, expiry, timestamps. Unique client/key.

### `rate_limit_buckets`

Hashed limiter identity, policy key, window start, count, expiry. Database-backed shared enforcement for MVP; Apache request/body/time limits provide the earlier network guard. Replace with a gateway/shared cache only through configuration and equivalent contract tests.

## Actor context

Trusted context includes actor type, owner user if browser, integration client if external, validated delegated `sub` when present, machine/delegated mode, issuer, effective scopes, environment, and correlation ID. Caller payload cannot override it.

Delegated tokens must match the configured owner subject. Machine tokens must match the adopted grant/extension policy and contain no fabricated human actor. Ambiguous/missing identity claims fail authentication.

## Scopes

Use root scope families and high-impact matrix. Effective scopes are token scopes intersected with local maximum scopes. Revoked/unknown/wrong-environment clients fail before idempotency replay.

Scope projection applies to input, output, list discovery, includes, exports, MCP tools, resources, templates, and direct reads. Unauthorized fields are rejected on input and omitted only where a read projection explicitly defines omission.

## Capability catalog

Each capability entry defines:

- Stable capability key and purpose.
- Application command/query.
- Input/output schema independent of HTTP/MCP.
- Required scopes and high-impact confirmation.
- Expected-version set for all existing aggregates it may mutate.
- Idempotency requirement and replay envelope.
- Stable outcomes.
- Projection/includes.
- Audit operation and safe metadata.
- Rate-limit policy.

Initial capability families cover campaign reads, companies, contacts, prospects/signals/transitions, interactions/corrections, follow-ups, opportunities/transitions, reports, imports, and exports. Campaign activation and integration administration remain browser-only.

## Resource versions and locking

Every adapter supplies the capability's expected-version set. After idempotency ownership is acquired, services lock existing domain rows in this deterministic order, ascending ID within each type:

1. Application settings/campaign/targets
2. Companies
3. Contacts
4. Prospects and signal evidence
5. Interactions/status events
6. Opportunities/stage events
7. Follow-ups
Integration-client configuration is read for authorization before replay and is not held as a write lock during ordinary commands. Database deadlocks receive at most two bounded retries only when the request has a valid idempotency key and no outcome committed; otherwise return retryable conflict. One stale version aborts everything.

## Idempotency

Required processing order follows the root. One transaction covers new key insertion, row locks, version checks, mutation, events, audit, and completed replay envelope. Concurrent duplicate waits then replays; lock timeout returns `idempotency_in_progress`.

Outcome consumption policy:

- Authentication, authorization, kill-switch, request-size, and rate-limit failures never create/consume a key.
- Parse/schema/validation failures never create/consume a key.
- Stale-version and pre-mutation business conflicts roll back the new key and are not replay records.
- A successful mutation always records its success envelope.
- A deterministic accepted command that commits a terminal business outcome records it even if response delivery fails.
- Transient infrastructure/internal failures roll back and never become replayable outcomes.

Fingerprint canonicalizes validated capability key/input, semantic null/default handling, expected versions, and confirmation fields. Published key constraints: 16–128 visible ASCII characters from an allowlist; retention initially 24 hours and configurable consistently in docs. Expired keys may execute again and clients are warned accordingly.

## Outcome codes

At minimum: validation failed, authentication required/invalid, insufficient scope, not found, conflict, stale version, idempotency conflict/in progress, rate limited, external access disabled, and internal failure. F15/F16 map these without changing meaning.

## Rate limiting

- Apache/global request/body/time controls protect all paths.
- Database fixed-window policies protect unauthenticated metadata/token-failure routes using privacy-safe network hashes.
- Authenticated policies key by integration client and capability risk.
- Headers/tool metadata disclose retry timing only where safe.
- Limiter storage expires automatically and never includes raw tokens/IPs.

## Cursors

HMAC-signed opaque cursors bind environment, client, capability, scopes/projection, filters, sort tuple, `as_of` where applicable, expiry, and key ID. Support active plus previous signing key during bounded rotation. Tamper, cross-client, changed-query, wrong-environment, or expiry returns invalid cursor.

## Integrations UI

Owner can create/approve mapping, set maximum scopes/subject/environment, inspect last successful use and recent sanitized events, reduce scopes, revoke, and rotate mapping metadata with version protection. No token or provider secret is displayed/stored.

## Development and documentation

Separate synthetic-data deployment and audience. Deliver an integration guide covering onboarding, OAuth flow, environments, scopes, capability catalog, idempotency/version examples, errors, rate limits, pagination, retention, revocation, and supported versions. Check in sanitized contract fixtures and minimal API/MCP clients.

## Tests

- Claim mapping for delegated/machine/ambiguous/wrong audience/environment tokens.
- Scope intersection, projection, discovery filtering, and revocation before replay.
- Version sets, deterministic locks, bounded deadlock retry.
- Every idempotency outcome category and concurrent same-key behavior.
- Rate policies across Apache/database/client layers.
- Cursor integrity/rotation/as-of behavior.
- Telemetry redaction/retention and no client-row mutation on use.
- Production/development isolation and kill-switch fail closed.

## Acceptance

- F15 and F16 can expose a capability without redefining business, scope, version, idempotency, or outcome rules.
- Ordinary external reads do not mutate authorization state.
- No invalid/revoked/under-scoped client can discover or invoke protected capability data.
