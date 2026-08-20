# F05 — Companies and Contacts

## Purpose

Maintain reusable organization and person records without forcing independent people into artificial companies.

## Scope

- Company and contact CRUD, detail views, archive, and restore.
- Independent contacts and contacts belonging to one company.
- Primary-contact selection is campaign/prospect-specific and belongs to F06.
- Duplicate warnings; no automatic merge in MVP.

## Data

### `companies`

Name, website, location, industry, employee/revenue ranges, notes, archive time, version, timestamps.

### `contacts`

Nullable company, first/last name, role, email, phone, LinkedIn URL, archive time, version, timestamps.

Normalization:

- Trim display text and collapse accidental surrounding whitespace.
- Store email in comparison-normalized form while preserving safe display where needed.
- Normalize URLs to supported HTTP(S) forms.
- Empty optional values become `NULL`.
- Company/contact names retain user capitalization.

## Routes

- `GET /companies`, `GET /companies/new`, `POST /companies`
- `GET /companies/{id}`, `GET /companies/{id}/edit`, `POST /companies/{id}/update`
- `POST /companies/{id}/archive`, `POST /companies/{id}/restore`
- `GET /contacts/new`, `POST /contacts`
- `GET /contacts/{id}/edit`, `POST /contacts/{id}/update`
- `POST /contacts/{id}/archive`, `POST /contacts/{id}/restore`

List search/filter behavior is owned by F11. All existing-record commands require current versions.

## Validation

- Company name is required and length-limited.
- Contact requires at least one name component.
- Email, phone, and URLs are optional but validated and length-limited when present.
- Contact company must exist and not be archived unless explicitly restoring/reassociating.
- Notes are plain text and size-limited.

## Duplicate policy

Potential duplicates are warnings based on normalized company name/domain and contact email/name/company. A warning requires confirmation but does not create a hidden merge. Exact conflicting identities used by an active prospect are handled by F06 uniqueness rules.

## Archive behavior

- Archive hides records from normal selection but preserves history.
- A company with non-archived contacts or active prospects cannot be archived until the owner explicitly resolves or confirms the dependency policy presented by the UI; no silent cascade.
- A contact that is primary on an active prospect cannot be archived until another contact is selected or the prospect becomes company-only.
- Restore revalidates dependencies and duplicates.
- Historical activity metrics remain intact.

## UI

Company detail shows company fields, active/archived contacts, associated campaign prospects, and archive state. Contact editor can create an independent person or select one active company.

## Application services

- `CreateCompany`, `UpdateCompany`, `ArchiveCompany`, `RestoreCompany`
- `CreateContact`, `UpdateContact`, `ArchiveContact`, `RestoreContact`

Services own normalization, validation, versions, dependency checks, transactions, and audit events.

## Tests

- Independent and company contact creation.
- Normalization and invalid email/URL behavior.
- Duplicate warnings and confirmation.
- Archive dependency conflicts and restore checks.
- Stale update and compound dependency rollback.
- Output escaping for all free text.

## Acceptance

- The owner can represent a company without a known person and an independent person without a company.
- Archiving never silently orphans an active prospect.
- Stale edits cannot overwrite newer browser/API/MCP changes.
