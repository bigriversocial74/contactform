# Microgifter MCP Phase 1 Provisioning Console Runbook

## Release

`microgifter_mcp_phase1_provisioning_console_v1`

## Purpose

This release adds a protected Microgifter admin control plane for the internal MCP Phase 1 runtime. It provisions persisted clients and connections, binds an active user and optional merchant workspace, manages database-backed read scopes, and generates one-time deployment credentials without storing secrets.

## Access

- Page: `/admin/mcp-connections.php`
- Required permission: `admin.settings.manage`
- Super administrators retain implicit access through the existing permission matrix.

## Supported operations

- Read MCP clients, connections, active scopes, and PHP-side deployment readiness.
- Create a read-only MCP client or select an existing eligible client.
- Create an active connection for an active Microgifter user.
- Optionally bind a merchant workspace after verifying owner or active team membership.
- Grant and revoke active, grantable read scopes from `mcp_scope_catalog`.
- Pause, resume, revoke, and rotate a connection token version.
- Generate a one-time bearer token, SHA-256 token hash, and bridge secret.
- Produce copyable PHP and Node environment blocks.

## Security boundaries

- All APIs require `admin.settings.manage`.
- All writes require CSRF validation.
- Reads and writes are rate limited.
- Provisioning requires an action reason.
- User status is revalidated before provisioning.
- Merchant workspace access is checked before binding.
- Client and connection operation classes are locked to `read`.
- Only active, grantable read scopes may be assigned.
- Status and scope changes are audited and recorded as events and security logs.
- Runtime secrets are generated with `random_bytes`, returned once, and never written to the database, logs, release manifests, or repository.
- The console does not modify process environment variables or restart services.

## Provisioning sequence

1. Open `/admin/mcp-connections.php` with an authorized admin account.
2. Select an existing development/active read-only client or create a new client.
3. Enter an active user ID or email address.
4. Optionally enter a merchant workspace UUID.
5. Confirm `profile:read` and `catalog:read` scopes.
6. Choose the connection expiration period and enter the required reason.
7. Provision the connection.
8. Verify the connection appears as active and ready.

## Deployment credential sequence

1. Select **Deployment bundle** on an active connection.
2. Verify the HTTPS bridge URL.
3. Enter the required reason.
4. Generate the one-time bundle.
5. Copy the bearer token to the internal MCP caller.
6. Copy the PHP environment block to the PHP/application deployment secrets.
7. Copy the Node environment block to the MCP service deployment secrets.
8. Close the dialog. The raw values cannot be retrieved again.

## Required deployment values

PHP/application environment:

```text
MG_MCP_BRIDGE_ENABLED=true
MG_MCP_BRIDGE_SECRET=<generated-one-time-secret>
```

Node MCP environment:

```text
MICROGIFTER_MCP_ENABLED=true
MICROGIFTER_MCP_INTERNAL_HTTP_ENABLED=true
MICROGIFTER_MCP_INTERNAL_HOST=127.0.0.1
MICROGIFTER_MCP_INTERNAL_PORT=8787
MICROGIFTER_MCP_INTERNAL_TOKEN_SHA256=<generated-sha256>
MICROGIFTER_MCP_INTERNAL_CONNECTION_ID=<persisted-connection-uuid>
MICROGIFTER_MCP_INTERNAL_CLIENT_KEY=<persisted-client-key>
MICROGIFTER_MCP_INTERNAL_USER_ID=<authorized-user-id>
MICROGIFTER_MCP_BRIDGE_ENABLED=true
MICROGIFTER_MCP_BRIDGE_URL=https://microgifter.com/api/internal/mcp-bridge.php
MICROGIFTER_MCP_BRIDGE_SECRET=<same-generated-one-time-secret>
```

## Smoke test

1. Confirm the readiness panel reports the foundation migration imported.
2. Confirm an active connection has `profile:read` and `catalog:read`.
3. Configure PHP and Node using the one-time bundle.
4. Start the Node MCP service.
5. Initialize MCP and list tools.
6. Call `microgifter.account.get_connection_context`.
7. Call `microgifter.catalog.search` and `microgifter.catalog.get_item`.
8. Confirm `mcp_tool_invocations` records receipts.
9. Revoke `catalog:read` in the console.
10. Confirm catalog tools disappear or fail closed on the next request.
11. Restore the scope only when required.

## Rollback

1. Set `MICROGIFTER_MCP_BRIDGE_ENABLED=false` in Node.
2. Set `MG_MCP_BRIDGE_ENABLED=false` in PHP.
3. Stop the Node process.
4. Pause or revoke the affected MCP connection.
5. Rotate the deployment bearer token and bridge secret.
6. Preserve clients, connections, scopes, audit records, and invocation receipts.

## SQL

No new SQL is required.

The console uses the already imported migration:

`database/20260720_microgifter_mcp_automation_foundation_v1.sql`
