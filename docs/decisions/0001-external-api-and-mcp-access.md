# ADR 0001 — External JSON API and MCP Access

- Status: Accepted
- Date: 2026-08-19

## Context

The campaign CRM was initially specified as a private, browser-based application. Dreamsmith Labs also needs independently built programs and AI agents to perform the supported campaign tasks. A browser-only architecture would encourage outside parties to automate HTML or access the database directly, bypassing validation, state transitions, attribution, and security controls.

The existing application-service boundary already provides the correct reuse point. The decision is therefore about exposing that boundary safely without creating separate business implementations.

## Decision

The project will provide two external adapters:

1. A versioned JSON API under `/api/v1`, documented with OpenAPI 3.1.
2. A remote MCP server under `/mcp`, initially targeting MCP protocol version `2025-11-25` over stateless Streamable HTTP.

The browser, JSON API, MCP server, and import processes invoke the same application services and domain rules. F14 owns a transport-neutral external capability catalog; JSON API and MCP are peer adapters over it. API controllers and MCP handlers do not query repositories directly, depend on one another's transport contracts, or call one another over loopback HTTP.

Remote access uses OAuth 2.1-compatible bearer tokens from a standards-compliant authorization server. The application is a protected resource, validates issuer/audience/expiry/scopes, maps the authenticated subject to a locally enabled integration client, and can revoke that client locally. API and MCP share capability scopes.

External writes use actor attribution, correlation IDs, authoritative integer resource versions, optimistic concurrency, and idempotency where duplicate execution would be harmful. REST uses version-derived `If-Match`; MCP mutations carry the equivalent expected-version set, including every existing aggregate touched by a compound command. Authentication/revocation/current scopes and idempotent replay precede stale-version evaluation; new requests then check all versions and commit domain changes, version increments, audit, and outcome atomically. Audit identity preserves both software-client and validated delegated-human subject where present. High-impact corrections, closures, archive actions, and import commits require elevated scopes/confirmation. Confirmation signals deliberate invocation, not human approval. Browser sessions never authenticate API or MCP calls.

Idempotency uses a single database transaction for the new client/key row, bounded synchronous command, version checks, domain changes/events, audit event, and completed outcome. Concurrent same-key calls wait and replay after commit or receive a retryable lock-timeout result. No durable in-progress reservation or recovery workflow is used.

Integration-client authorization/configuration rows are not request telemetry. Last-use and recent sanitized failure information comes from separate append-only, bounded-retention integration access events, so ordinary API/MCP traffic does not increment client configuration versions or contend on the authorization row.

The MCP server exposes tools and selected read-only resources. It does not autonomously run campaigns, send outreach, perform sampling, provide prompts, or implement long-running MCP tasks in the MVP.

The server will use the official MCP PHP SDK for Streamable HTTP, protocol handling, JSON Schema, protected-resource metadata, and OAuth/JWT middleware after a compatibility gate for PHP/Apache, license, and required protocol features. Failing that gate requires a new ADR; handwritten protocol code is not the default.

Machine-to-machine MCP access adopts `io.modelcontextprotocol/oauth-client-credentials` only when the chosen authorization provider and target clients support it. JWT bearer assertions are preferred to shared client secrets. Interactive delegated clients use the standard authorization-code flow with PKCE.

Outside integrations are developed against a separate synthetic-data environment with distinct database, canonical URLs, OAuth resources/audiences, clients, and credentials. Production and development tokens are mutually invalid.

## Alternatives considered

### Browser automation only

Rejected because HTML is not a stable machine contract and browser automation would couple integrations to presentation details.

### JSON API only

Rejected because outside AI-agent hosts benefit from MCP discovery and typed tool invocation. Requiring every agent builder to create a private API-to-MCP bridge would fragment semantics.

### MCP only

Rejected because ordinary programs and non-MCP systems need a conventional, documented HTTP API.

### Separate API and MCP business implementations

Rejected because validation, state transitions, metrics, and audit behavior would drift.

### MCP adapter built on REST routes

Rejected because it would couple tool behavior to HTTP representations and errors, add loopback failure modes, and make MCP a second-class adapter.

### Handwritten MCP protocol implementation

Rejected as the default because transport, negotiation, authorization metadata, JSON-RPC, and evolving protocol conformance are not CRM business differentiators. It requires a future ADR only if the official PHP SDK fails the compatibility gate.

### Static owner-created bearer keys

Rejected as the remote interoperability target because MCP authorization and independent third-party clients require stronger discovery and audience binding. As a provisional first-party bridge, the owner may issue account-owned local integration tokens that are stored only as hashes, scoped, expiring, revocable, disabled by default, and mapped to the same actor/scope model. They must never silently become the default production interoperability contract.

### Build an OAuth authorization server in this project

Rejected because secure OAuth server implementation is not part of the CRM's business purpose. The project will integrate with a standards-compliant provider.

## Consequences

- F14–F16 are added to the essential feature map.
- The MVP gains an external authorization/provider dependency and requires HTTPS for remote access.
- External contracts require compatibility management, contract tests, rate limits, idempotency storage, and audit attribution.
- F14 must select an authorization provider, shared rate-limit implementation, localhost strategy, and high-impact operation matrix before external access is accepted.
- F14 must define version/precondition sets, deterministic idempotency order and retention, minimal replay envelopes, claim mapping, scope-aware input/output projection, and separate development/production identity boundaries.
- OpenAPI schemas and MCP tool/resource schemas become versioned release artifacts.
- The project remains a LAMP/vanilla application; external adapters are PHP boundary modules over the same application layer, with the official PHP MCP SDK as an intentional Composer dependency.
- Webhooks, autonomous agents, prompts, sampling, and experimental MCP tasks remain deferred.

## Standards references

- MCP Authorization: https://modelcontextprotocol.io/specification/2025-11-25/basic/authorization
- MCP Streamable HTTP transport: https://modelcontextprotocol.io/specification/2025-11-25/basic/transports
- MCP OAuth Client Credentials extension: https://modelcontextprotocol.io/extensions/auth/oauth-client-credentials
- MCP PHP SDK: https://php.sdk.modelcontextprotocol.io/
