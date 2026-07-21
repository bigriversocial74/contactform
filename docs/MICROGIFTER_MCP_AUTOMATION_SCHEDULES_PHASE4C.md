# Microgifter MCP Phase 4C — Scheduled Simulations

Phase 4C adds durable fixed and recurring schedule records to Phase 4B automation definitions. It remains a PHP-only, simulation-only build.

## Owner flow

1. The owner explicitly authorizes fixed and/or recurring simulation triggers on a bounded Phase 4A grant.
2. The owner selects an active Phase 4B definition, timezone, first due time, and optional recurring interval.
3. Microgifter stores `next_due_at` on the existing automation trigger record.
4. Nothing runs in the background.
5. The signed-in owner opens `/account-agent-automation-schedules.php` and presses **Evaluate due simulations**.
6. Each due trigger revalidates the connection, client, scopes, workspace membership, grant, risk, amounts, quantities, frequency, concurrency, and target restrictions.
7. A successful evaluation records a succeeded simulation run and `proposed` actions with approval required.
8. It creates zero action receipts and calls no canonical command.
9. A fixed trigger expires; a recurring trigger advances to its next future due time.

## Current-server deployment boundary

The PHP code may be deployed now. Do not configure cron, a queue runner, Node.js, public MCP transport, bearer or HMAC secrets, external OAuth activation, or production runtime keys.

The schedule page is an owner-operated control-plane simulator. It is not a scheduler daemon.

## Safety properties

- Manual simulation remains available.
- Schedule authority is explicit and revocable.
- Removing authority pauses matching future triggers.
- Definition or grant pause/revoke prevents firing.
- Scheduled run idempotency is derived from trigger ID plus due time.
- Maximum owner-evaluated batch is ten due triggers.
- Recurrence is no faster than one hour and must respect the grant frequency limit.
- All actions remain `proposed` and approval-required.
- `mcp_action_receipts` remains unchanged.
- `agent_workflow_actions` remains unchanged.

## SQL

No SQL is required. Phase 4C uses `mcp_automations`, `mcp_automation_triggers`, `mcp_automation_runs`, and `mcp_automation_actions` from `database/20260720_microgifter_mcp_automation_foundation_v1.sql`.
