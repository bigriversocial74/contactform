# Buy-In Phase 9 Ledger Simulator & Reconciliation

Phase 9 adds a read-only simulator for the Buy-In / Share Market execution path.

It does not execute a Share Market action and does not mutate balances, pools, treasuries, series, holders, resale listings, or redemption state.

## What the simulator reads

- approved approval request metadata
- validated manifest
- release-gate result
- current target snapshot from the Stage 20 SQL tables
- dry-run ledger entries from the execution-prep layer
- approval projection values

## What the simulator returns

- current target snapshot
- simulated effects
- dry-run ledger entries
- reconciliation status
- mismatch list
- release-gate result
- proof that no writes were performed

## Reconciliation states

`reconciled_dry_run` means the current SQL snapshot matches the approval projection's current balance where a balance projection exists.

`mismatch` means the current SQL snapshot has drifted from the approval projection. In that case, the request should be revalidated before any future execution release.

## Locked contract

The simulator always returns:

- `executed: false`
- `mutations_performed: false`
- `ledger_entries_inserted: 0`
- `balances_changed: false`
- `market_state_changed: false`

## Future use

A future live runner can use this simulator as the preflight reconciliation step before opening a database transaction. That future runner must still be implemented separately and reviewed independently.
