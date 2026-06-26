# Buy-In Phase 15 Evidence Export Bundle

Phase 15 adds a read-only evidence export package for Buy-In audit attempts.

## Scope

This phase packages the existing audit/review data into one JSON artifact that an operator can download and archive.

The export includes:

- attempt summary
- approval request metadata
- readiness score and checklist
- blocker list
- signoff summary
- legal evidence summary
- rollback evidence summary
- idempotency reservation summary
- preflight snapshot hashes
- gate payload hash
- simulator payload hash
- export timestamp
- package hash

## API

`GET /api/admin/share-market/evidence-export.php?attempt_id=<public-id>`

The API requires Share Market Admin permission and returns:

- `export_version`
- `exported_at`
- `attempt`
- `approval_request`
- `readiness`
- evidence summaries
- `package_hash`
- `domain_mutations_performed: false`

## Console integration

The audit review console injects an Evidence Export panel inside the attempt detail modal.

The panel shows:

- package hash
- readiness score
- export timestamp
- compact package preview

The console also includes a `Download evidence JSON` button that downloads the returned package in the browser.

## Safety posture

This phase is read-only.

It does not add a runner, change balances, write value-moving ledger rows, alter holder positions, process resale, process redemption, or launch markets.

The export only reads existing audit/evidence data and computes a package hash.

## Future use

Phase 16 can add an immutable release-candidate registry that stores a selected package hash and reviewer note. That should still avoid any balance or market-state changes.
