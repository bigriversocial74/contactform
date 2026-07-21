# Microgifter MCP Phase 4A — Bounded Automation Grants

**Release:** `microgifter_mcp_automation_grants_phase4a_v1`  
**Deployment posture:** PHP code may be deployed before the Node.js VPS exists.  
**SQL:** No new SQL required.

## Purpose

Phase 4A turns the automation-ready control-plane schema into an owner-controlled authority layer. A signed-in Microgifter owner can create a durable grant for one existing MCP connection, select only fixed Microgifter playbooks, define limits and targets, and then activate, pause, resume, expire, or permanently revoke that grant.

A grant is an authorization record. It is **not** an automation, schedule, queue item, worker job, purchase instruction, campaign launch, message send, gift issuance, or reward activation.

## Current build-only boundary

The following may be deployed on the current PHP hosting environment:

- `/account-agent-automations.php`;
- owner grant creation and lifecycle controls;
- fixed playbook and tool allowlists;
- scope, operation-ceiling, connection, workspace, expiration, and risk validation;
- amount, quantity, frequency, concurrency, and target policies;
- revocation-version increments and cancellation requests for any future queued work;
- audit, application-event, security-log, and `mcp_security_events` evidence;
- the fail-closed grant evaluator future schedulers and workers must call.

The following remain disabled until later phases and VPS activation:

- Node.js MCP runtime;
- public MCP transport;
- production bearer or HMAC key activation;
- automation definitions;
- fixed or recurring schedules;
- canonical-event triggers;
- queue and worker processing;
- any external-effect action.

## Approved Phase 4A playbooks

Phase 4A accepts exact playbook keys only. Arbitrary tool names are rejected.

| Playbook | Operation ceiling | Required scopes | Boundary |
|---|---|---|---|
| `catalog_research` | `read` | `profile:read`, `catalog:read` | Published-catalog and account-context reads only |
| `gift_draft_preparation` | `draft` | `catalog:read`, `gift:draft` | Reviewable gift draft only |
| `campaign_draft_preparation` | `draft` | `campaign:draft` | Merchant-workspace reviewable campaign draft only |
| `reward_draft_preparation` | `draft` | `reward:draft` | Merchant-workspace reviewable reward draft only |
| `message_draft_preparation` | `draft` | `message:draft` | Merchant-workspace reviewable message draft only |

Each playbook expands into a fixed tool allowlist. The selected connection must currently hold every required active scope. Merchant playbooks require a merchant-workspace-bound connection.

## Grant lifecycle

```text
draft -> active -> paused -> active
  |        |         |
  +--------+---------+-> revoked
expired ----------------> revoked
```

Activation revalidates:

- connection and client are active;
- connection and grant are not expired;
- grant operation class does not exceed the client or connection ceiling;
- required scopes remain active;
- merchant workspace remains available;
- the authorizing user still owns or belongs to that workspace;
- every playbook still exists in the server allowlist.

Pausing or revoking increments the grant revocation version. Any future run in a queued or executing lifecycle state receives a cancellation request. Permanent revocation also revokes future automation definitions attached to the grant.

## Limits and policy

The owner may define:

- per-run, daily, and lifetime amount limits;
- per-run, daily, and lifetime quantity limits;
- a minimum frequency of one hour or slower;
- one concurrent run;
- low or medium risk ceiling;
- expiration from 7 to 365 days;
- product, campaign, and reward-template UUID restrictions;
- published-catalog and existing-contact restrictions.

The approval policy is fixed to `always` in Phase 4A. The only allowed trigger family is `manual`. Runtime execution remains disabled even when a grant is active.

## Future worker contract

`mg_mcp_automation_authorize_grant_action()` is the mandatory fail-closed policy evaluator for later scheduler and worker phases. It validates the active connection, client, grant, scopes, workspace access, operation class, risk, amount, quantity, daily and lifetime usage, frequency, target policy, concurrency, expiration, and revocation version.

A later phase must not create or execute a run without this evaluation plus fresh-state, idempotency, approval, and canonical Microgifter command checks.

## Deployment

1. Deploy the latest `integration-from-repair-20260628` PHP files.
2. Confirm the four existing MCP migrations are imported.
3. Open `/account-agent-automations.php`.
4. Confirm the page clearly reports that Node, keys, schedules, queues, workers, and execution are disabled.
5. No Node.js, DNS, TLS proxy, systemd, OAuth activation, or production key step is required for this code-only deployment.

## Validation

```bash
php -l includes/mcp-automations.php
php -l account-agent-automations.php
php scripts/validate_mcp_automation_grants_phase4a.php
vendor/bin/phpunit tests/phpunit/McpAutomationGrantsPhase4aV1ContractTest.php
php scripts/run_migrations.php
```

## Next phase

Phase 4B should add owner-created automation definitions and simulation-only manual runs against active grants. It must still create no schedule, queue, worker, or external effect.
