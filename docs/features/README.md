# Feature Specifications

This directory contains the implementation contracts for the Dreamsmith Contract Campaign MVP. `project_specification.md` remains authoritative for shared architecture and domain rules; these files define feature-owned details.

## Feature index

| ID | Feature | Specification | Depends on |
| --- | --- | --- | --- |
| F01 | Application foundation | [F01-application-foundation.md](F01-application-foundation.md) | None |
| F02 | Authentication | [F02-authentication.md](F02-authentication.md) | F01 |
| F03 | Shared application shell | [F03-application-shell.md](F03-application-shell.md) | F01–F02 |
| F04 | Campaign configuration | [F04-campaign-configuration.md](F04-campaign-configuration.md) | F01–F02 |
| F05 | Companies and contacts | [F05-companies-and-contacts.md](F05-companies-and-contacts.md) | F01–F03 |
| F06 | Prospects and research | [F06-prospects-and-research.md](F06-prospects-and-research.md) | F04–F05 |
| F07 | Interactions | [F07-interactions.md](F07-interactions.md) | F06 |
| F08 | Follow-ups and daily work | [F08-follow-ups-and-daily-work.md](F08-follow-ups-and-daily-work.md) | F06–F07 |
| F09 | Opportunities | [F09-opportunities.md](F09-opportunities.md) | F06–F08 |
| F10 | Dashboard and reporting | [F10-dashboard-and-reporting.md](F10-dashboard-and-reporting.md) | F04, F06–F09 |
| F11 | Search and filtering | [F11-search-and-filtering.md](F11-search-and-filtering.md) | F05–F06, F09 |
| F12 | CSV import and export | [F12-csv-import-and-export.md](F12-csv-import-and-export.md) | F04–F09 |
| F13 | Operational hardening | [F13-operational-hardening.md](F13-operational-hardening.md) | F01–F12 |
| F14 | External access foundation | [F14-external-access-foundation.md](F14-external-access-foundation.md) | F01–F13 capabilities |
| F15 | Versioned JSON API | [F15-versioned-json-api.md](F15-versioned-json-api.md) | F14 |
| F16 | MCP server | [F16-mcp-server.md](F16-mcp-server.md) | F14 |

## Shared implementation rules

- Stable keys come from `config/sales.php`; external access policy comes from `config/integrations.php`.
- Every mutation calls an application service with trusted actor context and all required resource versions.
- Controllers, API handlers, MCP tools, and imports never write repositories directly.
- Feature routes below are contracts unless the feature document labels them illustrative.
- POST/Redirect/GET is used for successful browser writes.
- State-changing browser requests require authentication and CSRF protection.
- Archive, event history, time, money, scope, projection, idempotency, and audit behavior follow the root specification.
- Each feature is complete only when its automated checks and acceptance scenarios pass.

## Specification status

All documents are initial approved implementation specifications. Material changes must update the affected feature document and, when cross-cutting, the root specification and an ADR.
