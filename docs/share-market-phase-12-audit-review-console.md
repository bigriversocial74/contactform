# Buy-In Phase 12 Audit Review Console

Phase 12 adds a read-only admin console for reviewing Phase 11 audit scaffolding records.

## Page

`/account-share-market-execution-audit.php`

The page requires `share_market.admin` and uses the existing account/admin shell.

## APIs

- `GET /api/admin/share-market/execution-audit-list.php`
- `GET /api/admin/share-market/execution-audit-detail.php`

Both APIs are read-only and require Share Market Admin permission.

## Console views

The console shows:

- audit attempts
- attempt status and run mode
- release-gate status
- simulator status
- preflight snapshots
- operator signoffs
- legal evidence
- rollback evidence
- idempotency reservations
- approval request metadata

## Filters

The list endpoint and UI support filtering by:

- status
- run mode
- target type
- target public ID
- approval request public ID
- text search across attempt, request, target, and idempotency key

## Safety posture

This phase does not add a live runner.

The console is read-only. It does not create audit attempts, change balances, write value-moving ledger rows, alter holder positions, process resale, process redemption, or launch markets.

## Future use

Phase 13 can add operator signoff collection on top of this console. That should still be non-domain-mutating and should only update the Phase 11 signoff/evidence tables.
