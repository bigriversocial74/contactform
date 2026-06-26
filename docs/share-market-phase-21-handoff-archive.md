# Buy-In Phase 21 Handoff Archive Record

Phase 21 adds archive records for reviewed Buy-In preflight handoff checklists.

## Scope

This phase adds:

- handoff archive schema
- handoff archive service
- handoff archive API
- audit console archive panel
- reviewed handoff checklist hash
- reviewer note
- handoff readiness state
- acknowledged candidate/package hash
- current evidence package hash at archive time
- archive history
- archived-vs-current drift status

## Schema

`database/stage_24_buy_in_handoff_archives.sql`

New table:

- `share_market_handoff_archives`

The migration is additive and registered in `config/migrations.php`.

## API

`GET /api/admin/share-market/handoff-archives.php?attempt_id=<attempt-id>`

Loads archive history for an audit attempt.

`POST /api/admin/share-market/handoff-archives.php`

Archives the current handoff checklist with a reviewer note.

Write requests require:

- Share Market Admin permission
- CSRF validation
- rate limiting
- an existing audit attempt

## Console integration

The audit review console now injects a Handoff Archive section into the audit detail modal.

The panel supports:

- reviewer note
- archive current handoff
- archive history
- archived-vs-current drift status

## Safety posture

This phase stores archive records only.

## Future use

Phase 22 can add an operations handoff packet that combines the latest archive, current drift, and checklist history into one printable reviewer artifact.
