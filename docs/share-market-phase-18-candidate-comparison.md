# Buy-In Phase 18 Candidate-to-Candidate Comparison

Phase 18 adds a read-only comparison view for two saved Buy-In evidence candidates.

## Scope

This phase adds:

- candidate comparison service
- candidate comparison API
- print-friendly comparison page
- comparison JSON download
- package hash comparison
- readiness score comparison
- blocker delta comparison
- signoff record comparison
- legal evidence comparison
- rollback evidence comparison
- reservation comparison
- snapshot hash comparison
- gate and simulator hash comparison

## API

`GET /api/admin/share-market/evidence-candidate-comparison.php?attempt_id=<attempt-id>&left_candidate_id=<candidate-id>&right_candidate_id=<candidate-id>`

The API requires Share Market Admin permission and returns one comparison packet.

## Page

`/account-share-market-candidate-comparison.php?attempt_id=<attempt-id>&left_candidate_id=<candidate-id>&right_candidate_id=<candidate-id>`

The page loads the comparison API, shows summary cards, renders difference rows, and supports print/download.

## Audit console integration

When at least two candidates exist for an audit attempt, the candidate registry area includes a `Compare newest` link.

## Safety posture

This phase is read-only. It renders and exports comparison data only.

## Future use

Phase 19 can add a final reviewer checklist that records reviewer acknowledgement of a selected candidate without executing market actions.
