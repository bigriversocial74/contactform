# Buy-In Phase 19 Final Reviewer Acknowledgement

Phase 19 adds a final reviewer acknowledgement layer for saved Buy-In evidence candidates.

## Scope

This phase adds:

- final reviewer acknowledgement schema
- acknowledgement service
- acknowledgement API
- audit console acknowledgement panel
- reviewer role
- reviewer note
- acknowledgement timestamp
- selected candidate package hash
- acknowledgement history
- current-vs-acknowledged package hash drift

## Schema

`database/stage_23_buy_in_final_reviewer_acknowledgements.sql`

New table:

- `share_market_evidence_acknowledgements`

The migration is additive and registered in `config/migrations.php`.

## API

`GET /api/admin/share-market/evidence-acknowledgements.php?attempt_id=<attempt-id>`

Loads acknowledgement history for an audit attempt.

`POST /api/admin/share-market/evidence-acknowledgements.php`

Records a reviewer acknowledgement for one saved evidence candidate.

Write requests require:

- Share Market Admin permission
- CSRF validation
- rate limiting
- an existing audit attempt
- an existing non-revoked evidence candidate

## Console integration

The audit review console now injects a Final Reviewer Acknowledgement section into the audit detail modal.

The panel supports:

- candidate selection
- reviewer role
- reviewer note
- acknowledgement history
- drift status against the current evidence export

## Safety posture

This phase stores review acknowledgement records only.

## Future use

Phase 20 can add a preflight handoff checklist that summarizes the acknowledged candidate, open drift, and remaining operational blockers for human review.
