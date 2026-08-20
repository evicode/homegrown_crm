# F11 — Search and Filtering

## Purpose

Provide consistent, safe, reproducible list discovery across companies, prospects, opportunities, and external adapters.

## Scope

- Free-text search, allowlisted filters, sorting, pagination, result counts, and filter persistence in URLs.
- Browser list contracts and transport-neutral query objects used by F15/F16 mappings.
- No full-text search service, fuzzy ranking engine, or saved searches in MVP.

## Query contract

Common parameters:

- `q`: trimmed text search.
- Resource filters such as `status`, `segment`, `signal`, `stage`, `offer`, `due`, `archived`.
- `sort`: allowlisted resource-specific key.
- `direction`: `asc` or `desc` where permitted.
- `page` for browser pagination; external cursor mappings belong to F15/F16.

Unknown values return validation feedback for external contracts and safe defaults for ordinary browser query mistakes. No query value becomes an SQL identifier or fragment.

## Searchable fields

- Companies: name, domain/website, location, industry, notes where explicitly included.
- Contacts: name, role, normalized email.
- Prospects: company/contact names, source, why-them, business problem, qualification notes.
- Opportunities: company/contact names, offer, stage, lost reason.

Sensitive notes are searched only for authenticated owner or clients with the corresponding read scopes. Search result snippets escape content and do not reveal fields outside projection.

## Sorting and pagination

Every sort appends primary key as a deterministic tie-breaker. Browser pagination may use numbered pages for modest current-state lists. External lists use seek cursors bound to client, environment, query, projection, sort tuple, and expiry.

Activity feeds use immutable `(occurred_at, id)` plus an `as_of` cutoff. Mutable record lists use a documented stable seek tuple and best-effort semantics under changes; clients are told whether snapshot completeness is guaranteed.

Actionable results include record ID and version. Archived records are excluded unless explicitly requested and authorized.

## Repositories

Use purpose-built query objects and prepared parameter binding. Count queries must apply the same filters as result queries. Enforce maximum query length, page size, joins, and execution budget.

## Browser behavior

Filters use GET, are shareable/bookmarkable, and survive pagination. Clear-all returns the base list. Empty results distinguish “no records” from “no records match these filters.”

## Tests

- Each allowlisted filter/sort and invalid values.
- SQL injection payloads remain data.
- Deterministic tie ordering and no duplicate page items in stable datasets.
- Signed cursor tampering, client/query/expiry binding, and key rotation.
- Scope-aware searchable fields and snippets.
- Result/count reconciliation.

## Acceptance

- The same transport-neutral query produces equivalent record sets through browser, API, and MCP projections.
- Search never leaks unauthorized fields or accepts raw SQL behavior.
- Actionable external results carry the versions needed for safe mutation.
