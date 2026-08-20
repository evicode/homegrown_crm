# F12 — CSV Import and Export

## Purpose

Move campaign data into and out of the application safely without creating a second validation or business-rule path.

## Scope

- Prospect-oriented CSV template/download, upload, mapping, validation preview, and commit.
- Separate exports for prospects, interactions, opportunities, and optionally companies/contacts.
- External preview/commit and export capabilities under elevated scopes.
- No arbitrary spreadsheet formulas, background imports, or silent updates.

## Import format

UTF-8 CSV with documented stable headers. Initial supported columns cover segment, company/contact identity, contact details, source, why-them, signal/evidence, status limited to safe initial states, and follow-up fields where supported.

File limits define bytes, rows, columns, cell length, and parser time. Reject malformed encoding, duplicate headers, unsupported delimiters where automatic detection is unsafe, and spreadsheet structures masquerading as CSV.

## Workflow

1. Upload to a random non-public temporary path.
2. Parse and normalize through shared command input rules.
3. Detect invalid rows, within-file duplicates, and potential existing matches.
4. Show inserts, warnings, and rejected rows without mutation.
5. Issue a short-lived preview plan/token.
6. Commit only after explicit confirmation.
7. Delete temporary input after commit, cancellation, or expiry.

Initial MVP inserts and flags matches; it does not automatically update existing records until F12 defines deterministic stable matching keys. Names alone never authorize updates.

## Preview token

Bind to integration client or owner session, environment, file hash, normalized plan, campaign, expiry, and versions of any referenced existing records. Sign with a rotatable server key. Commit rejects altered, stale, expired, cross-client/session, or cross-environment tokens.

## Transaction behavior

Commit is bounded and synchronous. One database transaction covers inserted records, events, audit, and external idempotency outcome. Any invalidated dependency or database failure rolls back the full import. A configured maximum keeps locks and request time bounded.

## Routes

- `GET /data/import`, `POST /data/import/preview`, `POST /data/import/commit`
- `POST /data/import/cancel`
- `GET /data/export`
- `POST /data/export/{type}`

External mappings use F15/F16 capabilities with `data:import` or `data:export`, projection scopes, confirmation, and idempotency for commit.

## Export

- UTF-8 CSV with documented headers and spreadsheet-injection escaping.
- Dates/times include clear format/timezone; money includes currency.
- External exports include only fields authorized by both export and resource read scopes.
- Files remain outside `public/`; download references are random, short-lived, authenticated, client-bound, bounded-use, and audited.
- Version fields appear only in synchronization-oriented exports explicitly documented for that purpose.

## Tests

- Valid, invalid, duplicate, oversized, malformed, and mixed files.
- Preview causes no writes.
- Token tamper/expiry/session/client/environment/version rejection.
- Full rollback on a late row failure.
- Concurrent identical external commit idempotency.
- Formula injection protection and scope-projected exports.
- Temporary/export file cleanup.

## Acceptance

- Invalid files cannot partially mutate campaign data.
- The preview explains every rejected row and possible duplicate.
- Exported data is understandable and safe to open in common spreadsheet software.
