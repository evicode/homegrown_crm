# Campaign Operator

This is a standalone, MCP-only campaign operator package. It does not read the CRM database directly and it does not contain any token.

## Windows dashboard — double-click to open

Double-click [open-campaign-dashboard.cmd](open-campaign-dashboard.cmd). It opens the Campaign Operator dashboard directly—no terminal commands required. Enter the CRM address and token directly in step 1 of the dashboard. It saves them in a local `.env` file that is ignored by Git.

The dashboard walks users through: connect it once, describe the companies they want, generate/edit a search, find companies, then optionally add reviewed candidates to the CRM queue. It never creates prospects or sends outreach.

## Optional interactive ChatGPT terminal session

Double-click [launch-campaign-agent.cmd](launch-campaign-agent.cmd) after signing in to the Codex CLI with your ChatGPT account. It starts an interactive Campaign Operator session in the terminal. The subscription model handles the conversation and reasoning; `run.php` makes the MCP calls to this CRM. No OpenAI API key is used by this launcher.

The session is interactive, not a background service: it runs while the terminal is open and asks before CRM writes. It still needs the CRM integration token below, plus at least one configured discovery source when you run lead discovery.

## Dashboard guardrails

The dashboard only runs the fixed **brief**, **tool discovery**, **search-plan**, and **lead finder** commands using argument lists (`shell=False`); it cannot run arbitrary terminal input.

- Lead searches are dry-run by default.
- Queue submission requires a review checkbox and a second confirmation.
- Only strong/possible-fit, non-duplicate candidates can be saved.
- Saved candidates are capped at 10 per run by default. Set `CAMPAIGN_OPERATOR_MAX_SAVED_CANDIDATES` to a value from 1–20 to lower or raise that cap.
- Each command has a 120-second timeout, and the CRM token is redacted from displayed output.

Run `py -3 tests/campaign_operator_control_panel.test.py` from the project root to verify those command and redaction guardrails.

## Setup

Create a local integration token in **Integrations** with the read scopes listed in [AGENT.md](AGENT.md). Enter it in step 1 of the dashboard; it reads and updates its local `.env` file automatically. For terminal use, export the variables only in the process that runs the operator:

```powershell
$env:CAMPAIGN_OPERATOR_MCP_URL = 'http://127.0.0.1/conversions/mcp'
$env:CAMPAIGN_OPERATOR_TOKEN = 'the-token-shown-once-when-created'
php agents/campaign-operator/run.php brief
php agents/campaign-operator/run.php plan
```

`brief` reads the active campaign, campaign report, and prospects. It makes no changes.

To use lead discovery, select one or more sources in the dashboard. Google Places and Foursquare each need their own key. OpenStreetMap discovery needs your own Mapbox token to find the requested area and an Overpass endpoint that you manage or host; it does not use a shared public endpoint. These settings stay outside this repository. Then run a precise ideal-customer query:

```powershell
$env:GOOGLE_PLACES_API_KEY = 'your-server-side-key'
php agents/campaign-operator/run.php find 'custom software development companies in Portland, Oregon'
```

Before the first search, open **Lead Finder → Ideal customer profile** in the CRM and define required characteristics, scored characteristics and their importance, exclusions, locations, and score thresholds. The agent reads that profile through MCP; no local JSON file is used.

Run `plan` to get up to eight suggested company searches drawn directly from the highest-importance characteristics and preferred locations. They are starting points, not claims that the profile is complete; edit a suggestion before using `find`.

`find` searches every selected source, removes obvious duplicates, checks names and website domains against CRM companies and checks active prospects, fetches the public homepage when available, and prints a readable review queue. A repeated discovery result cannot reopen or overwrite a previously reviewed queue item. Add `--json` only when another program needs the full machine-readable result.

Add `--save` to submit qualifying, non-duplicate candidates to the CRM **Lead Finder** queue through MCP. This needs the `lead_candidates:write` scope, but it still does not create a company or prospect.

## Commands

```text
php agents/campaign-operator/run.php discover
php agents/campaign-operator/run.php brief
php agents/campaign-operator/run.php plan
php agents/campaign-operator/run.php find 'manufacturing companies in Seattle, Washington'
php agents/campaign-operator/run.php find 'manufacturing companies in Seattle, Washington' --save
php agents/campaign-operator/run.php call search_companies '{"query":"Acme"}'
php agents/campaign-operator/run.php call create_company '{"idempotency_key":"unique-16-or-more-character-key","name":"Acme"}' --approve-write
```

The runner refuses MCP write tools unless `--approve-write` is present. The MCP server still enforces the token's scopes, record versions, business rules, audit trail, and idempotency.

Use [AGENT.md](AGENT.md) as the operating instruction set when connecting an LLM agent to this MCP endpoint. The runner is also useful by itself for safe daily briefs and MCP connectivity checks.
