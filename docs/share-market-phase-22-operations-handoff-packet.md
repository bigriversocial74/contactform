# Buy-In Phase 22 Operations Handoff Packet

Phase 22 adds a printable operations handoff packet for reviewed Buy-In handoff archives.

## Scope

This phase adds:

- operations handoff packet service
- read-only packet API
- printable packet page
- JSON packet download
- audit console packet link for archived handoffs
- packet summary for latest or selected archive
- archive hash and drift status
- final reviewer acknowledgement summary
- readiness score and blockers
- signoff status
- legal evidence status
- rollback evidence status

## SQL

No new SQL is added in Phase 22.

The packet uses the Stage 24 archive table:

- `share_market_handoff_archives`

## API

`GET /api/admin/share-market/operations-handoff-packet.php?attempt_id=<attempt-id>`

Loads the latest handoff archive packet for an audit attempt.

`GET /api/admin/share-market/operations-handoff-packet.php?attempt_id=<attempt-id>&archive_id=<archive-id>`

Loads a selected archived handoff packet.

## Page

`/account-share-market-operations-handoff.php?attempt_id=<attempt-id>&archive_id=<archive-id>`

The page supports:

- print packet
- download JSON
- archive summary
- checklist rows
- evidence summary
- full packet JSON

## Console integration

The audit review console loads a small companion script:

- `/assets/js/share-market-operations-handoff-links.js`

It adds Packet links to archived handoff rows.

## Safety posture

This phase is read-only. It renders archived review data and exports JSON only.

## Future use

Phase 23 can add a deployment-readiness checklist for non-value-moving operational tasks such as import verification, route availability, and permission checks.
