# Microgifter MCP Installation and Activation

This is the primary reference for installing, validating, and activating the Microgifter MCP service.

## Current state

The PHP authorization layer and database migrations can be deployed before a Node.js VPS is available. Keep the public MCP service and external OAuth switches disabled until DNS, TLS, Nginx, and the Node.js service are ready.

## Reference documents

1. `docs/MICROGIFTER_MCP_PRODUCTION_VPS_DEPLOYMENT_V1.md` installs Node.js, Nginx, TLS, systemd, health checks, logging, and rollback.
2. `docs/MICROGIFTER_MCP_EXTERNAL_AGENT_AUTHORIZATION_PHASE2A.md` activates OAuth, client registration, consent, token rotation, and revocation.
3. `docs/MICROGIFTER_MCP_EXTERNAL_AGENT_SIMULATOR_PHASE2B.md` runs the loopback-only pre-deployment simulator and readiness report.

## Required deployment order

1. Deploy the latest integration files.
2. Run all required migrations.
3. Provision the internal bridge connection and save the one-time credential bundle.
4. Configure the PHP bridge environment.
5. Configure the PHP OAuth environment, but keep OAuth disabled until the Node service is ready.
6. Create DNS for `mcp.microgifter.com`.
7. Install the Node.js service and Nginx reverse proxy.
8. Obtain TLS and validate `/health`, `/ready`, and OAuth discovery.
9. Pre-register the first approved external client.
10. Enable external OAuth and run a real client connection.

## Pre-deployment validation

From `services/mcp`:

```bash
npm ci --ignore-scripts
npm run check
npm run build
node scripts/simulate-external-agent.mjs
node scripts/external-agent-readiness.mjs
```

The simulator uses only loopback networking and sample data. It does not contact Microgifter, ChatGPT, Claude, or any other external service. It must not be used as evidence that public DNS, TLS, Nginx, callbacks, or live client interoperability are working.

## VPS readiness validation

After `/etc/microgifter/mcp.env` has been configured, run the readiness report from `services/mcp` with the environment loaded:

```bash
node scripts/external-agent-readiness.mjs --strict
```

The readiness report never prints secret values. Strict mode exits with code `2` while required files or environment values are incomplete.

## Production activation boundary

Do not claim the external MCP service is production-ready until all of the following are verified on the VPS:

- `https://mcp.microgifter.com/health`
- `https://mcp.microgifter.com/ready`
- protected-resource metadata
- authorization-server metadata
- exact redirect URI registration
- live authorization code and PKCE exchange
- live MCP initialization and tool discovery
- revocation taking effect on the next request

## SQL status

Phase 2B adds no SQL. It relies on the already imported Phase 1 and Phase 2A migrations.
