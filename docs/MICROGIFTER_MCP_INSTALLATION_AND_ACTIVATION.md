# Microgifter MCP Installation and Activation

This is the primary reference for installing, validating, and activating the Microgifter MCP service.

## Current state

The PHP OAuth authority, external-agent review workspace, owner conversion workflow, native handoff-status authority, Phase 4A automation grants, Phase 4B automation definitions/manual simulations, and Phase 4C fixed/recurring scheduled-simulation controls can be deployed before a Node.js VPS is available.

Keep the public MCP endpoint, runtime security keys, external OAuth switches, cron jobs, background schedulers, queues, workers, and execution switches disabled until DNS, TLS, Nginx, Node.js, and the later execution phases are ready.

Current boundary:

```text
external agent: read + reviewable draft creation + read-only handoff status
owner: review + inactive native-draft conversion + bounded grants + simulation definitions + schedule records
runtime: owner-operated manual and due simulations only; no background scheduler, queue, worker, command, receipt, or external effect
```

No MCP tool can publish, send, schedule a native campaign, purchase, issue, deliver, activate, fulfill, charge, or enqueue execution.

## Reference documents

1. `docs/MICROGIFTER_MCP_PRODUCTION_VPS_DEPLOYMENT_V1.md`
2. `docs/MICROGIFTER_MCP_EXTERNAL_AGENT_AUTHORIZATION_PHASE2A.md`
3. `docs/MICROGIFTER_MCP_EXTERNAL_AGENT_SIMULATOR_PHASE2B.md`
4. `docs/MICROGIFTER_MCP_APPROVAL_GATED_DRAFTS_PHASE3A.md`
5. `docs/MICROGIFTER_MCP_INSTALLATION_PHASE3B_SUPPLEMENT.md`
6. `docs/MICROGIFTER_MCP_NATIVE_DRAFT_STATUS_PHASE3C.md`
7. `docs/MICROGIFTER_MCP_AUTOMATION_GRANTS_PHASE4A.md`
8. `docs/MICROGIFTER_MCP_AUTOMATION_DEFINITIONS_PHASE4B.md`
9. `docs/MICROGIFTER_MCP_AUTOMATION_SCHEDULES_PHASE4C.md`

## Required migrations

```text
20260720_microgifter_mcp_automation_foundation_v1
20260720_mcp_external_agent_authorization_phase2a_v1
20260720_mcp_approval_gated_drafts_phase3a_v1
20260720_mcp_approved_draft_conversion_phase3b_v1
```

Phases 3C, 4A, 4B, and 4C require **no new SQL**. Phase 3C uses the existing Microgifter event ledger. Phases 4A–4C use the automation grant, definition, trigger, run, action, receipt, and security-event tables from the foundation migration.

## Current PHP-hosting deployment

On the current non-Node.js hosting environment:

1. Deploy the latest `integration-from-repair-20260628` PHP files.
2. Confirm all four migrations above are imported.
3. Confirm `/account-agent-drafts.php` loads.
4. Confirm `/account-agent-handoffs.php` loads.
5. Confirm `/account-agent-automations.php` loads and displays the build-only runtime boundary.
6. Confirm `/account-agent-automation-definitions.php` loads and displays the simulation-only runtime boundary.
7. Confirm `/account-agent-automation-schedules.php` loads and displays the manual due-evaluation boundary.
8. Do not generate or activate production bearer tokens, bridge HMAC secrets, public OAuth clients, or runtime security keys.
9. Do not configure `mcp.microgifter.com`, Nginx, systemd, Node.js, cron, a background scheduler, queues, or workers on this server.

Phase 4C may store fixed and recurring due records. Those records do nothing until the signed-in owner presses **Evaluate due simulations**. The evaluator creates proposed simulation actions only, creates zero action receipts, and calls no canonical command.

## Future VPS activation order

1. Provision the internal bridge connection and retain the one-time credential bundle.
2. Configure the PHP bridge and OAuth environment, but leave external OAuth disabled.
3. Create DNS for `mcp.microgifter.com`.
4. Install Node.js, Nginx, TLS, and the MCP systemd service.
5. Validate `/health`, `/ready`, and OAuth discovery.
6. Pre-register a client with the minimum required scopes.
7. Enable external OAuth and run a real client connection.
8. Verify review, inactive conversion, read-only handoff status, grant controls, definitions, manual simulations, schedule records, and owner due evaluation while execution queues remain unchanged.
9. Do not activate a background scheduler, workers, canonical commands, approval-gated external effects, or bounded-auto actions until their later phases are separately built, reviewed, and approved.

## Pre-deployment validation

```bash
cd services/mcp
npm ci --ignore-scripts
npm run check
npm run build
node scripts/simulate-external-agent.mjs
node scripts/external-agent-readiness.mjs
```

```bash
vendor/bin/phpunit tests/phpunit/McpNativeDraftStatusPhase3cV1ContractTest.php
vendor/bin/phpunit tests/phpunit/McpAutomationGrantsPhase4aV1ContractTest.php
vendor/bin/phpunit tests/phpunit/McpAutomationDefinitionsPhase4bV1ContractTest.php
vendor/bin/phpunit tests/phpunit/McpAutomationSchedulesPhase4cV1ContractTest.php
php scripts/run_migrations.php
php scripts/test_mcp_approval_gated_drafts_phase3a.php
php scripts/test_mcp_approved_draft_conversion_phase3b.php
php scripts/test_mcp_native_draft_status_phase3c.php
php scripts/validate_mcp_automation_grants_phase4a.php
php scripts/validate_mcp_automation_definitions_phase4b.php
php scripts/validate_mcp_automation_schedules_phase4c.php
php scripts/test_mcp_automation_definitions_phase4b.php
php scripts/test_mcp_automation_schedules_phase4c.php
```

## VPS readiness

After `/etc/microgifter/mcp.env` is configured:

```bash
cd services/mcp
node scripts/external-agent-readiness.mjs --strict
```

Strict mode exits with code `2` while required configuration is incomplete and never prints secret values.

## First client

Start with one preregistered pilot client.

```text
profile:read
catalog:read
gift:draft
```

Campaign, reward, and message drafts require a merchant-workspace connection. Dynamic registration remains read-only.

## Production checklist

Verify all of the following on the future VPS:

- `https://mcp.microgifter.com/health` and `/ready`;
- protected-resource and authorization-server metadata;
- exact redirect URI and PKCE exchange;
- live MCP initialization and tool discovery;
- revocation and scope filtering;
- owner review at `/account-agent-drafts.php`;
- owner conversion creates inactive native drafts only;
- `/account-agent-handoffs.php` reads canonical native state;
- `microgifter.drafts.get` and `microgifter.drafts.list` include `handoff`;
- `/account-agent-automations.php` creates only bounded authority records;
- `/account-agent-automation-definitions.php` creates grant-bound simulation definitions;
- `/account-agent-automation-schedules.php` creates due records and owner-evaluated simulations only;
- activating a grant creates no automation definition, trigger, run, action, or receipt;
- activating a definition starts no background scheduler or worker;
- fixed and recurring due records never fire without the explicit owner evaluator in Phase 4C;
- Phase 4B and 4C simulations create proposed actions but zero action receipts;
- no review, conversion, status, grant, definition, schedule, or simulation operation executes `agent_workflow_actions` or a canonical command.

Phase 3C reports native handoff status. Phase 4A records bounded owner authority. Phase 4B creates simulation-only definitions. Phase 4C adds fixed and recurring due records with owner-operated evaluation. None performs an external-effect action or enables runtime execution.
