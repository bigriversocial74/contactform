# Microgifter MCP Service

This directory is the separately deployable TypeScript control plane for Microgifter Platform Phase 5.

Current Phase 2A/2B posture:

- disabled by default;
- stateless Streamable HTTP at `/mcp`;
- OAuth-protected external connections with per-request database authority resolution;
- optional staged internal SHA-256 bearer fallback;
- OAuth protected-resource discovery;
- strict Origin and Host validation;
- deterministic, database-scope-filtered tool discovery;
- fixed-window per-connection rate limits;
- durable safe invocation receipts through the canonical PHP bridge;
- loopback-only external-agent compatibility simulator;
- non-secret deployment-readiness report;
- no scheduler or worker;
- no write-capable tools;
- no production database credentials in Node;
- HMAC-SHA256 signed Node-to-PHP canonical bridge requests.

Functional read-only tools:

- `microgifter.account.get_connection_context`
- `microgifter.catalog.search`
- `microgifter.catalog.get_item`

Catalog, connection, consent, client, user, workspace, token version, and scope authority are re-resolved from the Microgifter database on every bridged request. Revocation takes effect on the next request.

## Local validation

```bash
npm ci
npm audit --audit-level=high
npm run check
```

## Phase 2B external-agent simulator

The simulator runs the actual compiled MCP HTTP application on an ephemeral `127.0.0.1` listener with generated sample authorization and catalog data. It performs no external network calls and refuses production mode.

```bash
npm run build
node scripts/simulate-external-agent.mjs
node scripts/external-agent-readiness.mjs
```

The simulator validates discovery, OAuth challenge, PKCE, MCP initialization, tool discovery, a catalog read call, refresh rotation, replay-family revocation, and explicit revocation. It does not prove public DNS, TLS, Nginx, or live-client compatibility.

Primary installation and activation reference:

```text
docs/MICROGIFTER_MCP_INSTALLATION_AND_ACTIVATION.md
```

Phase 2B reference:

```text
docs/MICROGIFTER_MCP_EXTERNAL_AGENT_SIMULATOR_PHASE2B.md
```

## Runtime endpoints

```text
GET  /health                                      process liveness
GET  /ready                                       deployment readiness
GET  /.well-known/oauth-protected-resource        OAuth resource discovery
GET  /.well-known/oauth-protected-resource/mcp    path-specific discovery alias
POST /mcp                                         MCP Streamable HTTP
```

Readiness returns HTTP 503 while the process is draining or when the configured internal readiness connection cannot be resolved through the PHP bridge.

## Production configuration validation

```bash
npm run validate:env
```

Production mode requires:

- platform, internal HTTP, and canonical bridge enabled;
- an HTTPS public origin;
- at least one explicit allowed Host value;
- a persisted internal readiness connection UUID;
- a bridge secret of at least 32 characters;
- loopback binding unless non-loopback binding is explicitly enabled for a container.

When external OAuth is enabled, production additionally requires:

- `MICROGIFTER_MCP_RESOURCE_URI` matching the public base URL plus `/mcp`;
- an HTTPS authorization-server issuer;
- an HTTPS protected-resource metadata URL;
- the canonical PHP bridge;
- explicit choice of whether the internal bearer fallback remains enabled.

The validator emits only non-secret configuration metadata.

## External OAuth launch

The matching PHP deployment requires the Phase 2A migration, the existing bridge settings, and the OAuth settings in:

```text
deploy/vps/php-bridge.env.example
deploy/vps/php-oauth.env.example
```

Node requires:

```text
MICROGIFTER_MCP_EXTERNAL_OAUTH_ENABLED=true
MICROGIFTER_MCP_RESOURCE_URI=https://mcp.microgifter.com/mcp
MICROGIFTER_MCP_AUTHORIZATION_SERVER=https://microgifter.com
MICROGIFTER_MCP_PROTECTED_RESOURCE_METADATA_URL=https://mcp.microgifter.com/.well-known/oauth-protected-resource
MICROGIFTER_MCP_ALLOW_INTERNAL_BEARER=true
```

Set the internal fallback to `false` only after external OAuth is verified and the deployment no longer needs Phase 1 bearer smoke testing.

Primary Phase 2A runbook:

```text
docs/MICROGIFTER_MCP_EXTERNAL_AGENT_AUTHORIZATION_PHASE2A.md
```

## Internal bridge launch

Provision an active internal client and connection from `/admin/mcp-connections.php`, then configure the PHP and Node deployments with the generated one-time bundle.

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

## Production VPS package

The complete systemd, Docker, Nginx, environment, TLS-bootstrap, installation, rollback, logging, and smoke-test package is under `deploy/vps/`.

Primary VPS runbook:

```text
docs/MICROGIFTER_MCP_PRODUCTION_VPS_DEPLOYMENT_V1.md
```

The service writes redacted structured JSON to stdout/stderr for systemd journald or container logging. `SIGTERM` and `SIGINT` initiate readiness failure and graceful request draining before shutdown.
