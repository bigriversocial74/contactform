# Microgifter MCP Installation and Activation

This is the primary reference for installing, validating, and activating the Microgifter MCP service.

## Current state

The PHP authorization layer, draft review workspace, and database migrations can be deployed before a Node.js VPS is available. Keep the public MCP service and external OAuth switches disabled until DNS, TLS, Nginx, and the Node.js service are ready.

Current operation ceiling:

```text
reviewable drafts only
```

Approval records a human decision but does not publish, send, purchase, schedule, activate, fulfill, or execute.

## Reference documents

1. `docs/MICROGIFTER_MCP_PRODUCTION_VPS_DEPLOYMENT_V1.md` installs Node.js, Nginx, TLS, systemd, health checks, logging, and rollback.
2. `docs/MICROGIFTER_MCP_EXTERNAL_AGENT_AUTHORIZATION_PHASE2A.md` activates OAuth, client registration, consent, token rotation, and revocation.
3. `docs/MICROGIFTER_MCP_EXTERNAL_AGENT_SIMULATOR_PHASE2B.md` runs the loopback-only pre-deployment simulator and readiness report.
4. `docs/MICROGIFTER_MCP_APPROVAL_GATED_DRAFTS_PHASE3A.md` installs reviewable gift, campaign, reward, and message drafts.

## Required migrations

The canonical migration runner must include:

```text
20260720_microgifter_mcp_automation_foundation_v1
20260720_mcp_external_agent_authorization_phase2a_v1
20260720_mcp_approval_gated_drafts_phase3a_v1
```

The Phase 3A file is:

```text
database/20260720_mcp_approval_gated_drafts_phase3a_v1.sql
```

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
10. Choose a `read` or `draft` maximum operation class.
11. Grant only the minimum required scopes.
12. Enable external OAuth and run a real client connection.
13. For draft clients, verify `/account-agent-drafts.php` and prove approval creates no execution-queue rows.

## Pre-deployment validation

From `services/mcp`:

```bash
npm ci --ignore-scripts
npm run check
npm run build
node scripts/simulate-external-agent.mjs
node scripts/external-agent-readiness.mjs
```

PHP and clean-database checks:

```bash
vendor/bin/phpunit tests/phpunit/McpApprovalGatedDraftsPhase3aV1ContractTest.php
php scripts/run_migrations.php
php scripts/test_mcp_approval_gated_drafts_phase3a.php
```

The simulator uses only loopback networking and sample data. It does not contact Microgifter, ChatGPT, Claude, or any other external service. It must not be used as evidence that public DNS, TLS, Nginx, callbacks, or live client interoperability are working.

## VPS readiness validation

After `/etc/microgifter/mcp.env` has been configured, run the readiness report from `services/mcp` with the environment loaded:

```bash
node scripts/external-agent-readiness.mjs --strict
```

The readiness report never prints secret values. Strict mode exits with code `2` while required files or environment values are incomplete.

## First client profile

Start with one preregistered internal pilot client.

Recommended read-only scopes:

```text
profile:read
catalog:read
```

Recommended first draft pilot:

```text
profile:read
catalog:read
gift:draft
```

Campaign, reward, and message drafts require a merchant-workspace connection. Dynamic client registration remains read-only.

## Production activation boundary

Do not claim the external MCP service is production-ready until all of the following are verified on the VPS:

- `https://mcp.microgifter.com/health`;
- `https://mcp.microgifter.com/ready`;
- protected-resource metadata;
- authorization-server metadata;
- exact redirect URI registration;
- live authorization code and PKCE exchange;
- live MCP initialization and tool discovery;
- revocation taking effect on the next request;
- draft scope filtering;
- owner review at `/account-agent-drafts.php`;
- approved drafts remain `execution.enabled=false`;
- no rows are added to `agent_workflow_actions` or `mcp_automation_actions` by the draft lifecycle.

## Current execution boundary

The MCP service has no tool that can:

- publish a campaign;
- send or schedule a message;
- purchase, issue, or deliver a gift;
- activate or fulfill a reward;
- charge a payment method;
- enqueue a Task Agent action;
- enqueue an MCP automation action.

A later phase must add a separate human-authorized conversion workflow before an approved draft can become a live Microgifter object.
