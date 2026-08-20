# F09 — Opportunities

## Purpose

Track qualified commercial possibilities from offer selection through discovery/proposal and won/lost closure.

## Data

### `opportunities`

Prospect, offer, current stage, decimal value/currency, expected close date, close/lost metadata, generated open slot, version, timestamps.

### `opportunity_stage_events`

Prior/new stage, occurrence time, reason, void metadata, actor, version, timestamps.

Offers and stages use canonical keys from the root. USD is initial configured currency.

## Rules

- Prospect must be currently qualified.
- Database permits one open opportunity per prospect.
- Value is nonnegative fixed-precision decimal; JSON serializes it as a string.
- Won/lost require close time; lost requires reason.
- Closing uses `opportunities:close` externally and explicit confirmation.
- Reopening is deferred; a later opportunity may be created after terminal closure.
- Every stage change writes an event transactionally.

## Routes

- `GET /opportunities`
- `POST /prospects/{id}/opportunities`
- `GET /opportunities/{id}`, `GET /opportunities/{id}/edit`
- `POST /opportunities/{id}/update`
- `POST /opportunities/{id}/transition`
- `POST /opportunities/{id}/stage-events/{eventId}/void`

## Transitions

`qualified → discovery_offered → proposal_sent → won|lost`

Feature configuration may allow loss from any open stage. Backward movement is correction, not normal progression, and uses latest-event void/reapply behavior.

## Follow-up integration

An open opportunity's commercial next action is the relationship's shared F08 follow-up referencing the opportunity. Creation may retain/reassociate an existing prospect follow-up with explicit owner choice. Closure requires the owner/client to complete, cancel, or deliberately retain/reassociate the open follow-up in the same command.

## UI

List shows company/person, offer, stage, value, expected close, and open follow-up. Detail shows qualification context, stage history, value, close metadata, interaction summary, and current action. High-impact close confirmation explains metric consequences.

## Application services

- `CreateOpportunity`, `UpdateOpportunity`, `TransitionOpportunity`
- `VoidOpportunityStageEvent`
- `GetOpportunityPipeline`

## Tests

- Qualified-only creation and one-open database constraint.
- Decimal/currency validation.
- Allowed transitions and terminal requirements.
- Follow-up disposition atomicity on close.
- Stage event/void recalculation and reporting.
- High-impact scope/confirmation and stale versions.

## Acceptance

- Only qualified prospects enter the commercial pipeline.
- Won/lost outcomes remain historically accurate.
- Opportunity and follow-up state cannot contradict after a committed close.
