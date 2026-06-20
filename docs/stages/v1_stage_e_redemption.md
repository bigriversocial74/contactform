# V1 Stage E — Merchant Redemption

Stage E closes the focused V1 Microgift lifecycle with one canonical merchant-location redemption path.

## Completed flow

1. The merchant configures an active location and a hash-only location claim code.
2. An authorized merchant or assigned location staff member looks up the Microgift by its permanent ID.
3. The canonical service verifies paid or authorized issuance, current ownership, available lifecycle state, merchant identity, active location, location policy, staff authority, and the hashed claim code.
4. One transaction records the immutable attempt, completed redemption, PPPM redemption, claim-code usage, Microgift redeemed state, Action Center projections, audit events, operational outbox event, and customer and merchant confirmations.
5. Replaying the same idempotency key returns the existing redemption without duplicating attempts, code usage, notifications, or lifecycle changes.
6. Invalid claim-code attempts are recorded without changing Microgift, PPPM, or Action Center state.

## Canonical routes

- Merchant workspace: `/merchant-claims.php`
- Safe lookup: `/api/merchant/microgift-claim-lookup.php`
- Atomic redemption: `/api/merchant/microgift-claim.php`
- Location management: `/api/merchant/locations.php`
- Claim-code management: `/api/merchant/claim-codes.php`

Customer-side redemption is retired. Customers present the Microgift to an authorized merchant location; the merchant completes the redemption using its private location claim code.
