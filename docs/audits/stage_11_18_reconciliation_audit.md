# Stage 11–18 Reconciliation Audit

## Scope

This audit compares the merged Stage 11–18 implementation against the canonical Stage 1–10 authorities and the Stage 11–18 design documents. The review focuses on ownership boundaries, lifecycle authority, transaction completion, idempotency, queue processing, pagination, and operational recoverability.

## Confirmed and repaired findings

### Stage 14 — descending feed cursor was reversed

The feed orders posts by descending ID but previously filtered with `id > cursor`. Subsequent pages therefore selected newer records rather than the next older records.

Repair: the cursor is optional on the first page and uses `id < cursor` for subsequent pages.

### Stage 16 — failed final actions left workflow runs executing

The action processor reconciled the parent workflow only after a successful action. A failed final action was marked failed, but its workflow could remain in `executing` indefinitely.

Repair: parent workflow reconciliation now runs after both successful and failed actions. Terminal runs become `completed` or `partially_completed` according to their action outcomes.

### Stage 17 — routed swarm tasks created empty Stage 16 runs

The swarm processor directly inserted a queued Stage 16 workflow run without planning actions. No approved action could reach the Stage 16 processor, leaving swarm tasks permanently routed.

Repair: Stage 17 now delegates workflow creation and planning to `mg_agent_create_run()` and `mg_agent_plan_run()`. An executable swarm task must provide an `actions` array in its task input. Stage 16 remains the sole planning, approval, and execution authority.

### Stage 17 — review-required tasks had no completion path

Successful tasks marked `requires_review` entered `review_pending`, but no API allowed the owner to approve or reject them. Dependent tasks and the parent swarm could remain blocked indefinitely.

Repair: `POST /api/agents/swarm-review.php` provides owner-scoped, CSRF-protected, row-locked approval and rejection. Approval releases eligible dependencies and both decisions reconcile the parent swarm.

### Stage 17 — execution budget fields were ambiguous

The processor selected run and task columns with overlapping names and then evaluated the wrong values. Budget exhaustion checks could use task-level defaults instead of the parent run totals.

Repair: run budget, reserved, and consumed fields are explicitly aliased. Consumed units are bounded with `LEAST(budget_units, ...)` during synchronization.

## Preserved authorities

The repairs do not introduce alternate business authorities:

- Stage 14 remains a presentation/read boundary.
- Stage 16 remains the sole agent action planning, approval, and execution authority.
- Stage 17 coordinates teams, routing, dependencies, reviews, and abstract execution budgets only.
- No repair directly mutates payment, wallet, ledger, entitlement, PPPM, claim, redemption, or Microgift ownership truth.

## Open findings for separate repair scopes

### Stage 11 — Action Center messaging authority

`action-center-message.php` stores message content in generic event payloads. This does not appear to use the durable messaging and delivery foundation described by the Stage 11 documentation. The current Stage 11 test explicitly requires `INSERT INTO events`, so the implementation and its test likely encode the same architectural defect.

Recommended scope: establish the canonical durable message/thread authority, migrate Action Center messaging to it, and retain events only as append-only notifications or audit receipts.

### Stage 13 — initial subscription funding

A non-trial subscription becomes active immediately while its first renewal is scheduled at the end of the first period. This appears capable of granting the initial subscription period before successful funding.

Recommended scope: define an explicit initial-payment lifecycle, including pending activation, webhook success, failure handling, entitlement timing, and compatibility migration for existing subscriptions.

### Stage 15 — demand snapshot lower time bound

Demand snapshot aggregation applies the horizon end but requires additional validation that historical rows before the snapshot start cannot contaminate the window.

Recommended scope: focused Stage 15 time-window audit with fixtures covering old, in-window, and future demand observations.

## Regression coverage

`Stage11To18ReconciliationTest` protects:

- descending Stage 14 cursor direction;
- Stage 16 failure reconciliation;
- canonical Stage 17 delegation into Stage 16 planning;
- the Stage 17 review completion endpoint.

## Validation status

Repository CI validation is required before merge. Database-backed smoke tests should additionally exercise:

1. a low-risk swarm action that completes automatically;
2. a high-risk swarm action that waits for Stage 16 approval;
3. a review-required swarm task that releases a dependent task after approval;
4. a failed final Stage 16 action that terminates its workflow;
5. two consecutive descending feed pages with no duplicates or skipped cursor direction.
