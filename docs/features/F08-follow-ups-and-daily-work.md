# F08 — Follow-ups and Daily Work

## Purpose

Provide one authoritative next-action system and the overdue/today/upcoming queues that drive daily outreach.

## Data

`follow_ups` contains prospect, optional opportunity, action, UTC due time, status, completion/cancellation time, generated open slot, version, actor, and timestamps.

Statuses: `open`, `completed`, `cancelled`. Database uniqueness on prospect/open slot enforces one open follow-up per relationship.

## Rules

- Action and due time are required for open follow-ups.
- Opportunity, when present, must belong to the same prospect.
- Creating a next action when one is open is a reschedule/update command, not a second open row.
- Completing/cancelling preserves history and clears the unique open slot.
- Opening/closing opportunities never silently removes a follow-up.

Due classification uses owner timezone:

- Overdue: before local today.
- Today: within local today.
- Upcoming: after today, with dashboard default through seven days.
- Unscheduled: no open follow-up.

## Routes

- `POST /prospects/{id}/follow-ups`: create open follow-up.
- `POST /follow-ups/{id}/reschedule`
- `POST /follow-ups/{id}/complete`
- `POST /follow-ups/{id}/cancel`
- `GET /work`: complete daily-work view when dashboard subset is insufficient.

All mutations require prospect and existing follow-up versions; opportunity version is required only if the command mutates opportunity state.

## Daily queues

Order overdue/today by due time then ID. Upcoming uses due time then ID. Ready-for-contact is a prospect queue, not a fake follow-up. Queue rows show identity, segment, action, due time/classification, last contact, and relevant opportunity stage.

## Compound commands

Recording an interaction may complete the prior open follow-up and create a replacement in one transaction. The F14 precondition catalog declares every involved version. One stale record rolls back all changes.

## UI

Quick complete, reschedule, and cancel controls use accessible forms/dialogs and show exact target. Cancellation requires a reason when tied to an open opportunity. Stale actions return current follow-up state.

## Application services

- `CreateFollowUp`, `RescheduleFollowUp`, `CompleteFollowUp`, `CancelFollowUp`
- `GetDailyWorkQueues`

## Tests

- Generated open-slot uniqueness under concurrency.
- Timezone/daylight-saving due classification.
- Same-prospect opportunity validation.
- Complete/reschedule/cancel history.
- Compound interaction/follow-up atomicity.
- Queue ordering, archive exclusion, and stale conflicts.

## Acceptance

- At most one open next action exists per prospect.
- The owner can determine today's work without a spreadsheet.
- Completing/rescheduling never erases prior follow-up history.
