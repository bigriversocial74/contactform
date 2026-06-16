# Stage 10B — Merchant Location Claim Authority and Attempt Ledger

Stage 10B extends the existing Stage 9 Microgift lifecycle. It does not create a parallel claim or redemption engine.

## Delivered

- `merchant_location_staff` for explicit location-scoped staff and manager assignments.
- `microgift_claim_attempts` as an append-only success and failure ledger.
- Canonical `location_id` and `merchant_claim_code_id` references on Microgift claims and redemptions.
- A reusable authority service that locks the location and code records, verifies merchant ownership, active status, actor authority, validity windows, usage limits, and the stored SHA-256 value using `hash_equals()`.
- A separate usage-increment hook so verification cannot consume a code before an approved transaction.

## Authority order

1. Lock and load the canonical location.
2. Confirm that it belongs to the merchant.
3. Confirm that the location is active.
4. Confirm that the actor is the owner, assigned location staff, or an administrator.
5. Lock the active claim-code candidate.
6. Verify the submitted value against the stored hash.
7. Validate start time, end time, and usage limit.
8. Return canonical merchant, location, code, and actor context.

## Attempt results

`approved`, `invalid_gift`, `gift_not_paid`, `invalid_state`, `gift_expired`, `already_claimed`, `merchant_mismatch`, `invalid_location`, `location_not_allowed`, `invalid_claim_code`, `unauthorized_claim_actor`, `rate_limited`, and `internal_error`.

## Security contract

Sensitive submitted values are never written to the attempt table. Request, network, and client details are represented only by optional fingerprints. Attempt records are insert-only.

## Stage 10C handoff

Stage 10C will call these contracts inside the final transaction that coordinates lifecycle validation, approval, Microgift and PPPM redemption, code usage, inbox movement, and events.
