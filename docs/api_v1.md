# API v1 quick start

The local API is available at `/api/v1`. It uses an account-assigned integration token created from **Integrations** in the CRM. The token is shown once; store it in your client secret store, never in source control.

All authenticated requests use `Authorization: Bearer YOUR_TOKEN`. Mutations also require `Content-Type: application/json` and a unique `Idempotency-Key` of 16 to 128 URL-safe characters.

```sh
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost/conversions/public/api/v1/capabilities
```

The available capabilities reflect the scopes selected when the token was created. The public contract index is at `/api/openapi.json`.

## Prospect workflow

Create a prospect, retaining the returned `version`:

```sh
curl -X POST http://localhost/conversions/public/api/v1/prospects \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: prospect-create-20260821-a1" \
  -d '{"company_id":1,"segment":"direct","source":"referral","why_them":"Modernization need","business_problem":"Delivery bottleneck","problem_understood":false,"timing_understood":false,"buyer_understood":false,"budget_plausible":false}'
```

Record an interaction using the current prospect version:

```sh
curl -X POST http://localhost/conversions/public/api/v1/prospects/PROSPECT_ID/interactions \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: interaction-20260821-a1" \
  -d '{"prospect_version":1,"type":"email","direction":"outbound","occurred_at":"2026-08-21T09:30","summary":"Personalized introduction","outcome":"sent_completed"}'
```

Use the same pattern for follow-ups (`POST /api/v1/prospects/{id}/follow-ups`) and opportunities (`POST /api/v1/prospects/{id}/opportunities`). Responses include an ID, version where applicable, and `Location`. API reads and mutations are private and never cached.

The current API slice covers campaign context, capability discovery, company and contact search/read/create, prospect search/read/create, interaction recording, follow-up scheduling, and opportunity creation. Browser cookies are not accepted by these routes.
