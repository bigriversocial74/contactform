# Microgifter MCP Phase 1 Protocol and Registry Runbook

## Release

`microgifter_mcp_phase1_protocol_v1`

## Purpose

This section adds a stateless MCP Streamable HTTP server for internal development, internal bearer authentication, scope-filtered tool discovery, rate limits, and invocation receipts.

It does not expose an external production endpoint or OAuth flow. Catalog tools are deliberately listed but fail closed until the protected canonical PHP bridge is merged.

## Protocol

- Revision: `2025-11-25`
- Endpoint: `POST /mcp`
- Mode: stateless Streamable HTTP with JSON responses
- Enabled methods: `initialize`, `notifications/initialized`, `ping`, `tools/list`, `tools/call`
- `GET /mcp` and `DELETE /mcp`: `405 Method Not Allowed`

## Internal authentication

The internal service accepts one bearer token whose plaintext is never stored. Deployment provides only its lowercase SHA-256 hash.

Required launch settings:

```text
MICROGIFTER_MCP_ENABLED=true
MICROGIFTER_MCP_INTERNAL_HTTP_ENABLED=true
MICROGIFTER_MCP_INTERNAL_TOKEN_SHA256=<64 lowercase hex characters>
MICROGIFTER_MCP_INTERNAL_SCOPES=profile:read,catalog:read
```

Optional settings define host, port, origins, hosts, internal connection identity, workspace context, and rate limits.

## Validation

```bash
cd services/mcp
npm ci
npm audit --audit-level=high
npm run check

cd ../..
php scripts/validate_mcp_phase1_protocol.php
vendor/bin/phpunit tests/phpunit/McpPhase1ProtocolV1ContractTest.php
```

## Smoke test

1. Start the internal service on localhost.
2. Verify no token receives `401`.
3. Verify a disallowed Origin receives `403`.
4. Initialize using protocol `2025-11-25`.
5. List tools and confirm deterministic scope filtering.
6. Call `microgifter.account.get_connection_context` and confirm minimized output plus a receipt.
7. Call either catalog tool and confirm `MICROGIFTER_TOOL_DISABLED` until the bridge release.
8. Exceed the configured connection rate and confirm `429` plus `Retry-After`.

## Rollback

1. Set `MICROGIFTER_MCP_INTERNAL_HTTP_ENABLED=false`.
2. Stop the Node process.
3. Revoke the internal development token hash.
4. Roll back service code.
5. Preserve Phase 1 foundation tables and receipts.

## SQL

No SQL required for this section.
