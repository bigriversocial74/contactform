# Buy-In Phase 13 Operator Signoff and Evidence Collection

Phase 13 adds controlled collection forms for the Phase 11 audit/evidence tables.

## Scope

This phase adds:

- operator signoff decisions
- signoff rejection and revocation
- legal evidence notes and references
- rollback evidence notes and references
- audit-console forms for recording the above

## Page integration

The forms are added to:

`/account-share-market-execution-audit.php`

When an audit attempt detail is open, administrators can record:

- engineering signoff
- security signoff
- legal signoff
- operations signoff
- database backup confirmation
- product owner signoff
- legal evidence
- rollback evidence

## APIs

- `POST /api/admin/share-market/execution-signoff.php`
- `POST /api/admin/share-market/execution-legal-evidence.php`
- `POST /api/admin/share-market/execution-rollback-evidence.php`

All write APIs require:

- Share Market Admin permission
- CSRF validation
- rate limiting
- existing audit attempt public ID

## Data written

This phase writes only to Phase 11 audit/evidence tables:

- `share_market_execution_operator_signoffs`
- `share_market_legal_release_evidence`
- `share_market_execution_rollback_evidence`

## Safety posture

This phase does not add a runner and does not alter Buy-In balances or market state.

It does not write to value-moving ledger tables, pool balances, treasury balances, holder positions, resale records, redemption records, or market launch state.

Each service response includes:

- `domain_mutations_performed: false`

## Future use

Phase 14 can add signoff completeness checks and a readiness dashboard. That next phase should still remain non-domain-mutating and should only evaluate whether the evidence package is complete.
