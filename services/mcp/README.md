# Microgifter MCP Service

This directory is the separately deployable TypeScript control plane for Microgifter Platform Phase 5.

Current Phase 1 posture:

- disabled by default;
- stateless Streamable HTTP at `/mcp` for protected internal use;
- SHA-256 bearer-token verification;
- strict Origin and Host validation;
- deterministic, database-scope-filtered tool discovery when the canonical bridge is enabled;
- fixed-window per-connection rate limits;
- durable safe invocation receipts through the canonical PHP bridge;
- no external OAuth;
- no scheduler or worker;
- no write-capable tools;
- no production database credentials in Node;
- HMAC-SHA256 signed Node-to-PHP canonical bridge requests.

Functional Phase 1 tools:

- `microgifter.account.get_connection_context`
- `microgifter.catalog.search`
- `microgifter.catalog.get_item`

Catalog and connection authority are re-resolved from the Microgifter database on every bridged request. Revoked connections or scopes take effect on the next request.

## Local validation

```bash
npm ci
npm audit --audit-level=high
npm run check
```

## Internal bridge launch

Provision an active client and connection from `/admin/mcp-connections.php`, then configure the PHP and Node deployments with the generated one-time bundle.

```bash
MICROGIFTER_MCP_ENABLED=true \
MICROGIFTER_MCP_INTERNAL_HTTP_ENABLED=true \
MICROGIFTER_MCP_INTERNAL_HOST=127.0.0.1 \
MICROGIFTER_MCP_INTERNAL_PORT=8787 \
MICROGIFTER_MCP_INTERNAL_TOKEN_SHA256=<64-character-sha256> \
MICROGIFTER_MCP_INTERNAL_CONNECTION_ID=<persisted-connection-uuid> \
MICROGIFTER_MCP_INTERNAL_CLIENT_KEY=<persisted-client-key> \
MICROGIFTER_MCP_INTERNAL_USER_ID=<authorized-user-id> \
MICROGIFTER_MCP_BRIDGE_ENABLED=true \
MICROGIFTER_MCP_BRIDGE_URL=https://microgifter.com/api/internal/mcp-bridge.php \
MICROGIFTER_MCP_BRIDGE_SECRET=<shared-deployment-secret> \
npm start
```

The matching PHP deployment requires:

```text
MG_MCP_BRIDGE_ENABLED=true
MG_MCP_BRIDGE_SECRET=<same-shared-deployment-secret>
```

External OAuth and public autonomous deployment remain disabled until later scoped phases.
