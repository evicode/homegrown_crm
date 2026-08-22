# MCP endpoint

The remote MCP endpoint is `POST /mcp`. It uses the same account-assigned bearer tokens, revocation, and scopes as API v1; browser sessions are not accepted.

Send `Authorization: Bearer YOUR_TOKEN`, `Content-Type: application/json`, and MCP protocol version `2025-11-25` during initialization. The server only advertises tools the token may invoke.

The initial MCP surface contains:

- `get_active_campaign` (`campaign:read`)
- `get_campaign_report` (`reports:read`)
- `search_companies`, `get_company` (`companies:read`)
- `search_contacts`, `get_contact` (`contacts:read`)
- `search_prospects`, `get_prospect` (`prospects:read`)
- `get_opportunity` (`opportunities:read`)
- `create_company`, `create_contact`, `create_prospect` (matching write scopes)

This endpoint is backed by the official [`mcp/sdk`](https://github.com/modelcontextprotocol/php-sdk) Streamable HTTP transport. Its server session files live below `var/tmp/mcp-sessions`, are outside the public directory, and expire after 15 minutes.

Each MCP mutation requires an `idempotency_key` of at least 16 URL-safe characters. Reusing the same completed key returns the previous result rather than repeating the write.
