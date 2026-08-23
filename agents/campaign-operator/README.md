# Campaign Operator

This is a standalone, MCP-only campaign operator package. It does not read the CRM database directly and it does not contain any token.

## Setup

Create a local integration token in **Integrations** with the read scopes listed in [AGENT.md](AGENT.md). Export it only in the process that runs the operator:

```powershell
$env:CAMPAIGN_OPERATOR_MCP_URL = 'http://127.0.0.1/conversions/mcp'
$env:CAMPAIGN_OPERATOR_TOKEN = 'the-token-shown-once-when-created'
php agents/campaign-operator/run.php brief
```

`brief` reads the active campaign, campaign report, and prospects. It makes no changes.

To use lead discovery, enable the Places API in a Google Maps Platform project and export `GOOGLE_PLACES_API_KEY` in the same process. The key stays outside this repository. Then run a precise ideal-customer query:

```powershell
$env:GOOGLE_PLACES_API_KEY = 'your-server-side-key'
php agents/campaign-operator/run.php find 'custom software development companies in Portland, Oregon'
```

`find` searches Google Places, then calls the CRM MCP `search_companies` tool for every candidate. It prints a review queue containing name, website, address, category, business status, and whether a company with the same name is already present. It never creates a CRM record.

## Commands

```text
php agents/campaign-operator/run.php discover
php agents/campaign-operator/run.php brief
php agents/campaign-operator/run.php find 'manufacturing companies in Seattle, Washington'
php agents/campaign-operator/run.php call search_companies '{"query":"Acme"}'
php agents/campaign-operator/run.php call create_company '{"idempotency_key":"unique-16-or-more-character-key","name":"Acme"}' --approve-write
```

The runner refuses MCP write tools unless `--approve-write` is present. The MCP server still enforces the token's scopes, record versions, business rules, audit trail, and idempotency.

Use [AGENT.md](AGENT.md) as the operating instruction set when connecting an LLM agent to this MCP endpoint. The runner is also useful by itself for safe daily briefs and MCP connectivity checks.
