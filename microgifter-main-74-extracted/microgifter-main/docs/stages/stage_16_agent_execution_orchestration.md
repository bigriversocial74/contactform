# Stage 16 — Agent Execution and Orchestration

Stage 16 converts saved Stage 3 agents and Stage 15 demand signals into controlled operational workflows. It adds strategy definitions, idempotent workflow runs, explicit action plans, approval queues, canonical action adapters, execution processing, and append-only audit events.

## Safety model

Agents do not receive unrestricted database or financial authority. Every executable action must:

1. appear in the strategy action catalog;
2. use an explicitly supported Stage 16 adapter;
3. target a resource owned by the authenticated merchant;
4. pass lifecycle validation;
5. receive approval when required by strategy or risk level;
6. execute idempotently through the processor;
7. record planning, approval, completion, or failure events.

High- and critical-risk actions always require approval. Strategy-level approval requirements may require approval for lower-risk actions as well.

## Supported actions

The first execution catalog supports:

- acknowledge a Stage 15 demand signal;
- resolve a Stage 15 demand signal;
- pause an owned distribution program;
- resume an owned distribution program;
- create an operational alert through the existing communications authority.

Stage 16 does not grant agents direct authority over payments, wallets, ledger entries, tips, subscriptions, Microgift ownership, claims, redemptions, entitlements, or PPPM lifecycle state.

## Strategy lifecycle

`draft → active → paused → active`

Strategies may be retired. A retired strategy can be restored to draft for review and a new activation decision.

## Workflow lifecycle

`queued → planning → approval_pending|approved → executing → completed|partially_completed|failed`

Runs are owner-scoped and idempotent. Actions are sequence-numbered and independently tracked. Rejected actions remain visible in the run history and do not disappear from the audit trail.

## Approval lifecycle

`pending → approved|rejected|expired|canceled`

Only the workflow owner can decide an approval. Decisions are row-locked, time-bounded, audited, and reflected on the associated action.

## APIs and processor

- `GET|POST /api/agents/strategies.php`
- `POST /api/agents/strategy-state.php`
- `GET|POST /api/agents/runs.php`
- `GET|POST /api/agents/approvals.php`
- `php scripts/process_agent_actions.php [limit]`
- `php scripts/stage16.php`
- `php scripts/stage16_smoke.php`

## Preserved authorities

- saved agent ownership remains in the Stage 3 `agents` table;
- demand signals remain canonical in Stage 15;
- distribution program lifecycle remains canonical in the distribution foundation;
- alerts remain canonical in the communications foundation;
- Stage 16 stores orchestration state and execution receipts, not duplicate business ownership or financial truth.

## Deferred to Stage 17

Multi-agent teams, delegated specialist roles, cross-agent dependency graphs, model/provider routing, execution budgets, conflict arbitration, and swarm-level observability remain Stage 17 work.
