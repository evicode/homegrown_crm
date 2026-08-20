# F07 — Interactions

## Purpose

Record attempted and completed communication as durable history, identify meaningful responses, and update derived contact state without turning interactions into tasks.

## Data

`interactions` contains prospect, optional contact, type, direction, occurrence time, summary, outcome, version, correction link, void metadata, actor, and timestamps.

Types: email, phone, meeting, LinkedIn, and note. Directions: inbound, outbound, internal. Canonical outcomes include attempted/no response, sent/completed, meaningful response, automated response, bounce, wrong recipient, meeting held, and note only.

F07 owns `qualifies_as_contact` and `qualifies_as_response` mappings in canonical configuration. Only valid, non-voided interactions participate.

## Routes

- `POST /prospects/{id}/interactions`
- `GET /interactions/{id}/edit-correction`
- `POST /interactions/{id}/correct`

Ordinary deletion/edit-in-place is unavailable.

## Record behavior

- Occurrence time defaults to now and may be deliberately backdated within configured bounds.
- Contact is optional; if present it must be compatible with the prospect identity/company.
- Summary is required except for narrowly defined note/outcome combinations and is plain text.
- First qualifying outbound interaction may transition ready prospect to contacted in the same transaction.
- Derived `last_contact_at` is recalculated from qualifying valid interactions.
- An optional next action delegates to F08 in the same compound command.

## Correction

A correction creates a replacement interaction referencing the original, voids the original with reason/actor/time, and recalculates derived state. Require original interaction, prospect, and affected open follow-up versions when applicable. Repeated corrections form an auditable chain; voided records are visibly labeled in history but excluded from normal calculations.

## Timeline

Prospect detail shows valid interactions newest-first with type, direction, localized occurrence time, contact, outcome, summary, and actor class. Corrections can reveal prior versions through an explicit history view without cluttering the default timeline.

## Application services

- `RecordInteraction`
- `CorrectInteraction`
- `RecalculateProspectContactState`

## Security

- Summary text is untrusted and escaped.
- API/MCP projections require interaction scopes and never embed interaction content under prospect-only scopes.
- Backdating/correction/import paths are audited distinctly.

## Tests

- Each interaction type/direction/outcome validation.
- Contact compatibility and nullable contact.
- Qualifying contact/response mappings.
- First-outbound automatic transition.
- Last-contact recalculation after creation/correction.
- Compound follow-up creation rollback.
- Correction chain, stale preconditions, and metric effects.

## Acceptance

- The timeline accurately shows what happened and when.
- Automated replies/bounces never inflate response metrics.
- Corrections preserve the original while every derived value uses only valid history.
