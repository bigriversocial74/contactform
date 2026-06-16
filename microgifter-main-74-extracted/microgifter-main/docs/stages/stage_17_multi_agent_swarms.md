# Stage 17 — Multi-Agent Teams and Swarms

Stage 17 coordinates multiple owned Stage 3 agents while preserving Stage 16 as the sole action-execution authority.

## Team model

A team defines:

- an owner and objective;
- coordination mode;
- conflict policy;
- parallel task limit;
- default execution budget;
- specialist members using existing saved agents;
- role keys, capabilities, routing profiles, priorities, and concurrency limits;
- optional provider/model routing profiles.

Team lifecycle is `draft → active → paused → active`, with retirement and restore-to-draft support.

## Swarm runs

Swarm runs are owner-scoped and idempotent. A run contains a directed acyclic task graph with explicit task keys, objectives, capabilities, dependencies, priorities, review requirements, and unit estimates.

Creation validates:

- unique task keys;
- known dependency targets;
- no self-dependencies;
- no dependency cycles;
- total estimated units within the run budget;
- active team ownership;
- owned Stage 16 strategies where execution is requested.

## Routing

Ready tasks are routed by:

1. required capability;
2. active team membership;
3. member concurrency limits;
4. member priority;
5. optional provider/model route priority.

Equal-priority routing candidates create a durable conflict record instead of silently discarding the ambiguity.

Provider routes are planning metadata only. Stage 17 does not directly call external model providers or store provider secrets.

## Execution boundary

Stage 17 never executes business actions directly. Routed tasks create idempotent Stage 16 workflow runs. The routed team member must own the same agent as the selected Stage 16 strategy. Stage 16 approval and action adapters remain authoritative.

This preserves all existing business authorities, including Microgift lifecycle, PPPM, claims, redemption, entitlements, payments, wallets, ledger, tips, subscriptions, demand signals, distribution programs, and communications.

## Budgets

Run budgets and task estimates use abstract execution units. They are operational controls, not money, wallet balances, invoices, or ledger entries. Stage 17 tracks reserved and consumed units and blocks work that cannot fit within the configured budget.

## Conflicts

Conflicts may represent routing, result, budget, dependency, or policy disputes. Resolution is owner-scoped, row-locked, audited, and retained in the run event history.

Supported team policies are:

- owner decides;
- lead agent;
- majority;
- highest confidence.

Stage 17 records the policy and resolution. Automated arbitration that changes canonical business state remains prohibited.

## Observability

The observability API exposes:

- run state;
- task state and routing;
- linked Stage 16 workflow runs;
- provider-route metadata;
- reserved and consumed budget;
- execution events;
- open and resolved conflicts;
- task outputs and failures.

## APIs and processors

- `GET|POST /api/agents/teams.php`
- `GET|POST /api/agents/swarm-runs.php`
- `GET|POST /api/agents/swarm-conflicts.php`
- `GET /api/agents/swarm-observability.php`
- `php scripts/process_swarm_tasks.php [limit]`
- `php scripts/stage17.php`
- `php scripts/stage17_smoke.php`

## Deferred to Stage 18

Production hardening, retention and archival policy, operational runbooks, launch readiness, system-wide observability, incident controls, deployment gates, and final architecture reconciliation remain Stage 18 work.
