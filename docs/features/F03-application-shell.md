# F03 — Shared Application Shell

## Purpose

Provide a consistent, accessible owner-facing frame and reusable presentation components for all browser features.

## Scope

- Responsive layout, navigation, page headers, content region, and footer.
- Flash messages, validation summary, fields, status badges, filters, pagination, empty states, confirmation forms/dialogs, and next-action displays.
- CSS tokens and small vanilla-JavaScript enhancement bootstrap.
- Owner navigation visibility based on application state, not external scopes.

## Navigation

- Dashboard
- Prospects
- Opportunities
- Companies
- Data tools
- Campaign settings
- Integrations
- Account/logout

When no active campaign exists, campaign-dependent destinations lead to campaign setup rather than failing. Current location is conveyed semantically and visually.

## View contract

Controllers provide prepared view models. Templates:

- Escape text by default.
- Never access PDO, repositories, sessions, or request globals.
- Use named URL generation.
- Render dates in owner timezone and money through shared formatters.
- Preserve entered form values after validation errors without exposing passwords/tokens.

## Components

- `flash`: success, warning, error, and informational messages.
- `validation-summary`: links errors to fields.
- `field`: label, hint, control, error, and required state.
- `status-badge`: stable key mapped through canonical vocabulary.
- `filter-bar`: GET form with clear-all behavior.
- `pagination`: preserves allowlisted filters.
- `empty-state`: explains state and offers one relevant action.
- `confirmation`: describes exact target/consequence and submits a CSRF-protected POST.
- `next-action`: action, localized due time, and due classification.

## CSS and JavaScript

- Native CSS custom properties for spacing, typography, color, focus, border, and width.
- Layouts work at 320 CSS pixels and current desktop widths.
- JavaScript is deferred/module-based, discovers components through `data-*`, and fails safely.
- Dialog focus is trapped/restored; reduced motion is respected.
- Core navigation/forms work without JavaScript.

## UI states

Every page defines loading only when enhanced fetching is used, plus success, empty, validation error, conflict, not found, and unexpected error states. Stale-version conflicts show current data and require deliberate review/resubmission.

## Security and accessibility

- No unescaped HTML from business records.
- Destructive/high-impact actions use buttons/forms, not GET links.
- WCAG-oriented labels, focus visibility, keyboard operation, landmarks, heading order, and non-color status cues.
- Flash content never includes secrets or raw exception text.

## Tests

- Template escaping and named URL/base-path generation.
- Navigation with/without active campaign.
- Component rendering for empty/error/conflict states.
- Keyboard navigation, dialog focus, reduced motion, and phone width.
- No-JavaScript completion of primary forms.

## Acceptance

- All browser features render within one shell without duplicating navigation or form/error patterns.
- Primary workflows remain operable by keyboard and without JavaScript.
- A stale edit is understandable and cannot be overwritten accidentally.
