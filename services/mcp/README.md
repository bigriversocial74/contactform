# Microgifter MCP Service

This directory is the separately deployable TypeScript control plane for Microgifter Platform Phase 5.

Current Phase 1 posture:

- disabled by default;
- stateless Streamable HTTP at `/mcp` for protected use;
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

## Runtime endpoints

```text
GET  /health  process liveness with minimized service metadata
GET  /ready   deployment readiness, including canonical bridge authority
POST /mcp     authenticated stateless MCP Streamable HTTP
```

Readiness returns HTTP 503 while the process is draining or when the configured database-backed connection cannot be resolved through the PHP bridge.

## Production configuration validation

```bash
npm run validate:env
```

Production mode requires:

- platform, internal HTTP, and canonical bridge enabled;
- an HTTPS public origin;
- at least one explicit allowed Host value;
- a persisted connection UUID;
- the bearer-token SHA-256 hash;
- a bridge secret of at least 32 characters;
- loopback binding unless non-loopback binding is explicitly enabled for a container.

The validator emits only non-secret configuration metadata.

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

## Production VPS package

The complete systemd, Docker, Nginx, environment, TLS-bootstrap, installation, rollback, logging, and smoke-test package is under `deploy/vps/`.

Primary runbook:

```text
docs/MICROGIFTER_MCP_PRODUCTION_VPS_DEPLOYMENT_V1.md
```

Authenticated public smoke test:

```bash
MCP_SMOKE_BASE_URL=https://mcp.microgifter.com \
MCP_SMOKE_BEARER_TOKEN='<raw-bearer-token>' \
npm run smoke
```

The service writes redacted structured JSON to stdout/stderr for systemd journald or container logging. `SIGTERM` and `SIGINT` initiate readiness failure and graceful request draining before shutdown.

External OAuth and public autonomous deployment remain disabled until later scoped phases.
