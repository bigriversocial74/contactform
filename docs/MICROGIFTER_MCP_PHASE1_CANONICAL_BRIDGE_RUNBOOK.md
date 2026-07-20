# Microgifter MCP Phase 1 Canonical Bridge Runbook

## Release

`microgifter_mcp_phase1_canonical_bridge_v1`

## Purpose

This release activates the first live MCP catalog tools without giving Node.js database credentials or duplicating Microgifter domain logic.

The Node MCP service signs requests to a protected PHP endpoint. PHP resolves the current connection, client, user, workspace and database scopes before dispatching to existing published-catalog services.

## Required deployment settings

PHP/application environment:

```text
MG_MCP_BRIDGE_ENABLED=true
MG_MCP_BRIDGE_SECRET=<at-least-32-random-characters>
```

Node MCP environment:

```text
MICROGIFTER_MCP_ENABLED=true
MICROGIFTER_MCP_INTERNAL_HTTP_ENABLED=true
MICROGIFTER_MCP_INTERNAL_TOKEN_SHA256=<sha256-of-internal-bearer-token>
MICROGIFTER_MCP_INTERNAL_CONNECTION_ID=<active-mcp-connection-uuid>
MICROGIFTER_MCP_BRIDGE_ENABLED=true
MICROGIFTER_MCP_BRIDGE_URL=https://microgifter.com/api/internal/mcp-bridge.php
MICROGIFTER_MCP_BRIDGE_SECRET=<same-random-secret>
```

The bridge secret must be provided by deployment secrets and must never be committed.

## Activated tools

- `microgifter.account.get_connection_context`
- `microgifter.catalog.search`
- `microgifter.catalog.get_item`

Catalog tools require an active `catalog:read` scope on the current database connection. Connection context requires `profile:read`.

## Security controls

- HMAC-SHA256 request signatures.
- Five-minute timestamp window.
- Unique nonce replay reservation in `mcp_idempotency_keys`.
- Connection, client, user, expiration and workspace access rechecked on every MCP request.
- Scopes loaded from the database rather than trusted from Node configuration.
- Read-only operation ceiling.
- Durable success/failure receipts in `mcp_tool_invocations`.
- Exact street addresses, postal codes and private catalog metadata omitted from MCP output.

## Validation

```bash
cd services/mcp
npm ci
npm audit --audit-level=high
npm run check

cd ../..
php scripts/validate_mcp_phase1_canonical_bridge.php
vendor/bin/phpunit tests/phpunit/McpPhase1CanonicalBridgeV1ContractTest.php
```

## Smoke test

1. Confirm the Phase 1 foundation migration is imported.
2. Create or select an active MCP client and connection.
3. Grant `profile:read` and `catalog:read` in `mcp_connection_scopes`.
4. Start PHP and Node with the shared bridge secret.
5. Initialize MCP and list tools.
6. Search the catalog and verify only published, privacy-filtered products are returned.
7. Follow `next_cursor` and verify deterministic pagination.
8. Get a product by UUID and confirm exact address/private metadata is absent.
9. Confirm `mcp_tool_invocations` contains the success receipt.
10. Revoke `catalog:read` and confirm catalog tools disappear on the next request.

## Rollback

1. Set `MICROGIFTER_MCP_BRIDGE_ENABLED=false`.
2. Set `MG_MCP_BRIDGE_ENABLED=false`.
3. Rotate the bridge secret.
4. Stop the Node MCP process if necessary.
5. Preserve foundation tables and invocation receipts.

## SQL

No new SQL is required for this release.

The required foundation migration was previously imported:

`database/20260720_microgifter_mcp_automation_foundation_v1.sql`
