# Dreamsmith Contract Campaign — Project Specification

## 1. Document purpose

This document defines the project as a whole: its purpose, boundaries, architecture, major features, shared rules, and the contracts between those features. It is the architectural source of truth for the MVP.

Each major feature will receive a separate feature specification before implementation. Those documents should define detailed behavior, fields, state transitions, routes, UI states, and tests without repeating project-wide decisions found here.

### Specification hierarchy

When documents differ, use this order:

1. This project specification for current system-wide behavior and boundaries.
2. The relevant feature specification for current feature-specific behavior.
3. An approved decision record explaining why an architectural change was made.
4. The MVP build plan for sequencing and initial intent.
5. The business need for commercial context.

An approved decision must update every affected specification in the same change; decision records explain history and do not remain as permanent overrides. Coding conventions remain governed by `coding_standards.md`. A feature specification may tighten a rule but should not silently contradict it.

## 2. Product definition

The product is a private, single-owner campaign CRM for Dreamsmith Labs. It organizes a researched, founder-led client acquisition process across agency partners, direct prospects, and the owner's existing network. Its capabilities are available through the owner-facing web application, a versioned JSON API, and a remote Model Context Protocol (MCP) server so independently built programs and AI agents can participate safely in the same workflow.

It is designed to support deliberate, low-volume outreach. It is not intended to maximize message volume or automate relationship-building.

### Primary outcome

The owner can run the entire daily acquisition workflow from one application:

`Research → Contact → Follow up → Converse → Qualify → Offer → Win or close`

### Product principles

- **Action before reporting:** The first screen should make today's work obvious.
- **Evidence before outreach:** Cold prospects require a recorded reason for contact.
- **One history:** Interactions and follow-ups must remain attached to the prospect relationship.
- **Truthful metrics:** Counts derive from saved business events, not manually maintained totals.
- **Manual first:** The MVP validates the sales process before automating it.
- **Recoverability:** User-created business records are archived, not casually destroyed.
- **Progressive enhancement:** Core workflows work through server-rendered HTML.
- **Small architecture:** Add layers only when they enforce a real boundary or shared rule.
- **One capability, multiple adapters:** Browser, API, and MCP requests execute the same application services and domain rules.
- **Safe automation:** External writes are scoped, attributable, idempotent where required, and subject to the same state transitions as owner actions.

## 3. Scope boundary

### The system owns

- Owner authentication and session state.
- Campaign dates and targets.
- Companies and their contacts.
- Campaign-specific prospect qualification and pipeline state.
- Buying-signal evidence.
- Interaction history.
- Follow-up scheduling and outcomes.
- Prospect-status and opportunity-stage event history.
- Sales opportunities and their values/stages.
- Derived dashboard metrics.
- CSV import and export.
- External-client mappings, delegated identities, scopes, and local revocation. The authorization provider owns credential issuance.
- A documented, versioned JSON API for supported application use cases.
- A remote MCP server exposing supported use cases as tools and read models as resources where useful.

### The system references but does not own

- External websites and LinkedIn profiles through stored URLs.
- Emails and calls performed outside the application.
- Proposals, contracts, invoices, and discovery deliverables.
- Public marketing content and case studies.

The MVP records that these activities occurred; it does not perform or manage them.

### Out of scope

- Automated sending, inbox synchronization, calendars, enrichment, scraping, or autonomous AI research performed by this application.
- Public registration, password recovery, teams, roles, or record assignment.
- Lead purchasing, bulk campaigns, open/click tracking, or automated lead scoring.
- Proposal generation, e-signature, billing, project delivery, or client portals.
- Changes to the public Dreamsmith Labs website.
- Native mobile applications or offline support.
- Webhooks, event subscriptions, MCP prompts, MCP sampling, and long-running MCP tasks.
- An in-house OAuth authorization server; authorization may be delegated to a standards-compliant provider.

## 4. Actors and authorization

### Owner

The sole application user. The owner can view and modify every non-system record, import/export data, configure the active campaign, and archive/restore records.

### Anonymous visitor

Can only view and submit the login form. Every other route redirects to login or returns an authentication error appropriate to the response type.

### External client

An independently built program or AI agent authenticated with an access token. It may perform only the operations allowed by its granted scopes. Every request is attributed to a client identity in the audit context; a client never inherits the browser session.

### Future authorization boundary

The database may support more than one user record for operational recovery, but the MVP implements one owner and no row-level ownership. Multi-user-aware abstractions are deferred until there is a real requirement.

## 5. Domain map

```text
Owner Session
    │
    ▼
Application Settings ── Active Campaign ─────── Campaign Targets
    │
    ▼
Prospect ────────────── Company ───────── Contacts
    │                       │                 │
    ├── Signal Evidence     └─────────────────┘
    ├── Status Events
    ├── Interactions
    ├── Follow-ups
    └── Opportunity
            │
            ├── Stage Events
            └── Offer, Stage, Value, Close Outcome

Dashboard/Reporting reads the above domains but does not own them.
Browser, JSON API, MCP, and Import/Export adapters invoke the same application services.
```

### Aggregate boundaries

- **Company/contact records:** Reusable organizations and people. A person may be independent or belong to a company.
- **Prospect aggregate:** A company, an individual, or a company/contact relationship participating in one campaign, including segment, research evidence, status, and status history.
- **Interaction aggregate:** An immutable account of a completed or attempted communication. Corrections are allowed, but interactions are not used as mutable tasks.
- **Follow-up aggregate:** The single source of truth for pending, completed, and cancelled next actions on a prospect relationship, optionally associated with an opportunity.
- **Opportunity aggregate:** A qualified commercial possibility, including offer, stage, value, closing data, and stage history.
- **Campaign aggregate:** Dates, active state, and targets. Reporting is derived from campaign-linked records.

## 6. Essential feature map

Each feature below requires its own detailed specification before it is built. The listed responsibility is its architectural boundary.

| ID | Feature | Responsibility | Depends on |
| --- | --- | --- | --- |
| F01 | Application foundation | Bootstrap, configuration, routing, database access, errors, shared HTTP behavior | None |
| F02 | Authentication | Owner login, logout, session lifecycle, route protection | F01 |
| F03 | Shared application shell | Navigation, layout, messages, reusable view components, responsive frame | F01, F02 |
| F04 | Campaign configuration | Active campaign, campaign dates, metric target definitions | F01, F02 |
| F05 | Companies and contacts | Reusable organization and person records, including independent people, archive/restore | F01–F03 |
| F06 | Prospects and research | Segment, source, why-them evidence, signals, status, qualification context | F04, F05 |
| F07 | Interactions | Chronological communication record, response evidence, and last-contact effects | F06 |
| F08 | Follow-ups and daily work | Authoritative next-action records, outcomes, due classification, dashboard work queues | F06, F07 |
| F09 | Opportunities | Offers, stages, value, close outcome, and association with the shared follow-up model | F06–F08 |
| F10 | Dashboard and reporting | Derived targets, funnel, pipeline totals, recent activity | F04, F06–F09 |
| F11 | Search and filtering | Consistent query/filter/sort/pagination behavior across lists | F05, F06, F09 |
| F12 | CSV import and export | Validated bulk entry, duplicate handling, portable data extraction | F04–F09 |
| F13 | Operational hardening | Logging, backup/restore guidance, accessibility, responsive checks, release readiness | All |
| F14 | External access foundation | Shared capability catalog/schemas, OAuth resource integration, delegated/client identity, scopes, revocation, audit attribution, rate limiting | F01, F02, F04 and exposed application use cases |
| F15 | Versioned JSON API | REST/JSON mappings of the shared external capabilities with OpenAPI documentation | F14 and exposed application use cases |
| F16 | MCP server | MCP tool/resource mappings of the shared external capabilities using the official PHP SDK | F14 and exposed application use cases |

Status-event persistence belongs to F06 and stage-event persistence belongs to F09. F14 owns transport-neutral external capability inputs, outputs, scope requirements, actor context, concurrency tokens, and idempotency policy. F15 and F16 are peer adapters: neither depends on the other's routes, representations, envelopes, or errors, and neither creates new domain capabilities. The dependency order is not a requirement to build every feature serially. It identifies which contracts must be stable before a dependent feature can be accepted.

## 7. System architecture

### 7.1 Runtime architecture

The application is a server-rendered PHP web application running under Apache with MySQL/MariaDB persistence.

```text
Browser ──────────────── HTML adapter ───────┐
External program ─────── JSON API adapter ───┼── Application Service
AI/MCP client ────────── MCP adapter ────────┘        │          │
                                                      ▼          ▼
                                                Domain Rules  Repository
                                                                  │
                                                                  ▼
                                                            PDO → MySQL

HTML controller → View Model → PHP Template → HTML Response
API controller  → Representation Mapper → JSON Response
MCP handler     → Tool/Resource Mapper → JSON-RPC Response
```

Browser enhancement endpoints remain private browser interfaces and are not the external API contract. The external API is versioned under `/api/v1`. MCP uses one Streamable HTTP endpoint at `/mcp` and its negotiated protocol contract.

### 7.2 Architectural layers

#### Entry and HTTP layer

Responsibilities:

- Bootstrap configuration and dependencies.
- Match explicit routes and HTTP methods.
- Apply authentication and CSRF middleware.
- Convert requests into validated application input.
- Convert results/exceptions into redirects, HTML, or limited JSON.
- Select the HTML, versioned API, or MCP adapter without changing domain behavior.

Controllers must not contain SQL or core business rules.

#### Application layer

Responsibilities:

- Execute use cases such as `CreateProspect`, `RecordInteraction`, or `CloseOpportunity`.
- Coordinate repositories and transactions.
- Enforce workflow-level authorization.
- Receive an explicit trusted actor context and produce attributable audit events for every attempted or successful business mutation according to the audit policy. The context distinguishes owner actions, delegated external actions, machine clients, and system processes.
- Return result objects or view data suitable for controllers.

This is the preferred location for transaction boundaries.

#### Domain layer

Responsibilities:

- Allowed values and state transitions.
- Cross-field rules such as readiness requirements.
- Due-date classification and commercial close rules.
- Metric definitions that have business meaning.

Domain rules must be testable without HTTP or templates.

#### Persistence layer

Responsibilities:

- Prepared SQL and database mapping.
- Queries tailored to use cases and reports.
- No HTML, redirects, session access, or hidden business transitions.

Repositories may return records or purpose-built read models. A generic ORM or generic base repository is not required.

#### Presentation layer

Responsibilities:

- Escaped semantic HTML.
- Shared layout and small reusable view partials.
- Accessible forms, tables, navigation, validation summaries, and empty states.
- View-only formatting of dates, money, labels, and status badges.

Templates do not query the database or mutate records.

#### Browser enhancement layer

Responsibilities:

- Confirmations, modal/dialog behavior, import preview interaction, and convenient filters.
- Fetch-based quick actions only when a normal form fallback exists or the enhancement is nonessential.

JavaScript must not be the sole enforcer of validation, status transitions, authentication, or CSRF protection.

#### External adapter layer

Responsibilities:

- Authenticate bearer tokens independently of browser sessions.
- Map stable external schemas to application commands and results.
- Enforce scopes before invoking a use case.
- Apply idempotency, concurrency, pagination, rate limits, and external error conventions.
- Attach client identity and correlation metadata to the application audit context.

API controllers and MCP handlers never query repositories directly. MCP may call application services in-process; it must not make loopback HTTP calls to the JSON API merely to reuse behavior.

### 7.3 Proposed project tree

```text
/ 
├── public/
│   ├── index.php                 # Front controller
│   ├── .htaccess                 # Apache rewrite and access rules
│   └── assets/
│       ├── css/
│       │   ├── app.css           # Imports or defines global layers
│       │   ├── components.css
│       │   └── pages.css
│       └── js/
│           ├── app.js            # Shared bootstrapping
│           ├── dialogs.js
│           ├── filters.js
│           └── import-preview.js
├── config/
│   ├── app.php                   # Environment-neutral application config
│   ├── database.php              # Connection configuration
│   ├── routes.php                # Explicit route table
│   ├── sales.php                 # Canonical sales values and labels
│   └── integrations.php          # Issuers, audiences, scopes, limits, external feature switch
├── src/
│   ├── Application/
│   │   ├── Auth/
│   │   ├── Campaign/
│   │   ├── Company/
│   │   ├── Prospect/
│   │   ├── Interaction/
│   │   ├── FollowUp/
│   │   ├── Opportunity/
│   │   ├── Reporting/
│   │   └── DataTransfer/
│   ├── Integration/
│   │   ├── Contract/             # Transport-neutral capability schemas and policies
│   │   ├── Api/
│   │   │   ├── Controller/
│   │   │   ├── Representation/
│   │   │   └── OpenApi/
│   │   ├── Auth/
│   │   └── Mcp/
│   │       ├── Tool/
│   │       ├── Resource/
│   │       └── Transport/
│   ├── Domain/
│   │   ├── Campaign/
│   │   ├── Prospect/
│   │   └── Opportunity/
│   ├── Http/
│   │   ├── Controller/
│   │   ├── Middleware/
│   │   ├── Request.php
│   │   ├── Response.php
│   │   └── Router.php
│   ├── Persistence/
│   │   ├── CampaignRepository.php
│   │   ├── CompanyRepository.php
│   │   ├── ProspectRepository.php
│   │   ├── InteractionRepository.php
│   │   ├── OpportunityRepository.php
│   │   └── ReportingRepository.php
│   ├── Support/
│   │   ├── Clock.php
│   │   ├── Csrf.php
│   │   ├── Logger.php
│   │   ├── Validator.php
│   │   └── View.php
│   └── Database.php
├── templates/
│   ├── layouts/
│   ├── components/
│   ├── auth/
│   ├── dashboard/
│   ├── campaigns/
│   ├── companies/
│   ├── prospects/
│   ├── opportunities/
│   ├── data-transfer/
│   └── integrations/
├── database/
│   ├── migrations/
│   └── seeds/
├── tests/
│   ├── Unit/
│   ├── Integration/
│   ├── Contract/
│   └── Support/
├── var/
│   └── log/                      # Runtime files; not web-accessible
├── .htaccess                     # Defensive root access rules
└── docs/
    ├── business_need.md
    ├── mvp_build_plan.md
    ├── project_specification.md
    ├── features/                 # Detailed feature specifications
    └── decisions/                # Short architecture decision records
```

The exact number of classes should follow implementation needs. The tree defines ownership and dependency direction, not a requirement for empty scaffolding.

### 7.4 Dependency direction

- Templates depend on prepared view data, never repositories.
- Controllers depend on application services.
- Application services may depend on domain rules, repositories, clock, and transactions.
- Repositories depend on PDO/database infrastructure.
- Domain rules do not depend on controllers, templates, sessions, JavaScript, or PDO.
- Reporting may read across domains but cannot mutate them.
- Import must call the same application services used by manual entry.
- API and MCP adapters depend on application-layer contracts; the application and domain layers never depend on API versions, JSON representations, MCP tool names, or transport details.
- The F14 capability catalog supplies shared command/result schemas and operation policies to API and MCP mappings; transport-specific metadata remains in its owning adapter.

Circular feature dependencies are not allowed. If two features need the same rule, the rule belongs in a shared domain/support contract or in the feature that owns the concept.

## 8. Canonical domain rules

These rules apply throughout the application and must not be redefined independently by screens.

### 8.1 Prospect identity

A prospect is a campaign-specific relationship anchored by a company, an individual contact, or both. At least one anchor is required. The same company or person may appear in another campaign.

A contact may belong to one company or be independent. If a prospect has both a company and a primary contact, that contact must belong to the company. Changing a prospect's primary contact does not move the contact between companies.

Within one campaign, the application prevents duplicate non-archived identities in all three forms:

- The same company-only prospect.
- The same contact-only prospect.
- The same company/contact prospect.

Because nullable composite unique keys do not provide this behavior consistently in MySQL, application validation is required in addition to database indexes. Concurrent creation conflicts must fail safely and return a conflict response rather than create a duplicate.

### 8.2 Segments

- `partner`: Agency, consultancy, fractional executive, or adjacent service provider that can refer or subcontract work.
- `direct`: A business showing observable software-shaped pain or a buying signal.
- `network`: A person or organization with an existing relationship to the owner.

Segments affect readiness validation and reporting; they do not create different record types.

### 8.3 Prospect readiness

- Partner and direct prospects require a nonblank `why_them` value before `ready_to_contact`.
- Direct prospects additionally require at least one signal with an evidence note.
- Network prospects may become ready without cold-research evidence.
- Server-side validation is authoritative.

### 8.4 Prospect statuses

Canonical keys:

`researching`, `ready_to_contact`, `contacted`, `later`, `interested`, `conversation`, `qualified`, `closed_no_fit`

Labels are presentation data. Database records and business rules use stable keys.

Every status transition is explicit. Recording the first outbound interaction may transition `ready_to_contact` to `contacted`; other advancement normally requires an intentional user action. Editing an old interaction must not unexpectedly rewind or advance the prospect.

Every successful change writes a `prospect_status_events` row in the same transaction. The event records the prior status, new status, occurrence time, and optional reason. The current status remains on `prospects` for efficient reads; event history is authoritative for when milestones were reached.

Normal transitions receive a server-assigned `occurred_at`; ordinary forms cannot backdate them. An import or correction workflow may supply a historical occurrence time only through explicit validation. `created_at` always records when the event entered this system.

Status events are append-only but may be voided with `voided_at`, `voided_reason`, and `voided_by_user_id`. Voiding preserves the original event, requires a reason, and recalculates the current prospect status from the latest valid event in the same transaction. Reports ignore voided events. To preserve an intelligible transition chain, the MVP may void only the latest valid event; correcting an older event requires voiding later events in reverse order and then reapplying any valid transitions. A prospect's initial creation event cannot be voided while the prospect remains active.

### 8.5 Interactions

An interaction records what occurred, not what should occur. It includes a type, direction, timestamp, summary, outcome, prospect, and optional contact.

Deleting an interaction is not a normal UI operation. A material correction creates a new interaction whose `supersedes_interaction_id` references the original, then sets the original's `voided_at`, `voided_reason`, and `voided_by_user_id` in the same transaction. A replacement preserves its own business `occurred_at` and system `created_at`. Last-contact, response, recent-activity, and other derived values use valid interactions only and are recalculated transactionally.

F07 owns the canonical set of interaction outcomes that qualify as a meaningful response. Automated replies, bounces, wrong-recipient responses, and unrelated inbound activity do not qualify. F10 consumes that shared rule rather than defining another outcome list.

`last_contact_at` is derived from the most recent qualifying interaction, not typed independently. Its precise qualifying types will be fixed in the interaction feature specification.

### 8.6 Follow-ups and next actions

A follow-up contains an action description, due timestamp, status, and optional opportunity association. Action and due timestamp must both be present. Status keys are `open`, `completed`, and `cancelled`.

A prospect relationship may have at most one open follow-up. Creating or rescheduling a next action operates on that authoritative follow-up; it does not write separate next-action fields to prospects or opportunities. Completing or cancelling it preserves the record and its outcome history.

MySQL/MariaDB enforces the rule with a generated nullable `open_slot`: it is `1` for an open row and `NULL` otherwise, with a unique key on (`prospect_id`, `open_slot`). The F08 specification must confirm compatible generated-column syntax for the deployed database version.

Due classification uses the owner's configured timezone:

- `overdue`: before the start of today.
- `today`: within the current local calendar day.
- `upcoming`: after today, normally restricted to the next seven days in dashboard views.
- `unscheduled`: no open follow-up.

Store timestamps in UTC and convert at the application boundary. Date-only campaign values remain date-only.

Before an opportunity exists, the follow-up belongs to the prospect only. Once an open opportunity exists, new commercial follow-ups also reference that opportunity. When `opportunity_id` is present, the referenced opportunity must belong to the same prospect; the application service validates this, and F08 must use a composite database constraint where supported by the finalized schema. Opening, closing, or reopening an opportunity does not silently delete a follow-up; F08/F09 must define whether an existing open follow-up is retained, reassociated, completed, or explicitly cancelled.

### 8.7 Opportunities

Only a prospect in `qualified` status may receive an opportunity. The MVP intentionally permits at most one open opportunity per prospect to keep the daily workflow unambiguous. Won and lost opportunities remain historical, and a later opportunity may be opened for the same prospect.

MySQL/MariaDB enforces the rule with a generated nullable `open_slot`: it is `1` for nonterminal stages and `NULL` for won/lost rows, with a unique key on (`prospect_id`, `open_slot`). F09 must confirm compatible generated-column syntax for the deployed database version.

Offer keys:

- `technical_discovery`
- `product_development`
- `software_rescue`
- `systems_integration_automation`
- `applied_ai`

Stage keys:

`qualified`, `discovery_offered`, `proposal_sent`, `won`, `lost`

Won and lost are terminal stages. Closing requires `closed_at`; lost additionally requires a reason. Reopening is an explicit operation and must clear or preserve closure data according to the opportunity feature specification.

Every successful stage change writes an `opportunity_stage_events` row in the same transaction. The current stage remains on `opportunities` for efficient reads; event history is authoritative for milestone reporting. Reopening is not required by the MVP unless F09 explicitly includes it; if omitted, a new later opportunity is used instead.

Normal stage transitions receive a server-assigned `occurred_at`; ordinary forms cannot backdate them. Explicit import/correction paths may supply a historical time with validation, while `created_at` records when the event entered the system.

Stage events are append-only but may be voided with `voided_at`, `voided_reason`, and `voided_by_user_id`. Voiding requires a reason, preserves the original event, recalculates the current stage and close metadata from valid history in the same transaction, and may not leave the opportunity in a structurally invalid state. To preserve the transition chain, the MVP may void only the latest valid stage event; older corrections require voiding later events in reverse order and reapplying valid transitions. Reports ignore voided events.

Money is stored as fixed-precision decimal in one configured currency, initially USD. Floating-point values are not used for persistence or business calculations.

### 8.8 Archive behavior

- Companies, contacts, and prospects support soft archive through `archived_at`.
- Archived records are excluded from normal lists and current-state metrics such as selected prospects, open pipeline, and due follow-ups.
- Valid historical interactions and milestone events remain in activity metrics after their parent is archived. Archiving controls current visibility; it does not rewrite campaign history.
- Invalid activity is removed from reporting only through its explicit void/correction workflow, never merely by archiving a parent.
- Archiving a parent with active dependent records requires an explicit policy in its feature specification; silent cascading archive is prohibited.
- Restoring a record must re-run uniqueness and dependency checks.

## 9. Data architecture

### 9.1 Database conventions

- MySQL/MariaDB with InnoDB, foreign keys, and `utf8mb4`.
- Signed or unsigned integer primary keys chosen consistently in the first migration.
- UTC `DATETIME` timestamps for business events and audit timestamps.
- `DATE` for campaign boundaries and expected close dates when time-of-day is irrelevant.
- `DECIMAL(12,2)` for opportunity value.
- Every externally mutable aggregate/record has an unsigned integer `version NOT NULL DEFAULT 1`. Updates increment it atomically with `WHERE id = ? AND version = ?`; timestamps and representation hashes are never the authoritative concurrency token.
- Nullable fields only when absence has defined business meaning.
- Index foreign keys and fields used for active campaign, status, stage, archive, due-date, and chronological queries.
- Migrations are immutable after use; corrections receive a new migration.

Versioned records include application settings, campaigns/targets, integration clients, companies, contacts, prospects and signal evidence, interactions and voidable milestone events, follow-ups, and opportunities. Creation returns version `1`. A successful compound command increments every existing aggregate it mutates exactly once, even if several fields or child events change inside that command.

The singleton `application_settings` row (`id = 1`) holds nullable `active_campaign_id` and is the sole source of truth for campaign activation. Campaign selection locks and updates that row transactionally, so concurrent requests cannot create competing active campaigns. If no campaign is selected, the owner is directed to campaign setup. A missing or duplicated singleton row is an integrity error; campaign-dependent workflows never choose an arbitrary campaign.

### 9.2 Core schema ownership

| Table | Owning feature | Important relationships |
| --- | --- | --- |
| `users` | Authentication | Session identity |
| `application_settings` | Application foundation/campaign configuration | Singleton active campaign and owner timezone |
| `integration_clients` | External access foundation | Trusted issuer/client mapping, optional expected subject policy, status, and maximum allowed scopes |
| `integration_access_events` | External access foundation | Append-only sanitized access/failure telemetry with bounded retention; not authorization state |
| `idempotency_records` | External access foundation | Shared API/MCP client/key fingerprint and replayable write outcome |
| `audit_events` | Application foundation/external access | Append-only attribution including owner, integration client, external subject, grant type, issuer, operation, entity, and correlation data |
| `campaigns` | Campaign configuration | Parent of targets and prospects |
| `campaign_targets` | Campaign configuration | Unique metric per campaign |
| `companies` | Companies and contacts | Parent of contacts and prospects |
| `contacts` | Companies and contacts | Optionally belongs to company; otherwise independent |
| `prospects` | Prospects and research | Links campaign, company, primary contact |
| `prospect_status_events` | Prospects and research | Append-only prospect milestone history with explicit void metadata |
| `signals` | Prospects and research | Controlled lookup |
| `prospect_signals` | Prospects and research | Evidence join between prospect and signal |
| `interactions` | Interactions | Belongs to prospect and optional contact; corrections link and void originals |
| `follow_ups` | Follow-ups and daily work | Belongs to prospect and optionally its opportunity; generated open-slot uniqueness |
| `opportunities` | Opportunities | Belongs to qualified prospect |
| `opportunity_stage_events` | Opportunities | Append-only opportunity milestone history with explicit void metadata |

Detailed columns, constraints, and indexes belong in the owning feature specification and migration design. The build plan's initial field list remains the starting point.

Externally authenticated claims are mapped to an enabled `integration_clients` row before access is granted. Effective permissions are the intersection of token scopes and the client's locally allowed scopes. Delegated tokens retain both the software client identity and validated human `sub`; machine tokens record the client identity and explicitly have no human actor. Revoking the local client blocks future API and MCP access and idempotent replays even if an upstream token has not expired.

`integration_clients` contains authorization/configuration state only. Request activity never updates that row or increments its version. Sanitized `integration_access_events` provide last-use and recent-failure telemetry through append-only records with bounded retention, keeping authentication traffic from creating a versioned hot row. These events are operational telemetry, not a substitute for permanent domain audit events.

External actor/audit context includes `integration_client_id`, validated external subject when present, grant/authorization mode, trusted issuer, effective scopes, and correlation ID. These values come only from validated token claims and server configuration; API fields and MCP tool arguments cannot supply or override them.

### 9.3 Controlled vocabulary

`config/sales.php` is the canonical application definition for stable keys, default labels, display order, and allowed transitions for:

- Prospect segments and statuses.
- Signal seed identifiers.
- Interaction types, directions, and outcomes.
- Offers and opportunity stages.
- Campaign metric definitions.

Database constraints should protect structural integrity, while PHP domain validation protects changeable business vocabulary. Do not duplicate label lists in templates or JavaScript. When the browser needs them, render them from PHP as HTML options or a narrowly scoped JSON data attribute.

Campaign target rows store only the campaign-specific target value and metric key. Labels and display order come from the canonical metric definition unless a later feature explicitly introduces customizable labels.

### 9.4 Derived data

The following are calculated rather than manually editable:

- Campaign day number.
- Due classification.
- Funnel and target progress counts.
- Open pipeline and won totals.
- Last-contact value.
- Recent activity, assembled from dated interactions, status events, stage events, follow-up outcomes, and record creation events defined by F10.

A denormalized cache may be added only with an invalidation strategy and a demonstrated performance need. MVP queries should calculate directly from normalized, non-voided records. Cached current status/stage fields are recalculated from valid event history whenever history is corrected.

## 10. HTTP and interface conventions

### 10.1 Route style

Use explicit, resource-oriented browser routes with separate GET and POST handlers. Example route families:

```text
GET  /login
POST /login
POST /logout

GET  /dashboard
GET  /prospects
GET  /prospects/new
POST /prospects
GET  /prospects/{id}
GET  /prospects/{id}/edit
POST /prospects/{id}/update
POST /prospects/{id}/archive
POST /prospects/{id}/restore

POST /prospects/{id}/interactions
POST /prospects/{id}/opportunities
POST /opportunities/{id}/update
```

Final routes are owned by feature specifications. State changes must not use GET.

### 10.2 Form lifecycle

1. GET renders a form with current values and CSRF token.
2. POST normalizes and validates input.
3. Invalid input returns status 422 with entered values and field/summary errors.
4. Edit/action forms submit hidden expected versions for every existing aggregate the command may mutate. A stale form renders a conflict with current data and never silently overwrites an API/MCP change.
5. Valid input executes one application use case inside any required transaction.
6. Success follows Post/Redirect/Get and shows a one-time message.

### 10.3 Response rules

- HTML is the default representation.
- Unauthenticated HTML requests redirect to login; enhanced requests receive an appropriate 401 response.
- Missing records return 404 without revealing sensitive internal details.
- Business conflicts return 409 or a form-level conflict response.
- Validation failures return 422.
- Unexpected errors return a generic 500 page and are logged with a correlation identifier.
- Authenticated API/MCP responses containing business data use `Cache-Control: private, no-store` unless a feature specification proves a safe narrower cache policy. Public metadata uses its separately defined cache policy.

### 10.4 List query conventions

List features use consistent query names where applicable:

- `q` for free-text search.
- `status`, `segment`, `signal`, or `stage` for filters.
- `due` for follow-up classification.
- `sort` and `direction` for allowed sort keys.
- `page` for pagination.
- `archived` to deliberately include archived records.

Unknown or invalid values fall back safely and never become raw SQL fragments.

### 10.5 Deployment and base path

The deployment model serves the project folder through its root `index.php`. The application does not assume XAMPP paths, a particular domain, or installation at the web root. A deployment-specific configuration is conceptually:

```apache
DocumentRoot "/deployment-specific/path/to/project"
```

If the application must remain reachable below a path such as `/conversions`, the base path is supplied by configuration and used by the router and URL generator; templates must not hard-code root-relative URLs.

The root `.htaccess` is part of the deployment contract. Deployment verification must confirm that `src/`, `config/`, `database/`, `templates/`, `tests/`, `var/`, `docs/`, and `vendor/` cannot be fetched over HTTP.

### 10.6 Versioned JSON API contract

The external API is a first-class integration contract, not the private endpoint used by browser enhancements.

- Base path: `/api/v1`.
- Media type: JSON using UTF-8; errors use `application/problem+json` with stable machine-readable problem types.
- Documentation: a sanitized OpenAPI 3.1 document is publicly served at `/api/openapi.json`, contains no real business data or secrets, and is versioned with the implementation. Responses identify the effective API version through a documented response header.
- Authentication: OAuth bearer access token accepted only over HTTPS.
- Authorization: every operation declares one or more required scopes.
- Pagination: cursor-based for chronological/activity collections and stable page-based or cursor-based pagination defined consistently for record lists.
- Filtering and sorting: allowlisted parameters matching the project's list-query conventions.
- Concurrency: mutable resources expose their authoritative integer version. API responses also provide a strong, opaque ETag bound to resource identity, version, and representation variant so different scope/include projections do not incorrectly share a strong validator. The server securely extracts/maps the underlying version from an issued ETag; representation hashes and timestamps are never the concurrency authority. Updates require `If-Match` and return `412 Precondition Failed` with canonical code `stale_version` for stale clients.
- Idempotency: externally initiated create, transition, completion, correction, archive, and import-commit operations require an `Idempotency-Key` header. Reusing a key with a different request fingerprint returns a conflict; an identical authorized retry returns the recorded outcome. Local revocation and current scope checks occur before replay.
- Correlation: accept or generate a request ID and return it in the response and audit event.
- Rate limiting: network/global limits protect public metadata and unauthenticated/invalid-token paths before authentication; per-client limits apply after authentication. Rejections use `429 Too Many Requests` with retry guidance where safe. Production enforcement uses an authoritative shared store, Apache/reverse proxy, or gateway selected in F14; per-process PHP memory is not acceptable under multiprocess Apache.
- Dates and money: RFC 3339 timestamps with offsets/UTC, ISO date strings for date-only values, and decimal money serialized as strings with an explicit currency.

API resources include campaigns/targets, companies, contacts, prospects/signals/status history, interactions, follow-ups, opportunities/stage history, and reporting summaries. Data import/export and correction/archive endpoints require elevated scopes. Credential administration is browser-owner-only in the MVP and is not exposed through the external API.

Representations use least-privilege field projection. A scope for one resource does not implicitly reveal embedded fields from another resource: for example, `prospects:read` does not reveal contact email/phone without `contacts:read`, interaction summaries without `interactions:read`, or opportunity values without `opportunities:read`. Includes/expansions are explicit, allowlisted, scope-checked, and bounded. MCP tools apply the same projection policy and return only fields needed for the requested capability.

The same boundary applies to input. A client with `prospects:write` cannot create or update embedded contact fields without `contacts:write`, alter an opportunity without its scope, or smuggle unauthorized nested changes through a broader command. Unauthorized fields are rejected with a stable error rather than silently ignored. Application commands receive already-authorized field sets and still enforce domain invariants.

The API exposes business operations rather than unrestricted row mutation. For example, opportunity advancement, interaction correction, and follow-up completion use dedicated commands so clients cannot bypass transition or audit rules.

API versioning applies to external representations and behavior. Additive optional fields do not require a new major version; removing/renaming fields, changing meanings, or tightening previously valid inputs requires a new version or a documented deprecation window. Domain evolution does not automatically require an API version change when the external contract remains compatible.

Idempotency storage contains the client/key, operation, request fingerprint, status, affected entity/version, and minimum replay metadata. It does not retain full sensitive response bodies unless F15 demonstrates and protects that need. F14 defines retention and cleanup. Expired records may be removed without deleting permanent audit events.

The idempotency request fingerprint is computed from the operation identifier plus canonicalized, normalized, validated input—not raw JSON bytes, object-key order, volatile headers, or correlation ID. Idempotency keys have documented character/length limits and are unique per integration client across all operations. The published retention window defines how long duplicate-execution protection is guaranteed.

Idempotent processing order is mandatory for API and MCP:

1. Apply network/global abuse limits and basic request-size/shape checks before expensive authentication work.
2. Authenticate and validate issuer/audience/claims.
3. Check the local client, kill switch, current effective scopes, and operation permission.
4. Apply the authenticated per-client rate policy.
5. Canonicalize input and look up the idempotency key.
6. Replay an identical completed outcome; reject the same key with a different fingerprint.
7. For a new key, lock records and check every required resource version.
8. Execute the command, version increments, domain events, audit event, and idempotency outcome transactionally.

An idempotent replay does not create another domain-mutation audit event. It may create a redacted request/access log entry. Current revocation and authorization always take precedence over replay.

The MVP uses one transaction for idempotency and its bounded synchronous command. A new idempotency row is inserted/locked inside the same transaction as version checks, domain mutation, domain events, audit event, and completed replay outcome. Concurrent first use of the same client/key is serialized by the unique constraint and database locking: exactly one request executes, while another waits for commit and replays the outcome. If the wait reaches a configured database lock timeout, it returns retryable `idempotency_in_progress`; it never executes concurrently. Rollback removes the uncommitted reservation, so the MVP has no durable in-progress state, abandoned reservation, lease, or recovery workflow.

The replay envelope is immutable and minimal: operation, result status, affected entity identifiers, resulting versions, protocol status/code, and safe location/reference metadata. A replay returns that original envelope, not a newly rendered current resource. Clients fetch the current representation separately if needed.

F14 defines a precondition set for every mutating capability. The application command requires it regardless of whether the caller is HTML, API, MCP, or import code, so no adapter can bypass concurrency protection. A command that mutates multiple existing aggregates requires an expected version for each one—for example, recording an interaction may require the prospect version and, when rescheduling it, the existing follow-up version. Creation-only children have no prior version. All preconditions are checked after rows are locked inside the same transaction; any mismatch aborts the entire command with `stale_version` and no partial mutation.

F14 also owns stable transport-neutral outcome codes such as `validation_failed`, `not_found`, `conflict`, `stale_version`, `insufficient_scope`, `idempotency_conflict`, `idempotency_in_progress`, and `rate_limited`. F15 maps them to HTTP/problem responses and F16 maps them to structured tool/JSON-RPC results without changing their business meaning.

### 10.7 MCP contract

The application provides a remote MCP server at `/mcp` using the current stable Streamable HTTP transport. The initial implementation targets MCP protocol version `2025-11-25`, negotiates supported versions during initialization, and returns a protocol error for unsupported versions. Changing the supported baseline requires a compatibility review and decision record.

The MVP MCP server is stateless and uses JSON responses where the protocol permits; it does not require durable MCP sessions, SSE notifications, sampling, elicitation, prompts, or experimental long-running tasks. The endpoint implements the required POST/GET behavior for the selected Streamable HTTP profile and returns standards-compliant method responses when a capability is unsupported.

F16 uses the official MCP PHP SDK for protocol messages, Streamable HTTP, JSON Schema handling, protected-resource metadata, and OAuth/JWT middleware, subject to an initial compatibility check covering the deployed PHP version, Apache request lifecycle, dependency license, and required `2025-11-25` features. If that check fails, replacing the SDK or hand-implementing protocol behavior requires a new decision record and conformance plan.

The SDK is installed through Composer with an explicit compatible version constraint and committed lock file. F16 records license review, runs dependency security auditing, and pins the negotiated MCP baseline. SDK upgrades require contract/conformance tests and review of changed defaults; they must not silently enable sessions, SSE, prompts, sampling, elicitation, tasks, or other deferred capabilities.

MCP capabilities:

- **Tools:** expose approved commands such as searching/reading prospects, creating researched prospects, recording/correcting interactions, scheduling/completing follow-ups, qualifying prospects, creating/advancing opportunities, and reading campaign reports.
- **Resources:** expose read-only active-campaign context, controlled vocabulary, and selected record/report representations when this materially improves agent context.
- **Prompts:** deferred; the application does not prescribe an agent's sales language in the MVP.

Tool and resource names, descriptions, input schemas, output schemas, required scopes, side-effect annotations, and error mappings are specified in F16. Mutating tools execute the same application commands as the corresponding API operation and require `idempotency_key` plus the F14-defined expected-version set for every existing aggregate the command may mutate. These versions map to the same application preconditions as API `If-Match`; stale calls return canonical code `stale_version` without mutation. High-impact tools require their elevated scope and an explicit confirmation argument.

MCP `tools/list` returns only tools permitted by the token's current effective scopes. Conditional fields/includes remain runtime-authorized. MCP list/search tools use bounded page sizes and opaque, integrity-protected continuation cursors bound to the integration client, operation, filters, sort order, and expiry; no tool may return an unbounded collection. API cursors follow the same integrity rules. Tool results containing prospect, contact, website, interaction, or imported text treat that content as untrusted user/business data and never interpolate it into server instructions, tool descriptions, or protocol metadata.

The MCP adapter must:

- Validate a present `Origin` against an explicit allowlist and return the protocol-required rejection for a disallowed or malformed value. F16 defines standards-conforming handling for non-browser clients that omit `Origin`, and also validates the Host header/canonical endpoint to prevent DNS-rebinding and host-confusion attacks.
- Validate bearer token audience for the canonical MCP resource URI; token passthrough is forbidden.
- Publish OAuth Protected Resource Metadata and return compliant authentication challenges.
- Honor the negotiated `MCP-Protocol-Version` behavior.
- Return JSON-RPC protocol errors separately from business/tool execution errors.
- Avoid leaking stack traces, SQL, credentials, internal paths, or unrelated record data in tool results.
- Apply the same rate limit, request correlation, audit attribution, and local client revocation rules as the JSON API.

MCP is an adapter, not an agent runtime. The server does not autonomously choose prospects, send outreach, or run background campaigns. Outside parties build and operate clients that decide which exposed tools to call.

### 10.8 External authorization and scopes

Remote API and MCP access uses OAuth 2.1-compatible bearer tokens issued by a standards-compliant authorization server. The application acts as a protected resource and does not implement password grants, accept browser session cookies as API credentials, or build an authorization server from scratch.

The MCP resource publishes OAuth 2.0 Protected Resource Metadata and supports authorization-server discovery. Tokens are short-lived, audience-bound to the intended API/MCP resource, validated for issuer, audience, signature, expiry, and scopes, and never forwarded to downstream services. Authorization Code with PKCE supports clients acting for the owner. Machine-to-machine grants require explicit support in the relevant external-interface contract and authorization provider; MCP additionally requires its client-credentials extension below.

Machine-to-machine MCP authorization is enabled only by explicitly adopting `io.modelcontextprotocol/oauth-client-credentials`, advertising the extension as specified, and verifying compatible client and provider support. JWT bearer assertions are preferred over long-lived shared client secrets when the provider supports them. Without that extension, MCP clients use the standard interactive authorization flow with PKCE.

Canonical scope families:

- `campaigns:read`
- `companies:read`, `companies:write`
- `contacts:read`, `contacts:write`
- `prospects:read`, `prospects:write`
- `interactions:read`, `interactions:write`
- `followups:read`, `followups:write`
- `opportunities:read`, `opportunities:write`
- `opportunities:close`
- `reports:read`
- `records:correct`, `records:archive`
- `data:import`, `data:export`

F14 may split a scope further when a materially different risk exists, but must not create transport-specific equivalents. The same scope authorizes the same capability through API and MCP. Read scopes never imply write access, and ordinary write scopes do not imply correction, archive, import, export, or credential administration.

F14 owns a high-impact operation matrix used by both adapters. At minimum:

| Operation | Additional requirement |
| --- | --- |
| Mark opportunity won/lost or reopen | `opportunities:close`, current version, explicit confirmation |
| Void/correct history | `records:correct`, current version, reason, explicit confirmation |
| Archive/restore a record | `records:archive`, current version, explicit confirmation |
| Commit an import | `data:import`, preview token, idempotency key, explicit confirmation |
| Export sensitive campaign data | `data:export`; response projection still follows granted read scopes |
| Close prospect as no fit | `prospects:write`, current version, explicit confirmation |

Qualification, primary-contact reassignment, and follow-up cancellation remain ordinary writes only if their detailed feature specifications determine that their consequences are reversible and fully audited; otherwise F14 adds a narrower scope or confirmation rule before exposure.

An explicit confirmation flag proves only that the caller deliberately selected the high-impact operation; an AI agent can set it and it is not evidence of contemporaneous human review. Elevated scopes represent the owner's prior authorization of that client. True per-operation human approval would require a pending-approval workflow and is deferred unless a feature specification explicitly adds it. UI and integration documentation must not describe a confirmation flag as human approval.

External-client onboarding is explicit:

1. The outside party registers or identifies its OAuth client through the chosen authorization provider's supported process.
2. The owner creates a local integration-client record containing the trusted issuer, client identifier, expected subject where applicable, display name, and maximum scopes.
3. The client obtains an audience-bound token from the provider.
4. The application validates the token and local mapping on every request.
5. The owner may reduce scopes or revoke the local mapping without waiting for upstream token expiry.

Dynamic client registration is not required by this application unless the selected provider and F14 explicitly support it. Unknown OAuth clients are denied by default.

F14 cannot be accepted until one authorization provider and a localhost development strategy are selected and verified for issuer/audience claims, PKCE, resource indicators, scopes, JWKS rotation, client registration, and any adopted client-credentials extension.

The provider contract fixes the exact validated-claim mapping for software client identity, delegated owner subject, machine workload identity, authorization/grant mode, audience, and scopes. It explicitly defines precedence and required presence for claims such as `client_id`, `azp`, `sub`, and provider-specific grant indicators. Ambiguous or incomplete identity claims are rejected; the application never guesses actor identity from whichever claim happens to exist.

External integration development uses a separate non-production deployment with its own database, synthetic data, base URLs, OAuth audience/resource identifiers, clients, and secrets. Production credentials are invalid in development and development credentials are invalid in production. The development environment provides reset instructions and representative OpenAPI/MCP examples without copied production data.

## 11. Presentation and UX architecture

### 11.1 Navigation model

Primary navigation:

- Dashboard
- Prospects
- Opportunities
- Companies
- Data tools
- Campaign settings
- Integrations
- Logout

The dashboard is the post-login home page. A consistent page header provides the title, brief context, and primary action.

The owner-only Integrations screen shows each local client mapping, trusted issuer/client identity, expected-subject policy, maximum scopes, status, environment, and revocation controls. Last successful use and recent redacted failures are derived from bounded `integration_access_events`, never by mutating the versioned client row. The screen never displays tokens, raw logs, or provider-held secrets.

### 11.2 Shared components

Build shared view partials only for recurring UI contracts:

- Field, error, and validation summary.
- Flash message.
- Status/stage badge.
- Next-action display.
- Pagination.
- Empty state.
- Filter bar.
- Confirmation dialog/form.
- Campaign progress card.

Components accept prepared values and do not fetch data.

### 11.3 Accessibility baseline

- Keyboard-accessible navigation and controls.
- Visible focus styles.
- Proper labels and descriptions for inputs.
- Validation summary linked to invalid fields.
- Semantic headings, tables, lists, and buttons.
- Status is never conveyed by color alone.
- Dialog focus is managed and returned correctly.
- Minimum useful layouts at phone and desktop widths.
- Respect reduced-motion preferences.

### 11.4 CSS organization

Use native CSS with a small token layer for color, spacing, typography, borders, and layout widths. Prefer classes over element-specific nesting. Avoid page-specific duplication; promote a pattern to `components.css` only after reuse is clear.

No CSS framework, preprocessor, or build pipeline is required.

### 11.5 JavaScript organization

Use ES modules if supported by the deployed browser target, otherwise small deferred scripts with isolated initialization. Modules should discover behavior through `data-*` attributes and fail safely when markup is absent.

Avoid a global mutable application state. Server-rendered HTML and the database remain authoritative.

## 12. Reporting contracts

Dashboard metrics are defined once by stable metric keys. Each metric needs a precise query definition in the dashboard/reporting feature specification.

Not every funnel metric requires a numeric campaign target. In particular, response count and response rate are reported even though the initial business plan did not assign them a target.

At the project level:

- `selected_prospects`: non-archived prospects in the active campaign.
- `personalized_contacts`: prospects with at least one qualifying outbound interaction in the campaign period.
- `partners_contacted`: contacted prospects in the partner segment.
- `network_contacts`: contacted prospects in the network segment.
- `prospects_responding`: distinct prospects with at least one valid inbound interaction whose F07-owned outcome qualifies as a meaningful response during the campaign window.
- `sales_conversations`: distinct prospects whose status event history first reaches `conversation` during the campaign window.
- `qualified_opportunities`: distinct opportunities whose first `qualified` stage event occurs during the campaign window.
- `offers_sent`: distinct opportunities whose first `discovery_offered` or `proposal_sent` stage event occurs during the campaign window.
- `contracts_won`: distinct opportunities whose first `won` stage event occurs during the campaign window.

Counts represent distinct prospects or opportunities, not raw interaction volume. A record must not inflate the same metric because it was edited, contacted repeatedly, or crossed a milestone more than once.

F10 also derives conversion rates between adjacent funnel milestones and prominently reports `sales_conversations ÷ personalized_contacts`, the campaign's primary diagnostic ratio. Zero-denominator rates display as unavailable rather than zero percent.

Reporting distinguishes two kinds of values:

- **Activity metrics** count valid, non-voided qualifying events whose occurrence timestamps fall within the active campaign's inclusive local-date window. Contacts, responses, conversations, qualifications, offers, and wins are activity metrics. Archiving a parent later does not remove these historical events.
- **Current-state metrics** describe campaign records as they exist now, such as selected prospects, open pipeline value, overdue follow-ups, and current funnel distribution. They are labeled as current and may change after the campaign ends.

Reports operate in the active campaign. Current-state queries exclude archived parents, while activity queries include valid historical events regardless of later archive state. Event timestamps are stored in UTC and tested against campaign boundaries converted from the owner's timezone. Imported or deliberately backdated events count according to their validated occurrence time, not their creation time.

## 13. Import and export boundary

CSV is a transport format, not a second business-rule path.

### Import contract

- Upload to a non-public temporary location.
- Enforce configured file-size, row-count, encoding, and column limits.
- Parse and normalize into the same command/input objects used by manual entry.
- Preview inserts, potential existing-record matches, duplicates, warnings, and invalid rows before commit.
- Require explicit confirmation.
- Commit in a transaction according to the feature's atomicity policy.
- Never silently overwrite a conflicting existing record.

F12 must define deterministic matching keys before enabling updates. Until those keys are approved, imports may insert new records and flag possible duplicates, but may not update existing records automatically. Names alone are never sufficient update keys.

An external import preview returns a short-lived, integrity-protected preview token bound to the integration client, file/content hash, normalized operation plan, environment, and any existing-record versions. Commit requires that token, `data:import`, explicit confirmation, and an idempotency key; expired, altered, cross-client, cross-environment, or stale plans are rejected without partial import.

### Export contract

- Require authentication and an explicit user action.
- Generate UTF-8 CSV with stable documented headers.
- Export normalized business values, plus human-readable labels only when clearly named.
- Prevent spreadsheet formula injection in text values.
- Do not include password hashes, session data, internal stack traces, or secrets.
- Apply scope-aware field projection to external exports. Any generated download reference is random, short-lived, authenticated, bounded-use, tied to the requesting integration client, and audited; export files remain outside `public/` and are deleted after expiry.

## 14. Security architecture

### Authentication and sessions

- Use `password_hash()` and `password_verify()`.
- Regenerate the session identifier after authentication.
- Apply `HttpOnly`, `SameSite=Lax` or stricter, and `Secure` when served over HTTPS.
- Enforce inactivity and absolute session lifetimes defined in configuration.
- Logout invalidates the server session and cookie.
- Browser sessions are accepted only by browser routes and never authenticate `/api/v1` or `/mcp`.

### External authentication

- Validate OAuth token signature, issuer, audience/resource, expiry/not-before, subject/client identity, and scopes on every API and MCP request.
- Cache authorization metadata and signing keys only with bounded lifetimes and safe refresh behavior.
- Permit bounded last-known-good metadata/JWKS use during a provider discovery outage only while its configured freshness policy remains valid. Unknown key IDs, expired cache, invalid signatures, or unverifiable claims fail closed. A temporary discovery outage must not invalidate otherwise verifiable tokens solely because a safe cache refresh failed.
- Check the mapped local integration client is enabled and intersect token scopes with locally allowed scopes.
- Never log, persist in audit metadata, forward, or return bearer tokens, authorization codes, refresh tokens, client secrets, or raw credential headers.
- Require HTTPS for every non-localhost authorization, API, and MCP endpoint.
- Treat MCP and API as separate protected-resource audiences if they use distinct canonical resource URIs.
- Serve protected-resource metadata and required authorization-server discovery documents without bearer authentication, while applying safe cache and abuse controls; these documents contain configuration metadata only.
- Generate public OpenAPI/OAuth/MCP URLs from configured canonical origins, never arbitrary Host or forwarding headers. When deployed behind a trusted proxy, honor forwarded headers only from configured proxy addresses. F14 defines cache headers and invalidation for provider, audience, scope, or endpoint changes and prevents development metadata from advertising production resources or vice versa.

### Request protection

- Require CSRF tokens on every cookie-authenticated state-changing form and browser-enhancement request. Bearer-authenticated API/MCP requests do not use browser CSRF tokens and must not accept session-cookie fallback.
- Accept only declared HTTP methods and fields.
- Validate and normalize server-side.
- Use prepared statements for every data value.
- Escape HTML output by default.
- Validate redirect destinations rather than accepting arbitrary return URLs.
- Apply an explicit CORS allowlist to API endpoints; absence of a browser CORS permission does not replace authorization.
- Validate MCP `Origin` according to the selected protocol version and return the required rejection response for disallowed origins.
- Enforce payload size, nesting, batch, pagination, and execution-time limits before expensive processing.

### Data and deployment protection

- Credentials and environment-specific secrets remain outside the repository and web root.
- Apache denies direct access to configuration, source, templates, migrations, tests, logs, and documentation if the project root is web-accessible.
- Production error display is disabled; diagnostic details go to protected logs.
- CSV and log files are not stored under `public/`.
- The application follows least privilege for its database account.
- Integration-client administration and OAuth provider configuration are owner-only browser operations protected by reauthentication where the F14 specification requires it.
- Audit metadata uses an allowlisted structure and excludes secrets and unnecessarily sensitive request bodies.
- Successful mutations and their audit events commit atomically. If the durable audit write fails, the business mutation fails and rolls back.
- Authentication failures and rate-limit events go to redacted security logs. Authorized business denials and conflicts are audited according to F14 without storing sensitive request bodies.

## 15. Reliability and operations

### Error handling

Expected validation and business conflicts produce useful user messages. Unexpected exceptions are logged once at the application boundary with request context and a correlation ID; sensitive values are redacted.

### Transactions

Use transactions for operations that must succeed together, including:

- Prospect creation with signal evidence.
- Interaction creation plus derived last-contact/status changes.
- Opportunity stage closure plus close metadata.
- Confirmed multi-record imports.
- Every successful domain mutation plus its audit attribution, and its idempotency outcome when applicable.

### Time

Inject a clock into time-sensitive services so due classification and reporting are deterministic in tests. Store the owner timezone in application configuration for MVP; default deployment value is `America/Los_Angeles`.

### Backup and recovery

The release documentation must define:

- A database backup command/process.
- Backup frequency and retention recommendation.
- Restore steps tested against a separate database.
- Which environment/configuration files require separate secure backup.

CSV export is portability/reporting support, not a substitute for a database backup.

### External-interface operations

- Monitor API/MCP authentication failures, rate-limit events, tool/operation error rates, latency, and revoked-client use attempts without logging secrets.
- Expire idempotency records according to a documented retention period while preserving permanent domain audit events.
- Define audit retention, redaction, integrity monitoring, backup, and owner-only access. External clients cannot read the audit log in the MVP. Audit maintenance never permits an integration client to erase evidence of its own activity.
- Retain `integration_access_events` only for the documented operational window, restrict them to the owner Integrations view/operations, and purge them independently of permanent domain audit events.
- Version and deploy OpenAPI and MCP tool schemas with the application release that implements them.
- Provide a kill switch that disables all external access without disabling the owner-facing browser application. Missing, unreadable, or invalid external-access configuration fails closed.

## 16. Testing strategy

### Unit tests

Cover pure business behavior:

- Readiness validation by segment.
- Allowed prospect and opportunity transitions.
- Event voiding and current-state recalculation.
- Next-action completeness and due classification.
- Close requirements and money handling.
- Metric definitions that can be evaluated without the database.
- Import normalization and row validation.
- Scope-to-capability mapping and external representation mapping.
- High-impact operation policy and delegated-versus-machine actor attribution.
- Version increments, compound precondition definitions, canonical idempotency fingerprints, and replay-envelope mapping.

### Integration tests

Cover boundaries:

- Migrations against an empty database.
- Repository queries and constraints.
- Generated single-open constraints and singleton settings-row locking.
- Authentication/session behavior.
- CSRF and route method protection.
- Full application service transactions.
- Reporting queries with fixtures that detect double counting.
- Archive-safe activity metrics and correction-safe derived values.
- Import preview and commit behavior.
- OAuth issuer/audience/scope validation, local revocation, and browser-session isolation.
- Public metadata/OpenAPI safety, provider discovery, JWKS rotation/failure, and external-access fail-closed behavior.
- API idempotency, ETag/precondition, scope-aware field projection, pagination, shared-store rate-limit, and problem-response contracts.
- MCP SDK compatibility/conformance, protocol negotiation, Origin/Host validation, initialization, bounded tool results, expected-version conflicts, tool/resource schemas, optional client-credentials extension, and JSON-RPC error separation.
- Browser/API/MCP parity fixtures proving each adapter invokes the same domain behavior.
- Retry-order tests proving identical completed requests replay before stale-version evaluation while revoked/under-scoped clients cannot replay.
- Compound-command tests proving one stale precondition rolls back all domain, version, event, audit, and idempotency changes.
- Signed-cursor tamper/client/filter/expiry tests; pre-auth and per-client limiter tests; development/production audience-isolation tests.

### Feature acceptance tests

Each feature specification must include user-visible scenarios and failure cases. The project-wide end-to-end path is:

1. Log in and configure a campaign.
2. Create a company, contact, and researched direct prospect.
3. Make the prospect ready, record outreach, and schedule a follow-up.
4. Observe it in the correct daily queue.
5. Record interest and a conversation, then qualify it.
6. Create and advance an opportunity to won.
7. Reconcile dashboard metrics to the underlying records.
8. Export the campaign data.
9. Repeat an approved write/read workflow through `/api/v1` and `/mcp`; verify equivalent domain state, distinct client attribution, and scope enforcement.

### Quality gates

Before a feature is considered complete:

- Its specification and acceptance criteria are implemented.
- PHP syntax checks pass.
- Relevant automated tests pass.
- Every state-changing route has the authentication, authorization, and request-forgery protection appropriate to its adapter.
- Server validation works without JavaScript.
- Primary success, empty, validation-error, not-found, and conflict states are usable.
- Keyboard and phone-width checks pass for affected screens.

## 17. Feature specification process

Create feature documents under `docs/features/` with stable numeric names, for example `F06-prospects-and-research.md`.

The canonical feature index and implementation dependencies are maintained in `docs/features/README.md`.

Each feature specification should contain:

1. Purpose and user outcome.
2. In-scope and out-of-scope behavior.
3. Dependencies and owned concepts.
4. User stories and acceptance scenarios.
5. Detailed fields, validation, defaults, and normalization.
6. State model and transitions, if applicable.
7. Routes, request fields, response/redirect behavior.
8. Screen structure and all UI states.
9. Database changes, constraints, indexes, and migration notes.
10. Application services and repository/query responsibilities.
11. Security, authorization, archive, and error behavior.
12. Reporting or cross-feature effects.
13. Automated and manual test cases.
14. Open decisions and explicitly deferred work.

F14–F16 specifications additionally document scopes, external schemas, idempotency/concurrency behavior, versioning compatibility, scope-aware projection, authoritative rate limiting, discovery metadata, actor attribution, high-impact operations, and contract-test fixtures. F14 selects the authorization provider/local-development strategy and owns the transport-neutral capability catalog. F15 owns the OpenAPI operation contract. F16 owns MCP SDK compatibility and protocol/tool/resource mappings without redefining domain inputs.

Feature documents should link to shared rules in this specification instead of copying them. If a feature exposes a missing architectural decision, record the decision when material and update this document before implementation; a decision record never substitutes for updating the current specification.

## 18. Decision records and change control

Use short records under `docs/decisions/` for choices that materially affect multiple features, such as changing the external authorization model, allowing multiple open opportunities, replacing the MCP transport profile, or adopting a framework.

Each decision record should state:

- Context and problem.
- Decision.
- Alternatives considered.
- Consequences and required specification changes.

Small implementation details do not require decision records.

## 19. Implementation increments

The architectural implementation order is:

1. **Foundation:** F01–F03.
2. **Campaign and records:** F04–F06.
3. **Daily workflow:** F07–F08.
4. **Commercial pipeline:** F09–F10.
5. **Usability and portability:** F11–F12.
6. **Operational completion:** F13.
7. **External access:** F14–F16, delivered first for one end-to-end workflow and then expanded to the approved use-case surface.

An increment is releasable only when its vertical workflow works through the browser and persists correctly. Avoid building all database tables, then all repositories, then all screens as isolated horizontal phases.

## 20. Project completion criteria

The MVP is complete when:

- The essential features F01–F16 meet their approved feature specifications.
- The owner can complete the end-to-end acquisition path without a parallel spreadsheet.
- Campaign and pipeline metrics reconcile to their source records.
- Data can be backed up, restored, imported, and exported safely.
- Authentication, CSRF, validation, escaping, and database protections are verified.
- Core workflows remain functional without JavaScript.
- The application is usable on current desktop and mobile browsers at the supported widths.
- An outside party can implement a client from the OpenAPI contract or MCP discovery/tool schemas without private implementation knowledge.
- Browser, API, and MCP adapters produce equivalent domain outcomes and attributable audit events for shared capabilities.
- Revocation, scopes, audience validation, idempotency, concurrency, rate limits, and external-interface kill switch are verified.
- Deferred features have not leaked into the MVP as incomplete abstractions.

The first post-MVP planning decision should be based on observed use during a real campaign, not assumptions made during initial development.
