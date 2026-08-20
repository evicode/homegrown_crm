# F06 — Prospects and Research

## Purpose

Represent a company/person's campaign-specific sales relationship, research evidence, segment, qualification context, and historically accurate status.

## Scope

- Prospect create, view, edit, archive, restore.
- Partner, direct, and network segments.
- Why-them evidence and buying signals.
- Explicit status transitions and append-only status history.
- Qualification notes; opportunity creation remains F09.

## Identity

A prospect belongs to one campaign and is anchored by company, contact, or both. At least one is required. When both exist, the contact must belong to the company.

Within a campaign, prevent duplicate active identities for company-only, contact-only, and exact company/contact prospects. The application checks normalized cases, and database indexes/locking prevent concurrent duplicates.

## Data

### `prospects`

Campaign, nullable company/contact, segment, current status, source, why-them, business problem, qualification notes, derived last-contact time, archive time, version, timestamps.

### `signals` and `prospect_signals`

Canonical signal key/name plus prospect evidence note, observed date, and version. Evidence notes are required.

### `prospect_status_events`

Prior/new status, occurrence time, reason, void metadata, actor, version, timestamps. Creation writes the initial event.

## Status transitions

Canonical statuses follow the root. Allowed transitions are declared once in `config/sales.php`. General flow:

`researching → ready_to_contact → contacted → interested → conversation → qualified`

`later` may be entered from active pre-close states and returned to an allowed active state. `closed_no_fit` is terminal in ordinary flow. Corrections use latest-event void/reapply behavior.

Transitions cannot skip required readiness or qualification rules merely because a client submits the target status.

## Readiness

- Partner/direct: nonblank why-them.
- Direct: at least one active signal with evidence.
- Network: may become ready without cold-research evidence.

## Qualification

Moving to `qualified` requires business problem plus explicit confirmation that problem, timing, buyer, and plausible budget have been understood. These may be captured as structured booleans/notes in F06 implementation; the canonical qualification note remains human-readable.

## Routes

- `GET /prospects`, `GET /prospects/new`, `POST /prospects`
- `GET /prospects/{id}`, `GET /prospects/{id}/edit`, `POST /prospects/{id}/update`
- `POST /prospects/{id}/transition`
- `POST /prospects/{id}/signals`, `POST /prospects/{id}/signals/{signalId}/update`
- `POST /prospects/{id}/signals/{signalId}/remove`
- `POST /prospects/{id}/archive`, `POST /prospects/{id}/restore`
- `POST /prospects/{id}/status-events/{eventId}/void`

Writes include the versions of every existing prospect/signal/event affected.

## UI

Detail page contains identity, research, status controls, signal evidence, qualification context, interaction/follow-up summaries supplied by their features, status timeline, and opportunity summary. Status controls show only currently allowed transitions and explain unmet prerequisites.

## Archive and correction

Archived prospects leave valid historical events in activity metrics but disappear from current queues. Status events are never deleted. Only the latest valid event can be voided; later events must be voided first. Recalculation and parent/event version increments are transactional.

## Application services

- `CreateProspect`, `UpdateProspect`, `TransitionProspect`
- `AddSignalEvidence`, `UpdateSignalEvidence`, `RemoveSignalEvidence`
- `VoidProspectStatusEvent`
- `ArchiveProspect`, `RestoreProspect`

## Tests

- All identity forms and duplicate/concurrent duplicate prevention.
- Readiness by segment.
- Allowed/forbidden transitions and qualification prerequisites.
- Event creation, voiding order, recalculation, and reporting survival.
- Contact/company consistency.
- Archive-safe activity history and stale versions.

## Acceptance

- A cold direct prospect cannot become ready without reason and evidenced signal.
- Every status change is attributable and historically queryable.
- No adapter can bypass transition prerequisites or create duplicate campaign identities.
