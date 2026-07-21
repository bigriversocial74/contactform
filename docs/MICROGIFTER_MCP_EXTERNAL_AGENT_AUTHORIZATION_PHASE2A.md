# Microgifter MCP External Agent Authorization — Phase 2A

## Purpose

Phase 2A adds a database-backed OAuth authorization server for external MCP clients while preserving the canonical PHP authority boundary. The Node MCP service continues to receive no production database credentials.

## Endpoints

Authorization server on `https://microgifter.com`:

- `GET /.well-known/oauth-authorization-server`
- `GET|POST /oauth/authorize.php`
- `POST /oauth/token.php`
- `POST /oauth/revoke.php`
- `POST /oauth/register.php` when dynamic registration is explicitly enabled

Protected resource on `https://mcp.microgifter.com`:

- `GET /.well-known/oauth-protected-resource`
- `GET /.well-known/oauth-protected-resource/mcp`
- `POST /mcp`

## Authorization profile

- Authorization code grant
- Public clients
- PKCE S256 required
- Exact redirect URI matching
- Resource indicator required at authorization and token exchange
- Short-lived opaque access tokens
- Rotating opaque refresh tokens
- Full token-family revocation on rotated refresh-token replay
- Access, refresh, and authorization codes stored only as SHA-256 hashes
- Live connection, user, workspace, scope, consent, client, expiry, and token-version validation

Phase 2A grants only `profile:read` and `catalog:read`.

## Deployment order

1. Deploy the merged application files.
2. Import `database/20260720_mcp_external_agent_authorization_phase2a_v1.sql` through the canonical migration runner.
3. Configure the existing PHP bridge values from `deploy/vps/php-bridge.env.example`.
4. Configure the PHP OAuth values from `deploy/vps/php-oauth.env.example`.
5. Configure the Node environment from `deploy/vps/mcp.env.example`.
6. Keep OAuth disabled until the migration and both environments are ready.
7. Pre-register a known client at `/admin/mcp-oauth-clients.php`, or explicitly enable dynamic registration.
8. Restart PHP-FPM/Apache as required by the hosting environment.
9. Restart the Node MCP service.
10. Verify authorization-server and protected-resource metadata.
11. Run a real client connection only after DNS and TLS are active.

## Staged activation

Recommended initial values:

```text
MG_MCP_OAUTH_ENABLED=true
MG_MCP_OAUTH_DYNAMIC_REGISTRATION_ENABLED=false

MICROGIFTER_MCP_EXTERNAL_OAUTH_ENABLED=true
MICROGIFTER_MCP_ALLOW_INTERNAL_BEARER=true
```

Keeping the internal bearer fallback during initial verification allows the existing Phase 1 smoke test to remain available. After external OAuth is proven, it may be disabled by setting `MICROGIFTER_MCP_ALLOW_INTERNAL_BEARER=false` and removing the internal bearer hash from a future dedicated external-only deployment profile.

## Client onboarding

For a pre-registered client:

1. Enter the client name and exact redirect URI in `/admin/mcp-oauth-clients.php`.
2. Copy the generated public client ID.
3. Configure the client with:
   - MCP resource: `https://mcp.microgifter.com/mcp`
   - Authorization issuer: `https://microgifter.com`
   - Client ID: generated UUID
   - PKCE: S256
4. Begin authorization from the client.
5. The user signs in to Microgifter, chooses account or merchant workspace, reviews scopes, and approves.
6. The client exchanges the one-time authorization code for access and refresh tokens.

For dynamic registration, set `MG_MCP_OAUTH_DYNAMIC_REGISTRATION_ENABLED=true` only when required by an approved client and protect the public endpoint with infrastructure rate limiting.

## Operations

Users manage and revoke their authorizations at:

```text
/account-ai-connections.php
```

Administrators pre-register and review clients at:

```text
/admin/mcp-oauth-clients.php
```

Revoking a user connection:

- marks consent revoked;
- revokes the underlying MCP connection;
- increments the connection token version;
- revokes all token families for the connection;
- takes effect on the next request.

## Validation

Static contract:

```bash
php scripts/validate_mcp_external_agent_authorization_phase2a_v1.php
```

Clean-database executable OAuth flow:

```bash
php scripts/test_mcp_external_agent_authorization_phase2a.php
```

Node:

```bash
cd services/mcp
npm ci --ignore-scripts
npm audit --audit-level=high
npm run check
```

## Not included

- Write-capable tools
- Campaign, reward, gift, purchase, messaging, or redemption mutations
- Schedulers or workers
- Autonomous purchases
- Client-secret authentication
- Registration-management endpoints
- Production claims before public DNS/TLS and live client testing
