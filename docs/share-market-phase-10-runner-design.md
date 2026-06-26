# Buy-In Phase 10 Runner Design Specification

Phase 10 is documentation only. It defines the reviewed design for a future Buy-In runner, but it does not add the runner itself.

## Scope

This phase documents the control model that must exist before any production finalization path is considered.

It does not add:

- a new finalization endpoint
- balance-changing code
- credit-ledger insertion code
- resale handling
- redemption handling
- holder transfer handling
- market-launch behavior

## Relationship to previous phases

Phase 8 added the release gate. Phase 9 added the read-only ledger simulator and reconciliation layer.

A future runner must treat both as mandatory preflight controls.

Required order:

1. evaluate release gate
2. run simulator reconciliation
3. reject on mismatch
4. open a database transaction only after both controls pass
5. lock rows in the documented order
6. write audited records only after every invariant passes
7. mark the approval request final only after all related records are committed

## Required inputs

A future runner may only accept:

- approval request public ID
- explicit run mode
- server-computed idempotency key
- fresh password verification
- typed operator confirmation

It must not accept client-provided balances, counters, status values, holder values, DAVE values, or ledger payloads.

## Lock order

To reduce deadlock risk, locks must be acquired in this order:

1. approval request
2. approval decisions
3. primary target row
4. related pool or treasury row
5. related series row
6. related holder row
7. related risk flags
8. latest hash-chain checkpoint
9. idempotency lookup

If any lock fails, the transaction must roll back.

## Transaction outline

The future transaction must follow this shape:

1. begin transaction
2. lock approval request
3. verify request remains approved and not finalized
4. recompute manifest hash
5. recompute idempotency key
6. lock target rows
7. rerun simulator reconciliation inside the transaction
8. apply one approved domain change
9. write one audit event
10. write one hash-chain checkpoint
11. write one credit-ledger row only when the action has a balance impact
12. mark the approval request finalized
13. commit transaction

Any failure before commit must leave the approval request unfinalized.

## Invariant rules

A future runner must reject when:

- release gate is blocked
- simulator reconciliation reports mismatch
- requester is the same as the operator
- approval request is expired, rejected, cancelled, failed, or already finalized
- idempotency key was already used
- target row is missing
- target row changed after the simulator snapshot
- balance math fails
- a protected value would become negative
- hash-chain checkpoint cannot be prepared
- a risk flag remains open for the target

## Audit records

Every finalized operation must leave enough data to reconstruct what happened.

Audit payloads must include:

- approval request public ID
- manifest hash
- actor user ID
- target type
- target public ID
- before snapshot
- after snapshot
- simulator version
- release-gate version
- idempotency key
- timestamp

## Hash-chain rules

The checkpoint record must reference the previous checkpoint for the same source table and public ID when one exists.

If no checkpoint exists, the first checkpoint must be marked as the origin checkpoint in metadata.

## Idempotency rules

The same approval request must not finalize more than once.

A retry may be allowed only when no prior commit happened and no idempotency record exists.

## Failure states

Future implementations should use these reviewable states:

- blocked_by_release_gate
- blocked_by_reconciliation_mismatch
- blocked_by_risk_flag
- blocked_by_idempotency_replay
- failed_lock_timeout
- failed_invariant_check
- failed_hash_chain_checkpoint
- failed_rollback_safe
- finalized

Only the final state may mark the approval request complete.

## Future implementation sequence

The future implementation must be split into separate PRs:

1. storage and audit scaffolding with no domain changes
2. one low-risk state-only operation
3. broader operation support after concurrency and rollback tests pass

The first supported operation should be a state-only admin action, not a balance-impacting action.
