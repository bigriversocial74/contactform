# Buy-In Phase 20 Preflight Handoff Checklist

Phase 20 adds a read-only preflight handoff checklist for Buy-In audit attempts.

## Scope

This phase adds:

- handoff checklist service
- handoff checklist API
- audit console handoff panel
- acknowledged candidate summary
- acknowledged package hash
- current evidence package hash
- drift status
- readiness score
- remaining blockers
- required signoff status
- legal evidence status
- rollback evidence status
- final reviewer acknowledgement status
- compact handoff checklist JSON preview

## API

`GET /api/admin/share-market/preflight-handoff.php?attempt_id=<attempt-id>`

The API requires Share Market Admin permission and returns one handoff checklist.

## Console integration

The audit review console now injects a Preflight Handoff Checklist section into the audit detail modal.

The panel shows:

- handoff ready status
- readiness score
- drift status
- acknowledged/current package hashes
- missing signoff count
- pass/open checklist rows
- JSON preview for archive/review

## Safety posture

This phase is read-only. It renders and exports checklist data only.

## Future use

Phase 21 can add a handoff archive record that stores a reviewed checklist hash and reviewer note without changing Buy-In value state.
