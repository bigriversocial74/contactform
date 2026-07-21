# Microgifter MCP External Agent Simulator — Phase 2B

## Purpose

Phase 2B provides a headless, loopback-only simulator for validating the external-agent connection contract before a Node.js VPS is available. It is a development and CI tool, not a public sandbox and not a substitute for live client testing.

The simulator uses the actual compiled MCP HTTP application with sample connection and catalog data. It does not contact Microgifter, ChatGPT, Claude, or any third-party service.

## What it validates

For ChatGPT, Claude, and a generic remote MCP profile, the simulator covers:

- protected-resource metadata discovery;
- OAuth bearer challenge;
- authorization code and PKCE S256 behavior;
- exact simulated redirect URI matching;
- resource indicator matching;
- dynamic access-token resolution;
- MCP protocol initialization using revision `2025-11-25`;
- `tools/list` scope filtering;
- a real `microgifter.catalog.search` tool call through the MCP registry;
- invocation receipt creation;
- refresh-token rotation;
- refresh replay detection and full simulated family revocation;
- explicit connection revocation.

The existing PHP clean-database Phase 2A test remains authoritative for database-backed authorization-code, token hash, rotation, and replay behavior.

## Safety boundary

The simulator:

- binds only to `127.0.0.1` on an ephemeral port;
- uses generated sample credentials and sample catalog data;
- refuses to run when `NODE_ENV=production` or `MICROGIFTER_MCP_ENV=production`;
- stores nothing in the production database;
- performs no external network requests;
- enables no write-capable tools;
- creates no scheduled or autonomous activity.

## Run the simulator

From the repository root:

```bash
cd services/mcp
npm ci --ignore-scripts
npm run build
node scripts/simulate-external-agent.mjs
```

Run one compatibility profile:

```bash
node scripts/simulate-external-agent.mjs --profile=chatgpt
node scripts/simulate-external-agent.mjs --profile=claude
node scripts/simulate-external-agent.mjs --profile=generic
```

The command prints a JSON report. A successful report still lists the production checks that require the VPS.

## Readiness report

From `services/mcp`:

```bash
node scripts/external-agent-readiness.mjs
```

The report checks:

- Node.js 20 or newer;
- required deployment and reference files;
- whether required environment variables are present;
- whether canonical public URLs match the Microgifter deployment contract;
- whether a bridge secret is present without printing it.

Use strict mode only on the configured VPS:

```bash
node scripts/external-agent-readiness.mjs --strict
```

Strict mode exits with code `2` while required environment values are incomplete.

## CI combination

The Phase 2B workflow runs:

1. the complete Node test suite;
2. the loopback external-agent simulator;
3. the non-secret readiness report;
4. the PHP Phase 2A clean-database authorization lifecycle;
5. the Phase 2B release contract;
6. repository production-quality auditing.

## Production checks that remain blocked

These cannot be proven by the simulator:

- public DNS for `mcp.microgifter.com`;
- public TLS;
- Nginx reverse-proxy behavior;
- the persistent Node.js process;
- provider-supplied exact callback URLs;
- live ChatGPT or Claude interoperability;
- public rate limiting and firewall behavior.

Follow `docs/MICROGIFTER_MCP_INSTALLATION_AND_ACTIVATION.md` when the VPS becomes available.

## SQL

No new SQL is required for Phase 2B.
