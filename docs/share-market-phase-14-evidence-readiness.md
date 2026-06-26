# Buy-In Phase 14 Evidence Package Completeness & Readiness Dashboard

Phase 14 adds computed readiness checks for Buy-In audit attempts.

## Scope

This phase adds a read-only checklist and score for the evidence package attached to an audit attempt.

It evaluates:

- required operator signoffs
- approved legal evidence
- rollback evidence
- simulator reconciliation status
- release-gate visibility
- idempotency reservation presence
- open blockers
- readiness score

## API

`GET /api/admin/share-market/evidence-readiness.php?attempt_id=<public-id>`

The API requires Share Market Admin permission and returns:

- readiness version
- complete/incomplete state
- score
- checks
- blockers
- summary counts
- `domain_mutations_performed: false`

## Console integration

The audit review console now shows a readiness checklist inside the audit detail modal.

The checklist refreshes when an audit detail is opened and after signoff/evidence forms are saved.

## Required signoffs

The checklist requires approved records for:

- engineering
- security
- legal
- operations
- database backup
- product owner

## Safety posture

This phase does not add a runner.

It does not change balances, write value-moving ledger rows, alter holder positions, process resale, process redemption, or launch markets.

The dashboard only reads existing audit/evidence records and computes checklist state.

## Future use

Phase 15 can add a release-candidate report/export that packages the readiness output, snapshots, signoffs, evidence, and rollback notes into a review bundle.
