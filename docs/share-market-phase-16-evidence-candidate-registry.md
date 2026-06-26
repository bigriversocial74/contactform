# Buy-In Phase 16 Evidence Candidate Registry

Phase 16 adds an evidence candidate registry for Buy-In audit attempts.

## Scope

This phase stores selected evidence export packages as review candidates.

A candidate stores:

- candidate public ID
- audit attempt link
- approval request link
- package hash
- package JSON
- reviewer note
- candidate status
- created timestamp
- superseded timestamp
- revoked timestamp

## Schema

`database/stage_22_buy_in_evidence_candidates.sql`

New table:

- `share_market_evidence_candidates`

The migration is additive and registered in `config/migrations.php`.

## API

`GET /api/admin/share-market/evidence-candidates.php?attempt_id=<public-id>`

Loads candidates for an audit attempt and compares each saved package hash against the current evidence export hash.

`POST /api/admin/share-market/evidence-candidates.php`

Actions:

- `record` — stores the current evidence export as the active candidate and supersedes any previous active candidate for the same attempt.
- `revoke` — marks a saved candidate as revoked.

Write actions require:

- Share Market Admin permission
- CSRF validation
- rate limiting
- an existing audit attempt public ID

## Console integration

The audit review console injects an Evidence Candidates section inside the audit detail modal.

The section supports:

- reviewer note
- save current package as candidate
- candidate list
- matching/drifted comparison against current evidence package
- revoke candidate

## Safety posture

This phase does not add a runner.

It does not change balances, write value-moving ledger rows, alter holder positions, process resale, process redemption, or launch markets.

It writes only to the evidence candidate registry table.

Service/API responses include:

- `domain_mutations_performed: false`

## Future use

Phase 17 can add a reviewer packet view that compares two candidates or generates a printable review page for the selected candidate.
