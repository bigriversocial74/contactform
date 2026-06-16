# Stage 10A — Official Plan Review and Existing Claim-System Reconciliation

## Purpose

Stage 10A is a documentation and architecture reconciliation pass. It compares the official Stage 10 Merchant Claim / Redeem plan against the code now deployed through Stage 9E. It does not add schema, APIs, or UI behavior.

## Official Stage 10 objective

Stage 10 must allow a paid, received, claimable Microgift to be redeemed at an authorized merchant location using a location-owned claim code. Every successful and failed attempt must be recorded. A successful claim must be transactional, idempotent, concurrency-safe, and must update gift, inbox, event, merchant-location, PPPM, and entitlement state without storing plaintext credentials.

## Locked scope retained from the official plan

- Redeem voucher lookup and claim-session preparation.
- Merchant/location claim verification.
- Location claim-code validation against stored hashes.
- Claim-attempt logging for success and failure.
- Approved immutable claim/redemption records.
- Claimable/received to claimed/redeemed state transition.
- Inbox movement to Claimed.
- Merchant and location claim history.
- Audit, security, and fraud signals.
- Post-claim tip availability flag only.
- Future Demand/PSR redeemed source event only; no scoring dashboard.

## Explicit do-not-build boundaries

Stage 10 must not build tip payments, subscriptions, feed/comments, predictive Future Demand dashboards, agent commerce execution, unrelated UI redesigns, new wallet logic, or an alternative ownership system.

## Existing implementation built ahead of Stage 10

### Complete or substantially complete

- Hash-only Microgift credentials with prefix/last-four lookup.
- Credential expiration, failed-attempt counters, and lockout.
- Row locking of `microgift_instances` during claim/redeem workflows.
- Claim and redemption idempotency keys.
- `microgift_claims`, `microgift_redemptions`, and lifecycle action tables.
- Canonical PPPM ownership transfer through `mg_pppm_transfer_owner_canonical()`.
- Canonical PPPM redemption through `mg_pppm_redeem()`.
- Entitlement transfer synchronization under the PPPM ownership path.
- Microgift lifecycle events and admin inspection timeline.
- Customer, merchant, and administrator operational read surfaces.
- API/event contract baselines and CI contract enforcement.

### Partial

- Location policy validation exists, but it currently compares a free-form `location_reference` against JSON policy values.
- Merchant identity is accepted by redemption, but merchant/location ownership is not resolved through canonical location records.
- Microgift credential failures are logged as lifecycle events, but there is no complete immutable claim-attempt ledger for every merchant-location attempt.
- Merchant operations expose redemption records, but not the official location-specific claim history API contract.
- Existing `gift_claims` has Stage 3 location and merchant claim-code columns, while canonical Stage 9 Microgift claims/redemptions do not yet carry canonical location and claim-code foreign keys.

### Missing or misaligned

- No canonical validation of `merchant_claim_codes.code_hash` during Microgift redemption.
- No required relationship proving the submitted claim code belongs to the selected active location and merchant.
- No dedicated immutable Microgift claim-attempt table covering both failures and successes.
- No normalized Stage 10 failure reason catalog.
- No merchant staff/location assignment authorization inside the canonical redemption transaction.
- No atomic inbox movement from Received/Open to Claimed.
- No `gift.claim_attempted`, `gift.claim_failed`, `gift.claimed`, or `psr.redeemed_pending` compatibility events.
- No canonical merchant/location claim-history endpoints matching the official Stage 10 contract.
- `microgift_redemptions.location_reference` is a string rather than a canonical `merchant_locations.id` relationship.
- No tip-action availability placeholder tied to an approved claim.

## Source-of-truth rules for Stage 10

- `microgift_instances` remains the Microgift contract and lifecycle record.
- `merchant_locations` remains the canonical merchant-location record.
- `merchant_claim_codes` remains the canonical location claim authority.
- `pppm_items` remains the canonical issued-unit ownership and redeemed-state source.
- `entitlements` remains the digital-access source.
- Stage 7 ledger remains the money source; Stage 10 does not move funds.
- Inbox records are side effects/read models, not claim authority.
- Events and metrics are source facts, not predictive scores.

## Recommended Stage 10 implementation sequence

1. **Stage 10B — Merchant Location Claim Authority and Attempt Ledger**
   - Add canonical location and claim-code references.
   - Add immutable attempt records and failure reason catalog.
   - Add location/staff authorization and hash verification service.

2. **Stage 10C — Atomic Claim/Redemption and Inbox Integration**
   - Orchestrate location validation, approved claim, Microgift status, PPPM redemption, inbox movement, audit, and events in one transaction.

3. **Stage 10D — Merchant Claim APIs, Fraud Controls, and Operational Reads**
   - Add redeem lookup, claim submission, claim status, merchant claim list/detail, and location claim history.
   - Add velocity limits, lockouts, review escalation, and safe error responses.

4. **Stage 10E — Behavioral Tests, Security Hardening, and Closeout**
   - Add database-backed valid, invalid, duplicate, concurrent, inactive-location, wrong-merchant, unauthorized-staff, expired, refunded, and already-claimed tests.

## Stage 10A decision

Do not create a second claim engine. Extend the canonical Stage 9 Microgift lifecycle so merchant-location authority is added beneath the existing PPPM, entitlement, and Microgift contracts.
