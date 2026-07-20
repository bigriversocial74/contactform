# Microgifter MCP Phase 1 Foundation Runbook

## Release

`microgifter_mcp_phase1_foundation_v1`

## Purpose

This section installs the durable MCP automation control plane and the disabled TypeScript service foundation. It does not expose an MCP endpoint and cannot execute an automation.

## Required migration

```text
database/20260720_microgifter_mcp_automation_foundation_v1.sql
```

The migration is additive and must be imported after `20260720_task_agent_phase4_v1.sql`.

## Deployment sequence

1. Deploy the merged integration branch.
2. Run the canonical migration runner or import the required migration through the normal production process.
3. Confirm all 16 `mcp_*` foundation tables exist.
4. Confirm only `profile:read` and `catalog:read` are active and grantable.
5. Confirm no MCP, scheduler, worker, OAuth, write-tool, or bounded-automation environment flag is enabled.
6. Run the PHP contract validator and the TypeScript service checks.

## Validation

```bash
php scripts/validate_mcp_phase1_foundation.php
vendor/bin/phpunit tests/phpunit/McpPhase1FoundationV1ContractTest.php
cd services/mcp
npm ci
npm run check
```

## Rollback

1. Keep all MCP runtime flags disabled.
2. Stop any future MCP process, scheduler or worker before rolling back code.
3. Roll back application/service code.
4. Preserve additive MCP audit, grant, run, receipt and security records.
5. Do not drop the MCP foundation tables during an ordinary application rollback.
6. Do not modify Task Agent, Approval Center, order, PPPM or Microgift lifecycle records.

## Safety posture

- No public endpoint.
- No external OAuth.
- No active automation.
- No write-capable tool.
- No Node database credentials.
- No unbounded mode.
