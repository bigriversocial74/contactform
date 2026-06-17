# Adapted Stage 10 Implementation Plan

## Architecture decision

Stage 10 will extend the canonical Stage 9 Microgift lifecycle. It will not create parallel gift, ownership, entitlement, or redemption systems.

## Stage 10B — Merchant Location Claim Authority and Attempt Ledger

### Schema changes proposed

Add a Stage 10 migration containing:

- `microgift_claim_attempts`
  - immutable record for every attempt,
  - instance, merchant, location, actor, result, reason code, idempotency/correlation, risk metadata, timestamps.
- canonical location and claim-code references on `microgift_redemptions`.
- optional canonical location and claim-code references on `microgift_claims` only where the official approved-claim compatibility model requires them.
- merchant/location staff assignment indexes or missing authorization fields only if the existing Stage 3 model is insufficient.
- post-claim `can_tip` read-model/flag only; no financial implementation.

### Service boundaries

Add a location claim authority service responsible for:

1. locking and loading the location,
2. proving merchant ownership,
3. proving location active status,
4. proving actor authorization,
5. selecting an active claim-code record,
6. verifying the submitted code with constant-time hash comparison,
7. validating valid-from, valid-until, usage limit, and status,
8. incrementing usage only after approved redemption,
9. returning canonical merchant, location, code, and actor context.

### Attempt result catalog

At minimum:

- `approved`
- `invalid_gift`
- `gift_not_paid`
- `invalid_state`
- `gift_expired`
- `already_claimed`
- `merchant_mismatch`
- `invalid_location`
- `location_not_allowed`
- `invalid_claim_code`
- `unauthorized_claim_actor`
- `rate_limited`
- `internal_error`

## Stage 10C — Atomic Claim/Redemption and Inbox Integration

### Canonical transaction order

1. Begin transaction.
2. Resolve and lock Microgift instance.
3. Create or reserve claim-attempt record.
4. Validate paid source and lifecycle state.
5. Resolve canonical merchant/location.
6. Validate actor and location claim code.
7. Validate Microgift location policy.
8. Create approved claim/redemption context.
9. Call `mg_pppm_redeem()`.
10. Update Microgift state and timestamps.
11. Increment claim-code usage.
12. Move/create inbox read-model state as Claimed/Redeemed.
13. Append Microgift, merchant-location, compatibility, and audit events.
14. Commit.
15. Queue notifications and analytics through retryable outbox processing.

Failed attempts must be recorded without mutating Microgift, PPPM, entitlement, inbox, or claim-code usage state.

## Stage 10D — API and Operational Surface

### Proposed APIs

- `GET /api/gifts/redeem.php?token=...`
- `POST /api/gifts/claim.php`
- `GET /api/gifts/claim-status.php?id=...`
- `GET /api/merchant/claims.php`
- `GET /api/merchant/claim-detail.php?id=...`
- `GET /api/merchant/location-claims.php?location_id=...`

Project conventions may use PHP query endpoints instead of path parameters, but request/response semantics must match the official contract.

### Security

- Authentication and permission checks.
- CSRF on writes.
- Idempotency on claim approval.
- Constant-time code verification.
- Generic external credential failures.
- No code/hash in responses, logs, events, metadata, or analytics.
- Object and location scoping on all merchant reads.
- Rate limits and review escalation.

## Stage 10E — Events and compatibility

Register canonical events:

- `gift.claim_attempted`
- `gift.claim_failed`
- `gift.claimed`
- `claim.approved`
- `merchant_location.redemption_completed`
- `inbox.item_moved_to_claimed`
- `psr.redeemed_pending`

Existing canonical events remain:

- `microgift.redemption_completed`
- `pppm.redeemed`

Compatibility events must not create a second state source.

## Stage 10F — Behavioral tests and closeout

Required database-backed tests:

- valid allowed-location claim,
- invalid code,
- wrong merchant,
- inactive location,
- excluded location,
- expired/revoked/refunded gift,
- unauthorized staff,
- assigned location manager,
- duplicate idempotency request,
- concurrent submissions,
- claim-code usage limit,
- inbox side effects,
- PPPM/Microgift synchronization,
- failed-attempt immutability,
- plaintext credential scan,
- merchant location history scoping.

## Definition of done

Stage 10 is complete only when a valid authorized location claim succeeds atomically, every failure is safely logged, canonical source-of-truth systems remain synchronized, merchant/location history is available, inbox state updates, and all Stage 1–9 regressions remain green.
