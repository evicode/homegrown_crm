# Dreamsmith Contract Campaign — MVP Build Plan

## 1. Goal

Build a small internal web application that helps Dreamsmith Labs run the 30-day contract campaign described in `business_need.md`.

The MVP should answer four questions every day:

1. Who should I contact next?
2. Why is this person or company relevant?
3. What happened in the last interaction?
4. Which conversations are becoming paid work?

This is a workflow and record-keeping tool, not a marketing automation platform. Functionality, clarity, and reliable data come before visual polish.

## 2. MVP success criteria

The MVP is successful when one user can:

- Maintain partner, direct-prospect, and existing-network lists in one place.
- Record a specific reason or buying signal for every cold prospect.
- Move prospects through a simple outreach pipeline.
- See overdue and upcoming follow-ups.
- Record conversations, opportunities, estimated value, and outcomes.
- Track progress against the 30-day campaign targets.
- Export the campaign data as CSV.

The application should make it difficult to lose a follow-up or contact an unqualified cold prospect without recording why they were selected.

## 3. Scope

### Included

- Password-protected, single-user application.
- Dashboard with campaign progress and today's work.
- Company and contact records.
- Three prospect segments: partner, direct prospect, and existing network.
- Buying signals and a required "Why them?" note for cold prospects.
- Interaction history for email, phone, meeting, LinkedIn, and notes.
- Follow-up records and a daily task list.
- Pipeline stages and opportunity value.
- Basic search and filters.
- CSV import for initial lists and CSV export for backup/reporting.
- Configurable campaign start/end dates and numeric targets.
- Scoped external-client access through a versioned JSON API, using a shared transport-neutral capability catalog.
- Remote MCP tools/resources for approved workflows, backed by the same application services.
- OpenAPI documentation and owner-managed integration-client enable/revoke controls.

### Explicitly deferred

- Automated email sending, sequencing, tracking, or templates with mail merge.
- LinkedIn, calendar, inbox, enrichment, or third-party CRM integrations.
- Automatic web research, lead scoring, or AI-generated outreach.
- Multiple users, permissions, assignments, and team reporting.
- Proposal generation, contracts, invoicing, or project delivery management.
- A public website or changes to dreamsmithlabs.com.
- Content publishing and referral-fee accounting.
- Webhooks, MCP prompts/sampling, autonomous background agents, and long-running MCP tasks.

These can be considered only after the manual process produces useful sales conversations.

## 4. Users and operating rules

The MVP has one role: **Owner**.

Business rules:

- A partner or direct prospect cannot be marked `Ready to contact` without a "Why them?" note.
- A direct prospect should also have at least one buying signal.
- Every completed interaction may create or reschedule the relationship's open follow-up.
- A record with a past follow-up date appears in `Overdue` until completed or rescheduled.
- Only qualified prospects should become opportunities.
- Closed opportunities require an outcome of `Won` or `Lost`.
- Records are archived rather than permanently deleted through the UI.

## 5. Core workflow

### A. Build the prospect list

1. Add or import a company.
2. Add its primary contact and role when known. Independent people may be entered without a company.
3. Choose the segment.
4. Record source, "Why them?", and any observed signal.
5. Mark the prospect ready when research is sufficient.

### B. Run daily outreach

1. Open the dashboard.
2. Work overdue follow-ups first.
3. Review today's scheduled contacts.
4. Record each interaction and result.
5. Set, complete, or reschedule the relationship's next follow-up.

### C. Qualify and convert

1. Change an interested prospect to `Conversation`.
2. Record discovery notes and the business problem.
3. If qualified, create an opportunity with offer, value, and next step.
4. Move it through `Qualified`, `Discovery offered`, `Proposal`, and `Won/Lost`.

### D. Review the experiment

Use the dashboard to compare contacts, responses, conversations, opportunities, proposals, and wins against campaign targets. Funnel milestones are backed by dated events so later status changes do not erase campaign history. The funnel should reveal where the process is failing without relying on vanity metrics such as opens or impressions.

## 6. Pipeline model

Use one prospect status field rather than separate status systems:

| Status | Meaning |
| --- | --- |
| Researching | Record exists but is not ready for outreach. |
| Ready to contact | Required research is complete. |
| Contacted | At least one outbound interaction was recorded. |
| Later | Relevant, but timing is not current. |
| Interested | The contact responded positively. |
| Conversation | A substantive sales conversation is underway. |
| Qualified | Problem, timing, buyer, and plausible budget are understood. |
| Closed — no fit | No active sales path remains. |

Opportunity stages are separate because not every prospect becomes an opportunity:

`Qualified` → `Discovery offered` → `Proposal sent` → `Won` or `Lost`

## 7. Campaign targets

Seed the campaign with editable targets from the business need:

| Metric | Default target |
| --- | ---: |
| Selected prospects | 175 |
| Personalized outbound contacts | 125 |
| Partners contacted | 50 |
| Existing-network contacts | 40 |
| Sales conversations | 16 |
| Qualified opportunities | 6 |
| Proposals or paid discovery offers | 3 |
| Contracts won | 1 |

Store one value per metric. Avoid duplicating target definitions in PHP, JavaScript, and HTML; render all progress displays from the stored configuration.

## 8. Screens and required behavior

### Login

- Email/password form.
- Secure session logout.
- No public registration or password reset in MVP; create the owner through setup/configuration.

### Dashboard

- Campaign day and date range.
- Progress cards for the target metrics.
- Funnel counts and estimated opportunity value.
- Overdue follow-ups.
- Actions due today and in the next seven days.
- Prospects ready for first contact.
- Recent activity.

### Prospects list

- Search company, contact, and notes.
- Filter by segment, status, signal, follow-up state, and archived state.
- Sort by next action date, last interaction, company, or estimated value.
- Quick links to add, edit, archive, and view details.

### Prospect detail/editor

- Company and contact information.
- Segment, source, status, why-them note, and signals.
- Business problem and qualification notes.
- Chronological interaction timeline.
- Current follow-up and prior follow-up outcomes.
- Opportunity summary when present.
- Validation messages that explain missing required fields.

### Add interaction

- Type, date/time, direction, summary, and outcome.
- Optional next action and due date, which creates or reschedules the relationship's open follow-up.
- On save, update the derived last-contact timestamp and record any automatic status transition as an event.

### Opportunities

- List/filter by stage and offer.
- Show company/person, primary contact when applicable, offer, estimated value, stage, current follow-up, and expected close date.
- Totals for open pipeline and won value.

### Campaign settings and data tools

- Campaign name and dates.
- Editable metric targets.
- CSV import with preview, validation, and a downloadable sample header.
- CSV export of prospects, interactions, and opportunities.

## 9. Data model

Use MySQL with InnoDB, foreign keys, `utf8mb4`, UTC timestamps, and integer primary keys.

Every externally mutable aggregate has an unsigned integer `version` starting at `1`. Each successful mutation increments it atomically; API ETags and MCP expected-version checks use this value rather than timestamps or representation hashes.

### `users`

- `id`, `email`, `password_hash`, `created_at`, `updated_at`

### `campaigns`

- `id`, `name`, `starts_on`, `ends_on`, `version`, timestamps

### `application_settings`

- Singleton row with `id = 1`, nullable `active_campaign_id`, `owner_timezone`, `version`, timestamps
- `active_campaign_id` is the sole source of truth for campaign activation.

### `integration_clients`

- `id`, trusted issuer/client identifiers, optional expected-subject policy, display name, allowed scopes, environment, `revoked_at`, `version`, timestamps
- Maps validated OAuth subjects to a locally enabled client and maximum permission set.
- Its version changes only when authorization/configuration state changes; request telemetry never mutates this row.

### `integration_access_events`

- `id`, nullable `integration_client_id`, environment, outcome class/code, operation/transport, correlation ID, safe network fingerprint where permitted, `occurred_at`
- Append-only, sanitized operational telemetry with bounded retention; contains no tokens, secrets, raw request bodies, or business record contents.
- Supplies last-use and recent-failure data without versioning or contending on `integration_clients`.

### `idempotency_records`

- `id`, `integration_client_id`, `idempotency_key`, operation, request fingerprint, status, affected entity/version, minimal replay metadata, expiry, timestamps
- Unique key on (`integration_client_id`, `idempotency_key`).
- Do not retain full sensitive response bodies unless explicitly justified and protected.
- The record is inserted/locked inside the same transaction as the bounded synchronous command, audit event, and completed replay outcome. No durable `in_progress` reservation is committed.

### `audit_events`

- `id`, actor type, nullable owner/client identifiers, nullable validated external subject, grant/authorization mode, trusted issuer, action, entity type/id, correlation ID, safe metadata, `created_at`
- Append-only attribution for state changes from the browser, API, MCP, import, and system processes.

### `campaign_targets`

- `id`, `campaign_id`, `metric_key`, `target_value`, `version`
- Unique key on (`campaign_id`, `metric_key`)
- Labels and display order come from the canonical metric configuration; only target values vary by campaign.

### `companies`

- `id`, `name`, `website`, `location`, `industry`, `employee_range`, `revenue_range`, `notes`, `archived_at`, `version`, timestamps

### `contacts`

- `id`, nullable `company_id`, `first_name`, `last_name`, `role`, `email`, `phone`, `linkedin_url`, `archived_at`, `version`, timestamps
- A contact may represent an independent person without a company.

### `prospects`

- `id`, `campaign_id`, nullable `company_id`, nullable `primary_contact_id`
- `segment` (`partner`, `direct`, `network`)
- `status`, `source`, `why_them`, `business_problem`, `qualification_notes`
- `last_contact_at`, `archived_at`, `version`, timestamps
- At least one of company or primary contact is required. If both are present, the contact must belong to the company.
- Duplicate prevention is enforced for company-only, contact-only, and company/contact identities through explicit validation and suitable database indexes; do not rely on one nullable composite unique key.

### `prospect_status_events`

- `id`, `prospect_id`, nullable `from_status`, `to_status`, `occurred_at`, `reason`, nullable `voided_at`, `voided_reason`, nullable `voided_by_user_id`, `version`, timestamps
- Every status change is recorded so funnel milestones survive later transitions. Mistakes are voided with a reason rather than deleted or overwritten.

### `signals`

- `id`, `name`, `is_active`
- Seed values such as funding, hiring, leadership vacancy, acquisition, product launch, legacy system, integration need, AI initiative, cloud migration, expansion, and manual workflow.

### `prospect_signals`

- `prospect_id`, `signal_id`, `evidence_note`, `observed_on`, `version`
- Composite key on (`prospect_id`, `signal_id`)

### `interactions`

- `id`, `prospect_id`, nullable `contact_id`, `type`, `direction`, `occurred_at`, `summary`, `outcome`, `version`
- Nullable `supersedes_interaction_id`, `voided_at`, `voided_reason`, `voided_by_user_id`, timestamps
- Corrections create a replacement and void the original; valid interactions alone drive response and last-contact calculations.

### `follow_ups`

- `id`, `prospect_id`, nullable `opportunity_id`, `action`, `due_at`, `status`, `completed_at`, generated nullable `open_slot`, `version`, timestamps
- Status values are `open`, `completed`, and `cancelled`.
- A unique key on (`prospect_id`, `open_slot`) database-enforces at most one open follow-up; `open_slot` is `1` only for open rows and `NULL` otherwise.
- Opportunity follow-ups use the same table rather than duplicating next-action fields. If `opportunity_id` is present, it must identify an opportunity belonging to the same prospect.

### `opportunities`

- `id`, `prospect_id`, `offer`, `stage`, `estimated_value`, `expected_close_on`, `lost_reason`, `closed_at`, generated nullable `open_slot`, `version`, timestamps
- Offers: Technical Discovery, Product Development, Software Rescue, Systems Integration & Automation, and Applied AI.
- A unique key on (`prospect_id`, `open_slot`) database-enforces at most one open opportunity; `open_slot` is `1` for nonterminal stages and `NULL` for won/lost rows.
- A later opportunity may be opened after the previous one is won or lost.

### `opportunity_stage_events`

- `id`, `opportunity_id`, nullable `from_stage`, `to_stage`, `occurred_at`, `reason`, nullable `voided_at`, `voided_reason`, nullable `voided_by_user_id`, `version`, timestamps
- Every stage change is recorded for historically accurate funnel reporting. Mistakes are voided with a reason rather than deleted or overwritten.

Keep enums or allowed-value definitions in one shared PHP configuration source. The database migration, server-side validation, and UI options should derive from or remain explicitly synchronized with that source.

## 10. Technical approach

Use the existing LAMP stack:

- Apache for routing and static assets.
- PHP 8+ for server-rendered pages, application services, the versioned JSON API, and the MCP adapter.
- MySQL/MariaDB accessed through PDO and prepared statements.
- Semantic HTML, plain CSS, and vanilla JavaScript.
- Progressive enhancement: core forms and navigation should work without JavaScript; use JavaScript for filters, dialogs, fetch-based quick actions, and import previews.

The canonical source tree and dependency direction are defined only in `project_specification.md`; this build plan does not maintain a second architecture. Use a small front controller and explicit route table. Configure Apache so `public/` is the document root; the repository root must not be publicly browsable. Support a configured base path for local subdirectory deployments. Avoid introducing a framework, build tool, SPA state layer, CSS framework, or JavaScript dependency unless the MVP demonstrates a concrete need.

## 11. DRY implementation boundaries

- Put database access in repositories/services, not page templates.
- Put validation and business transitions in server-side domain services; JavaScript may improve feedback but must not be authoritative.
- Reuse one form partial for create/edit prospect fields.
- Reuse shared components for status badges, empty states, errors, pagination, and next-action displays.
- Define segments, statuses, offers, stages, interaction types, and metric keys once in `config/sales.php`.
- Calculate dashboard metrics in one campaign reporting service.
- Use one CSV column mapping/import service and one validation path for both imported and manually entered data.
- Do not prematurely create generic abstractions; extract shared code only when the same rule or presentation is genuinely reused.

## 12. Security and reliability baseline

- Hash passwords with `password_hash()` and verify with `password_verify()`.
- Regenerate session IDs on login and use secure, HTTP-only, SameSite cookies.
- Require authentication for every application route except login.
- Use CSRF tokens for every state-changing request.
- Use PDO prepared statements; never concatenate user input into SQL.
- Escape output by default with `htmlspecialchars()`.
- Validate URLs, emails, dates, enum values, lengths, and monetary values on the server.
- Restrict CSV size and column count; display row-level import errors before committing.
- Wrap multi-record imports and coupled updates in transactions.
- Keep credentials outside the web root and out of version control.
- Provide friendly errors to the user and log diagnostic details server-side.

## 13. Delivery phases

### Phase 1 — Foundation and records

Deliver:

- Database migrations and seeds.
- Configuration and environment loading.
- Owner login/logout and protected routes.
- Shared layout/navigation.
- Company, contact, and prospect create/read/update/archive flows.
- Search and core filters.

Acceptance:

- The owner can securely sign in and manage all three prospect segments.
- Cold prospects cannot become ready without the required research fields.
- Archived records are hidden by default and remain recoverable.

### Phase 2 — Daily outreach workflow

Deliver:

- Interaction timeline and add-interaction flow.
- Next-action scheduling.
- Overdue, today, and upcoming dashboard lists.
- Status transitions and activity history.

Acceptance:

- Recording an interaction updates last contact correctly.
- Due and overdue records appear on the correct day.
- The owner can start from the dashboard and determine the next work without opening a separate spreadsheet.

### Phase 3 — Opportunities and campaign reporting

Deliver:

- Opportunity creation and stage management.
- Campaign targets and settings.
- Funnel, target progress, and value totals.

Acceptance:

- Dashboard figures can be reconciled to underlying records.
- Won/lost opportunities retain their outcome and close date.
- The owner can identify whether outreach, qualification, or closing is the weak point.

### Phase 4 — Import, export, and hardening

Deliver:

- CSV import preview/validation and export.
- Responsive/mobile usability pass.
- Empty states, validation feedback, error handling, and accessibility pass.
- Backup/restore documentation and release checklist.

Acceptance:

- A valid initial prospect spreadsheet imports without duplicate records.
- Invalid rows do not partially corrupt the campaign.
- All campaign data can be exported in readable CSV files.
- Primary workflows are usable at phone and desktop widths.

### Phase 5 — External API and MCP access

Deliver:

- OAuth protected-resource integration, local client mapping, scopes, revocation, and audit attribution.
- Transport-neutral capability schemas/policies shared by peer API and MCP adapters.
- Versioned `/api/v1` JSON operations for approved use cases.
- OpenAPI 3.1 documentation.
- Idempotency, optimistic concurrency, pagination, rate limiting, and external error contracts.
- Integer resource versions, multi-aggregate precondition sets, deterministic replay ordering, and minimal replay envelopes.
- Stateless Streamable HTTP MCP endpoint at `/mcp`, implemented with the official PHP MCP SDK after compatibility verification.
- MCP tools/resources mapped to the same application services and scopes as the API.
- Integration-client management screen for the owner.

Acceptance:

- An independently built client can discover the API contract, authenticate, and complete a scoped end-to-end prospect workflow.
- An MCP client can initialize, list permitted tools, read campaign context, and complete the same workflow without bypassing business validation.
- API and MCP writes produce the same state, events, derived metrics, and audit attribution as browser actions.
- Repeating an identical mutating request with the same idempotency key does not duplicate work.
- Stale writes, insufficient scopes, revoked clients, invalid audiences, and disallowed MCP origins are rejected safely.
- No external interface exposes credentials, password hashes, stack traces, unrestricted SQL-like filters, or browser-session authority.
- MCP mutations require the same expected version as API `If-Match` updates.
- Scope-aware projections prevent one resource scope from leaking another resource's sensitive fields.
- Machine-to-machine MCP access works only through the adopted client-credentials extension and a compatible provider/client.
- Separate synthetic-data integration environment with distinct database, audiences, clients, and credentials.
- Network/global pre-authentication limits plus shared per-client limits.
- Owner Integrations screen showing scopes, identity policy, environment, last use, redacted failures, and revocation.

## 14. Verification plan

### Automated checks

- PHP syntax checks for all PHP files.
- Unit tests for validation, pipeline transitions, due-date classification, void/correction behavior, and metric calculations.
- Integration tests for authentication, CSRF protection, OAuth token validation, scope enforcement, CRUD operations, single-open constraints, active-campaign locking, transactions, and CSV import.
- Contract tests for OpenAPI operations, scope-aware projections, external error shapes, idempotency/concurrency behavior, MCP initialization, tool schemas, OAuth discovery/extensions, and API/MCP parity.
- Tests for integer-version atomic updates, compound preconditions, retry-order determinism, canonical fingerprints, signed cursors, public metadata isolation, and development/production audience separation.
- Database migration test against an empty database.

### Manual acceptance scenarios

1. Add a direct prospect without a signal and confirm it cannot be marked ready.
2. Add the missing research, contact the prospect, and schedule tomorrow's follow-up.
3. Confirm the follow-up appears in the appropriate dashboard list.
4. Record a positive response, qualify it, and create a Technical Discovery opportunity.
5. Advance it to proposal and won; confirm funnel and value metrics update once.
6. Archive and restore a record without losing its interaction history or changing historical activity metrics.
7. Import a CSV containing valid, invalid, and duplicate rows and confirm the preview explains each result.
8. Export data and confirm the records can be opened and understood outside the application.
9. Correct an interaction and void a mistaken milestone; confirm the originals remain auditable while derived fields and metrics use only the valid records.
10. Attempt concurrent creation of open follow-ups/opportunities and confirm the database prevents duplicates.
11. Run the same scoped workflow through the browser, API, and MCP and confirm equivalent domain results and distinct actor attribution.
12. Revoke an integration client and confirm both API and MCP access stop immediately.
13. Submit a stale MCP mutation and confirm it fails exactly as the equivalent stale API update does.
14. Read a prospect without contact/interaction scopes and confirm neither API nor MCP leaks those fields.
15. Disable or corrupt external-access configuration and confirm API/MCP fail closed while the owner browser remains usable.
16. Retry a successful version-changing request with its original idempotency key and stale expected version; confirm the original result replays without a second mutation or domain audit event.
17. Submit a compound command with one stale aggregate version and confirm no aggregate, event, audit record, or idempotency success outcome partially commits.
18. Confirm production tokens fail against development and development tokens fail against production.

## 15. MVP completion definition

The MVP is complete when all five phases meet their acceptance criteria, the manual scenarios pass, the owner can run a full workday from the application without relying on a second prospect spreadsheet, and an outside party can build a client from the published API or MCP contracts.

After 30 days of real usage, review friction and conversion data before selecting any next feature. Likely candidates are email/calendar integration, reusable message snippets, partner referral tracking, and multi-user support, but none should be assumed before the workflow is validated.
