# Microgifter MCP Phase 4B — Automation Definitions and Manual Simulations

Phase 4B turns active Phase 4A grants into owner-created automation definitions and durable manual simulation history. It is safe to deploy on the current PHP hosting environment without Node.js or production MCP keys.

## Included

- `/account-agent-automation-definitions.php` owner workspace.
- One fixed playbook per automation definition.
- Grant, scope, operation ceiling, workspace membership, risk, amount, quantity, frequency, target, and concurrency revalidation.
- Draft, active, paused, and permanently revoked definition states.
- Manual simulation triggers only.
- Durable simulation runs in `mcp_automation_runs`.
- Proposed simulation actions in `mcp_automation_actions`.
- Audit, application-event, security-log, and MCP security-event evidence.

## Simulation boundary

A successful simulation means the current policy would authorize the proposed playbook inputs. It does not call a canonical Microgifter command service. Simulation actions remain `proposed`; no `mcp_action_receipts` row is created because no action attempt occurs.

The following remain disabled:

- Node.js MCP runtime and public transport;
- bearer and HMAC production keys;
- external OAuth activation;
- fixed or recurring schedules;
- canonical-event and condition triggers;
- queues, workers, retries, and dead-letter processing;
- publication, sending, purchase, issuance, delivery, activation, fulfillment, charging, or other external effects.

## SQL

No SQL required. Phase 4B uses the existing automation-foundation tables introduced by `database/20260720_microgifter_mcp_automation_foundation_v1.sql`.
