# F16 — MCP Server

## Purpose

Expose approved F14 capabilities to independently built AI-agent hosts through a standards-conforming remote Model Context Protocol server.

## Current delivery

`POST /mcp` is now wired through the official PHP SDK with protocol baseline `2025-11-25`, local bearer-token authentication, scope-filtered tool discovery, and the active-campaign/report read tools. Additional tool families will be added by reusing the established API-domain services.

## Protocol and transport

- Endpoint `/mcp` through the canonical front controller.
- Initial stable protocol baseline `2025-11-25` with initialization negotiation and required protocol header behavior.
- Stateless Streamable HTTP using JSON responses where permitted.
- GET/POST and unsupported-method behavior follow the selected protocol profile.
- Sessions, SSE notifications, prompts, sampling, elicitation, and tasks are disabled/deferred.

Use the official PHP MCP SDK with pinned Composer lock. Compatibility gate verifies PHP/Apache lifecycle, license, Streamable HTTP, JSON schemas, OAuth middleware, protected-resource metadata, Origin handling hooks, and baseline protocol. Upgrades run full conformance/contract tests and do not silently enable capabilities.

## Authentication

F14 token validation, local mapping, scopes, revocation, rate limits, telemetry, and kill switch apply before MCP dispatch. Publish RFC 9728 metadata and compliant challenges. Validate canonical resource audience, Origin when present, Host/canonical URL, and protocol version. Browser session cookies never authenticate MCP.

Optional machine access advertises `io.modelcontextprotocol/oauth-client-credentials` only when configured/provider/client compatible. Interactive clients use authorization code with PKCE.

## Tools

Initial stable tool families, with final names using clear snake case:

- `get_active_campaign`, `get_campaign_report`
- `search_companies`, `get_company`, `create_company`, `update_company`
- `search_contacts`, `get_contact`, `create_contact`, `update_contact`
- `search_prospects`, `get_prospect`, `create_prospect`, `update_prospect`, `transition_prospect`
- `add_signal_evidence`, `update_signal_evidence`
- `list_interactions`, `record_interaction`, `correct_interaction`
- `list_daily_work`, `schedule_follow_up`, `reschedule_follow_up`, `complete_follow_up`, `cancel_follow_up`
- `search_opportunities`, `get_opportunity`, `create_opportunity`, `update_opportunity`, `transition_opportunity`
- Elevated archive/restore, import preview/commit, and export tools where F14 authorizes them.

Tool schemas map directly to F14 capability schemas. Mutations require idempotency key, all expected versions, and high-impact confirmation/reason when applicable. Tool annotations accurately indicate read-only, destructive, idempotent, and open-world behavior but are never relied on for security.

## Resources

Read-only resources may expose:

- Active campaign context and canonical vocabulary.
- Individual projected prospect/company/contact/opportunity records.
- Bounded campaign report context.

`resources/list`, resource templates, and direct reads return only entries permitted by current effective scopes. Every read reauthorizes; prior discovery grants no continuing access after scope reduction/revocation. URIs contain opaque IDs or safe stable identifiers and never secrets.

## Discovery and projection

`tools/list` contains only currently permitted tools. Optional include fields receive runtime scope checks. Tool/resource output is minimum necessary, structured, schema-validated, bounded, and scope-projected. Business text is labeled/treated as untrusted data and never inserted into tool descriptions, server instructions, or protocol metadata.

## Pagination

List/search inputs use bounded `limit` and F14 opaque cursor. Outputs return items with IDs/versions and optional next cursor. Activity feeds preserve `as_of`; no tool returns unbounded arrays or complete interaction history by default.

## Results and errors

- Successful query: structured content matching output schema.
- Successful mutation: minimal outcome with entity IDs/resulting versions and replay indication.
- Business failures: structured stable F14 code/details marked as tool execution error where MCP requires, distinct from JSON-RPC protocol errors.
- Authentication/transport/protocol errors occur at HTTP/JSON-RPC layer.
- Never return stack, SQL, local path, token, raw log, or unauthorized record fields.

## Idempotency and concurrency

Same F14 ordering and transaction as API. Identical completed key replays before stale-version evaluation. Concurrent same-key calls serialize; timeout returns retryable in-progress. Compound tools declare every expected version in schema.

## Rate and abuse controls

Apply pre-auth/global and per-client/capability limits, request/batch/depth/tool-result bounds, Origin/Host protection, and execution timeout. JSON-RPC batches are disabled initially unless SDK/spec conformance and per-item authorization/idempotency are fully specified.

## Tests

- Official SDK conformance and initialization/version negotiation.
- OAuth discovery/challenges, audience, scopes, revocation, Origin/Host.
- Scope-filtered tools/resources/templates/direct reads.
- Every tool schema and F14 mapping.
- Mutation idempotency, compound stale versions, high-impact confirmation.
- Pagination/cursor bounds and untrusted-content handling.
- JSON-RPC versus business-error separation.
- API/MCP parity fixtures and disabled capability checks.
- Minimal TypeScript/Python or other supported sample client against synthetic environment.

## Acceptance

- A standard MCP client can discover authentication, initialize, list permitted capabilities, and execute the documented end-to-end campaign workflow.
- Scope reduction or revocation immediately removes discovery/read/call access.
- MCP produces the same domain versions, events, metrics, and audit attribution as browser and API commands.
