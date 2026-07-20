# Microgifter MCP Production VPS Deployment v1

## Purpose

This package deploys the existing Phase 1 MCP service as an isolated Node.js 20 process while keeping all production account, catalog, scope, and workspace authority inside the canonical PHP bridge.

Recommended initial topology:

```text
mcp.microgifter.com -> Nginx TLS -> 127.0.0.1:8787 Node MCP
microgifter.com     -> PHP application -> MySQL
Node MCP            -> signed HTTPS -> /api/internal/mcp-bridge.php
```

The Node service has no MySQL credentials. It can run on the same VPS as PHP or on a separate VPS without changing the MCP application contract.

## Release boundary

- Streamable HTTP endpoint: `/mcp`
- Liveness endpoint: `/health`
- Readiness endpoint: `/ready`
- Node.js: 20 or newer
- Public ingress: Nginx HTTPS only
- Internal listener: `127.0.0.1:8787` for systemd
- Docker host publication: `127.0.0.1:8787:8787`
- Authentication: internal bearer token hash
- Node-to-PHP authentication: HMAC-SHA256 bridge secret
- Operation ceiling: read-only
- External OAuth: not included in this release
- New SQL: none

## 1. Prepare DNS and the VPS

Create an `A` or `AAAA` record for `mcp.microgifter.com` pointing to the VPS.

Recommended Ubuntu 24.04 packages:

```bash
sudo apt update
sudo apt install -y nginx certbot python3-certbot-nginx curl git
```

Install Node.js 20 or newer using the VPS provider's supported method, then verify:

```bash
node --version
npm --version
nginx -v
```

Do not expose port `8787` through the VPS firewall or provider security group. Public inbound access should be limited to the SSH port you actually use plus TCP 80 and 443.

## 2. Deploy the repository package

Extract the current integration deployment ZIP or clone the repository to a protected deployment directory. Run the installer from the repository root:

```bash
sudo deploy/vps/scripts/install-systemd.sh \
  --domain mcp.microgifter.com \
  --source "$(pwd)"
```

The installer:

1. verifies Node.js 20+;
2. creates the non-login `microgifter-mcp` service account;
3. builds and tests a timestamped release;
4. prunes development dependencies;
5. installs the hardened systemd unit;
6. creates `/etc/microgifter/mcp.env` when missing;
7. installs either the TLS bootstrap or final Nginx configuration;
8. activates only when the environment is complete and valid;
9. rolls back the release symlink if restart or readiness fails;
10. retains the five newest releases.

An installer exit code of `2` means the code installed successfully but the environment file still needs its generated values.

## 3. Obtain the TLS certificate

When no certificate exists, the installer enables an HTTP-only ACME bootstrap site. Obtain the certificate:

```bash
sudo certbot certonly \
  --webroot \
  -w /var/www/letsencrypt \
  -d mcp.microgifter.com
```

Rerun the installer after certificate issuance. It will replace the bootstrap site with the final HTTPS reverse proxy and run `nginx -t` before reload.

## 4. Provision the MCP connection

Open:

```text
https://microgifter.com/admin/mcp-connections.php
```

Provision an active connection with at least:

```text
profile:read
catalog:read
```

Generate the one-time runtime credential bundle. Save it securely. The raw bearer token and bridge secret cannot be retrieved later.

## 5. Configure the PHP bridge

Add the generated PHP values to the PHP deployment environment:

```text
MG_MCP_BRIDGE_ENABLED=true
MG_MCP_BRIDGE_SECRET=<generated-shared-secret>
```

The PHP and Node bridge secrets must match exactly. Do not place these values in Git, JavaScript, or a web-accessible file.

## 6. Configure the Node service

Edit the root-owned environment file:

```bash
sudoedit /etc/microgifter/mcp.env
sudo chmod 600 /etc/microgifter/mcp.env
```

Start with `deploy/vps/mcp.env.example`, then replace every placeholder using the one-time Node bundle. Confirm these production values:

```text
MICROGIFTER_MCP_ENV=production
MICROGIFTER_MCP_INTERNAL_HOST=127.0.0.1
MICROGIFTER_MCP_PUBLIC_BASE_URL=https://mcp.microgifter.com
MICROGIFTER_MCP_ALLOWED_HOSTS=mcp.microgifter.com
MICROGIFTER_MCP_BRIDGE_URL=https://microgifter.com/api/internal/mcp-bridge.php
```

`MICROGIFTER_MCP_ALLOWED_ORIGINS` may remain empty for non-browser MCP clients that send no `Origin` header. Add only exact trusted origins when a browser-based client requires them.

Validate and activate:

```bash
sudo deploy/vps/scripts/activate-systemd.sh
```

## 7. Verify service health

Local checks:

```bash
curl --fail http://127.0.0.1:8787/health
curl --fail http://127.0.0.1:8787/ready
sudo systemctl status microgifter-mcp
```

Public checks:

```bash
curl --fail https://mcp.microgifter.com/health
curl --fail https://mcp.microgifter.com/ready
```

The readiness endpoint checks the current database-backed connection through the PHP bridge. A paused, revoked, expired, scope-reduced, inactive-user, or invalid-workspace connection causes readiness to return HTTP 503.

## 8. Run the authenticated MCP smoke test

Use the raw bearer token only in the temporary shell environment:

```bash
cd services/mcp
MCP_SMOKE_BASE_URL=https://mcp.microgifter.com \
MCP_SMOKE_BEARER_TOKEN='<raw-bearer-token>' \
npm run smoke
unset MCP_SMOKE_BEARER_TOKEN
```

The smoke test validates liveness, readiness, protocol revision `2025-11-25`, and the three Phase 1 tools without printing the bearer token.

## 9. Operations

Structured JSON logs are written to journald:

```bash
sudo journalctl -u microgifter-mcp -f
sudo journalctl -u microgifter-mcp --since today --no-pager
```

The optional `deploy/vps/journald/60-microgifter-retention.conf.example` defines global journal retention. Review it before installation because journald limits apply to all system services.

Common operations:

```bash
sudo systemctl restart microgifter-mcp
sudo systemctl stop microgifter-mcp
sudo systemctl start microgifter-mcp
sudo nginx -t && sudo systemctl reload nginx
```

On `SIGTERM` or `SIGINT`, the service immediately becomes not-ready, stops accepting new MCP requests, waits for active requests up to the configured grace period, and then exits. systemd restarts only on failure.

## 10. Docker alternative

Use Docker instead of systemd, not in addition to it:

```bash
MICROGIFTER_MCP_ENV_FILE=/etc/microgifter/mcp.env \
  docker compose -f deploy/vps/docker-compose.mcp.yml up -d --build
```

The container runs as a non-root user, has a read-only filesystem, drops Linux capabilities, uses a health check, and publishes port `8787` only on VPS loopback. Nginx remains the public HTTPS entry point.

## Rollback

The systemd installer uses timestamped releases under `/opt/microgifter-mcp/releases` and an atomic `/opt/microgifter-mcp/current` symlink. If activation or readiness fails, it restores the previous symlink when available.

Manual rollback:

```bash
sudo ls -1dt /opt/microgifter-mcp/releases/*
sudo ln -sfn /opt/microgifter-mcp/releases/<previous-release> /opt/microgifter-mcp/current
sudo systemctl restart microgifter-mcp
curl --fail http://127.0.0.1:8787/ready
```

## Security checklist

- Keep `8787` closed publicly.
- Keep `/etc/microgifter/mcp.env` owned by root with mode `0600`.
- Never store the raw bearer token on the server after client configuration unless the selected harness requires a protected secret store.
- Rotate the bearer token and bridge secret after suspected disclosure.
- Revoke the database connection immediately when access should end.
- Keep the Node service without database credentials.
- Keep external OAuth and write-capable MCP tools disabled until their scoped releases.
