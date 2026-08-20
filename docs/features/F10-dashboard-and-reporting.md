# F10 — Dashboard and Reporting

## Purpose

Make today's work obvious and calculate a trustworthy campaign funnel, target progress, pipeline value, and diagnostic conversion rates.

## Dashboard sections

- Campaign name, inclusive dates, day number, and setup/ended state.
- Target progress cards.
- Current funnel distribution and open/won value totals.
- Overdue, today, next-seven-days, and ready-to-contact queues.
- Recent valid activity.
- Primary diagnostic ratio: sales conversations divided by personalized contacts.

## Metric definitions

Use root metric keys and event semantics. Activity metrics count distinct prospects/opportunities whose first qualifying valid event occurs within the campaign's local-date window. Current-state metrics exclude archived parents; historical activity survives later archive.

Responses use F07's meaningful-response mapping. Conversation/qualification/offer/win milestones use non-voided event history. Repeated events do not double count.

Rates:

- Contact → response
- Response → conversation
- Contact → conversation (primary)
- Conversation → qualified opportunity
- Qualified opportunity → offer
- Offer → won

Zero denominators display unavailable, never misleading zero percent.

## Value calculations

- Open pipeline: sum values for nonterminal opportunities.
- Won value: sum won opportunities in applicable campaign/current view.
- No floating point; repository returns decimal-safe values.
- Archived current records leave current totals but valid won activity remains historical.

## Routes

- `GET /dashboard`
- `GET /reports/campaign`: fuller reconciliable report.
- Drill-down links reuse F11 filters and show the exact source records behind a count.

## Query architecture

`CampaignReportingService` owns metric definitions. `ReportingRepository` may use purpose-built SQL/read models but cannot embed alternate business definitions. Every displayed number has a drill-down/query test fixture proving no double count.

Recent activity uses valid interactions, status/stage events, follow-up outcomes, and creation events. Voided/corrected originals are excluded or visibly identified only in audit views.

## Performance

MVP calculates from normalized indexed tables. Dashboard queries must remain bounded. Add caching only after measurement and with event-based invalidation; browser refresh is sufficient initially.

## External access

F14 exposes bounded report capabilities under `reports:read`. Results are scope-projected: counts may be visible without exposing underlying contact/interaction text. Drill-down records require their own read scopes.

## Tests

- Fixture-based exact metric counts across status advancement, closure, archive, void, backdating, and campaign boundaries.
- Distinct counting under repeated interactions/events.
- Owner-timezone boundary and daylight-saving behavior.
- Decimal pipeline/won totals.
- Drill-down reconciliation.
- Zero denominator and ended/no-campaign states.
- Scope-projected external summaries.

## Acceptance

- Every dashboard number reconciles to source records.
- Archiving or advancing state does not erase valid historical activity.
- The owner sees overdue work and the primary campaign conversion ratio immediately after login.
