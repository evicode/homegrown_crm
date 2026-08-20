# F04 — Campaign Configuration

## Purpose

Create and select campaigns, configure their date windows and targets, and provide exactly one active campaign context to dependent workflows.

## Scope

- Campaign create, view, edit, list, and activation.
- Target values for canonical campaign metrics.
- Owner timezone setting used by campaign/day/due calculations.
- No campaign deletion through the UI; unused campaigns may be archived only if a later specification adds it.

## Data

### `campaigns`

- Name, start/end dates, version, timestamps.
- End date must be on or after start date.

### `campaign_targets`

- Campaign, canonical metric key, nonnegative integer value, version.
- Unique campaign/metric key.
- Labels and order come from configuration.

### `application_settings`

- Singleton active campaign ID, owner timezone, version.

## Defaults

New campaign target defaults:

- Selected prospects: 175
- Personalized outbound contacts: 125
- Partners contacted: 50
- Existing-network contacts: 40
- Sales conversations: 16
- Qualified opportunities: 6
- Offers sent: 3
- Contracts won: 1

Response count/rate is reported without an initial numeric target.

## Routes

- `GET /campaigns`: list campaigns and identify active.
- `GET /campaigns/new`, `POST /campaigns`: create with canonical targets.
- `GET /campaigns/{id}/edit`, `POST /campaigns/{id}/update`: edit dates/name/targets.
- `POST /campaigns/{id}/activate`: lock singleton settings and select campaign.
- `GET /settings/campaign`: shortcut to active campaign edit/setup.
- `POST /settings/timezone`: update supported owner timezone.

All writes require CSRF and submitted versions for existing records. Updating multiple targets is one transaction with a version for every changed target and settings/campaign record touched.

## Behavior

- Zero active campaign: owner is routed to setup; dependent features show a configuration-required state.
- Activation locks the singleton row, verifies campaign exists, updates its version, and never uses campaign flags.
- Campaign dates are date-only in owner timezone.
- Editing dates does not rewrite event timestamps; reporting immediately reevaluates the campaign window.
- Changing timezone requires confirmation because due/report boundaries may move; stored UTC timestamps do not change.

## External capability hooks

F14 may expose campaign/target reads and versioned updates. Activation is a high-impact configuration command and requires a dedicated policy if exposed; it is browser-only by default.

## Audit

Audit campaign creation, edits, target changes, activation, and timezone change with before/after keys but no unrelated business data.

## Tests

- Default target creation and canonical labels.
- Date and nonnegative target validation.
- Zero/one active campaign behavior.
- Concurrent activation and stale target updates.
- Timezone change impact on classification without timestamp mutation.
- Transaction rollback if one target version is stale.

## Acceptance

- The owner can create and activate one campaign.
- Concurrent activation cannot yield competing active campaigns.
- Dashboard consumers receive one unambiguous campaign/timezone context.
- Target updates cannot partially commit.
