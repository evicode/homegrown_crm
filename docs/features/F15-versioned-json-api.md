# F15 — Versioned JSON API

## Purpose

Expose approved F14 capabilities as a stable REST/JSON contract that outside programs can implement from OpenAPI without private application knowledge.

## Base contract

- Base path `/api/v1`.
- UTF-8 JSON; problem details for errors.
- Bearer OAuth only; browser sessions rejected.
- Authenticated business responses are private/no-store.
- `X-API-Version` identifies effective version.
- Correlation/request ID returned on every response.
- Mutable representations include integer `version` and strong opaque ETag.

## Public routes

- `GET /api/openapi.json`: sanitized OpenAPI 3.1.
- OAuth protected-resource metadata at configured RFC 9728 well-known path(s).
- Health endpoints remain F01 and reveal no business data.

## Resource routes

Representative route contract:

- `GET /api/v1/campaigns/active`, `GET /api/v1/campaigns/{id}/targets`
- `GET|POST /api/v1/companies`, `GET|PATCH /api/v1/companies/{id}`
- `POST /api/v1/companies/{id}:archive|restore`
- `GET|POST /api/v1/contacts`, `GET|PATCH /api/v1/contacts/{id}`
- `POST /api/v1/contacts/{id}:archive|restore`
- `GET|POST /api/v1/prospects`, `GET|PATCH /api/v1/prospects/{id}`
- `POST /api/v1/prospects/{id}:transition|archive|restore`
- Signal evidence subresources under prospects.
- `GET|POST /api/v1/prospects/{id}/interactions`; correction command on interaction.
- Follow-up create/reschedule/complete/cancel commands.
- `GET|POST /api/v1/opportunities`; update/transition commands.
- `GET /api/v1/reports/campaign`
- Import preview/commit and export request/download operations.

Exact OpenAPI operation IDs match F14 capability keys. State changes never use GET. Dedicated commands are used instead of generic patches for transitions, corrections, closes, archive, import commit, and follow-up outcomes.

## Requests

- Strict documented fields; unknown fields rejected.
- Scope-aware nested input authorization.
- `If-Match` required for each primary mutable resource. Compound commands carry additional expected versions in documented body fields.
- `Idempotency-Key` required for F14-marked mutations.
- High-impact commands carry explicit confirmation/reason.
- Content type and size are enforced before JSON parsing.

## Responses

- Create: 201 with Location, ID, version, and safe representation/replay envelope.
- Read/update: 200 with projected representation and ETag.
- Successful no-body command may use 200 with outcome envelope rather than 204 so versions remain visible.
- Pagination returns items, opaque next cursor, and optional bounded metadata; no unbounded total unless query cost is approved.
- Actionable list items include ID and version.

## Error mapping

- Invalid JSON/schema: 400/422 with stable field errors.
- Missing/invalid token: 401 plus standards-compliant challenge.
- Insufficient scope: 403.
- Not found or intentionally concealed unauthorized identity: 404.
- Domain/idempotency conflict: 409.
- Stale `If-Match`: 412 with `stale_version` and safe current version/URL when authorized.
- Rate limit: 429.
- Unexpected: 500 with correlation ID.

Problem documents contain type URI, title, status, stable code, detail safe for clients, correlation ID, and field errors where applicable.

## Filtering and pagination

Map F11 query contract. Mutable lists use stable seek sort plus ID. Activity lists use `(occurred_at,id)` and fixed `as_of` cutoff. Cursor scopes/query/projection are immutable; changing them starts a new traversal.

## Versioning

Additive optional response fields are compatible. Removing/renaming/reinterpreting fields, changing required input, outcomes, or authorization meaning requires a new major path or documented deprecation. Deprecation emits headers and remains documented for a configured minimum window before removal.

## OpenAPI and fixtures

OpenAPI is generated/validated from checked-in source, never runtime reflection of private code alone. It includes OAuth security schemes, scopes, schemas, examples using synthetic data, operation IDs, headers, errors, pagination, idempotency, and version rules. Contract fixtures validate actual responses against it.

## Tests

- Every operation happy/error/scope/projection contract.
- ETag variant and If-Match behavior.
- Compound expected versions and replay ordering.
- Unknown/unauthorized input fields.
- Seek/as-of cursor consistency and tampering.
- OpenAPI validation plus implementation coverage.
- Browser-cookie rejection, CORS, rate limits, no-store.
- Deprecation/version header behavior.

## Acceptance

- A sample outside client can authenticate and complete prospect → interaction → follow-up → qualified opportunity using only published documentation.
- Actual requests/responses validate against OpenAPI.
- API behavior matches browser/MCP domain outcomes without exposing transport-internal details.
