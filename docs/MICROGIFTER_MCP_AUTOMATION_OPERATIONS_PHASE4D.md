# Microgifter MCP Phase 4D — Operations and Emergency Controls

## Purpose

Phase 4D adds the owner-operated control and evidence layer for the existing MCP automation foundation. It does not activate the Node.js MCP runtime, external OAuth clients, security keys, a scheduler, a queue worker, canonical command execution, or external-effect actions.

## Owner workspace

`/account-agent-automation-operations.php`

The page provides:

- owner-scoped counts for grants, definitions, triggers, runs, proposed actions, cancellation requests, due schedules, and action receipts;
- live MCP connection and client health findings;
- recent run history with durable cancellation-request state;
- recent MCP automation security events;
- an owner-wide emergency pause;
- a connection-scoped automation pause;
- cancellation requests for mutable future-worker run states.

## Emergency pause semantics

The owner-wide emergency pause:

1. changes every active owner grant to `paused`;
2. increments each affected grant revocation version;
3. pauses active automation definitions;
4. pauses active triggers and clears `next_due_at`;
5. clears definition `next_run_at` values;
6. requests cancellation for queued, evaluating, waiting-for-approval, approved, or executing runs;
7. writes audit, application-event, security-log, and MCP security-event evidence.

There is no bulk resume. Owners must revalidate and resume grants individually through the existing grant controls, then explicitly re-enable definitions and schedules.

## Connection isolation

The connection pause performs the same fail-closed changes, but only for automation records attached to one owner-authorized MCP connection.

## Run cancellation

Phase 4D stores `cancellation_requested_at` for mutable run states. Current simulation runs complete immediately and are terminal. A future worker must check cancellation before any attempt, but no worker exists in this phase.

## Deployment boundary

The PHP code may be deployed on the current server. Do not configure Node.js, `mcp.microgifter.com`, bearer or HMAC keys, external OAuth activation, cron, background schedulers, queues, or workers.

## SQL

No SQL required. Phase 4D reuses the existing MCP automation foundation tables.
