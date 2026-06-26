# Buy-In Phase 8 Release Gate

Phase 8 adds release-gate evaluation for the Buy-In / Share Market execution path.

This phase does not add live execution. It evaluates whether an approved request has the required safety posture for a future runner while keeping the current runner blocked.

## Gate checks

- approved request required
- super administrator unlock policy
- requester cannot execute their own approved request
- explicit execution feature flag required
- explicit legal release flag required
- idempotency replay protection
- balance invariant checks
- hash-chain checkpoint readiness
- dry-run/live-run separation

## Required flags for a future release

- `MICROGIFTER_SHARE_MARKET_EXECUTION_ENABLED=1`
- `MICROGIFTER_SHARE_MARKET_LEGAL_RELEASE=1`

Even when those flags are present, Phase 8 still returns `eligible_for_live_execution: false` and `release_gate_passed: false` because the live runner is not part of this phase.

## Locked response contract

The runner remains blocked and returns:

- `executed: false`
- `execution_enabled: false`
- `can_execute: false`
- `runner_state: release_gate_blocked_stub`

## Future live runner requirements

A later release must add a separate reviewed live runner and prove:

- idempotent writes
- transaction locks
- balance invariants
- maker-checker separation
- audited legal release
- hash-chain append behavior
- rollback behavior
