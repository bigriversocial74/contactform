# Buy-In Phase 11 Execution Audit Scaffolding

Phase 11 adds storage and service scaffolding for a future Buy-In runner.

This phase does not add live execution. It records preflight/audit evidence only.

## Added storage

- `share_market_execution_attempts`
- `share_market_execution_preflight_snapshots`
- `share_market_execution_operator_signoffs`
- `share_market_legal_release_evidence`
- `share_market_execution_rollback_evidence`
- `share_market_idempotency_reservations`

## What the scaffolding records

- execution attempt metadata
- release-gate output
- ledger simulator output
- target snapshot hash
- approval request snapshot
- operator signoff placeholders
- legal evidence placeholders
- rollback evidence placeholders
- idempotency reservation records

## API

`POST /api/admin/share-market/execution-audit-scaffold.php`

Required controls:

- Share Market Admin permission
- CSRF
- rate limiting
- fresh password verification
- typed confirmation phrase: `AUDIT SCAFFOLD`

## Non-mutating contract

The service returns:

- `mutations_performed: false`
- `domain_mutations_performed: false`

The scaffolding does not:

- insert value-moving credit ledger rows
- change pool balances
- change treasury balances
- change holder positions
- launch a market
- execute resale
- execute redemption

## Idempotency reservation behavior

The scaffolding records a reserved idempotency key for the approved request and preflight bundle.

This reservation is evidence only. It does not mark the request executed and does not create a value-moving ledger entry.

If the same idempotency key already has a reservation, the service returns the existing attempt rather than creating a duplicate.

## Future use

A later runner implementation can use these records as the audit baseline before any future transaction is implemented.

That future implementation must still pass the Phase 8 release gate, Phase 9 reconciliation, and Phase 10 operator/legal requirements.
