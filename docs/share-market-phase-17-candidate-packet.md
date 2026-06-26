# Buy-In Phase 17 Candidate Review Packet

Phase 17 adds a read-only review packet for saved Buy-In evidence candidates.

## Scope

This phase adds:

- candidate packet service
- candidate packet API
- print-friendly packet page
- packet JSON download
- candidate-vs-current evidence comparison
- package hash verification
- readiness snapshot
- signoff, legal, rollback, reservation, and snapshot summaries

## API

`GET /api/admin/share-market/evidence-candidate-packet.php?attempt_id=<attempt-id>&candidate_id=<candidate-id>`

The API requires Share Market Admin permission and returns one saved candidate packet.

## Page

`/account-share-market-candidate-packet.php?attempt_id=<attempt-id>&candidate_id=<candidate-id>`

The page loads the packet API, shows summary cards, renders readiness checks, shows evidence summary counts, and supports print/download.

## Audit console integration

The candidate registry list now includes a `Packet` link for each saved candidate.

## Safety posture

This phase is read-only. It renders and exports review data only.

## Future use

Phase 18 can add candidate-to-candidate comparison for reviewer history.
