# HomeServer Phase 6A dependency lock

This Microgifter provider implementation was reconciled against HomeServer draft PR #37 at head `c6fda846acdca7488dccc16d0d19083e11ec14da` on 2026-07-28.

The following client contract items were verified at that head:

- all eight `/api/homeserver/v1/` provider routes
- the `v1` contract header
- capability identifiers
- provider request envelope
- pairing request and response fields
- Ed25519 signed request canonicalization
- signed entitlement lease claims
- heartbeat response envelope
- credential rotation response
- update authorization response
- update result receipt fields
- replacement start and completion fields

PR #37 remains an active dependency. This lock must be refreshed and the contract tests reconciled against its final fixtures before either coordinated implementation is merged.
