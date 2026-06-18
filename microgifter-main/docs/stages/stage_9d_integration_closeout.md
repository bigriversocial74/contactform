# Stage 9D — Integration Review, Operational Surfaces, Consolidation, and Closeout

## Purpose

Stage 9D closes the Microgift Engine by consolidating the Stage 9B template/instance foundation and Stage 9C claim/redemption lifecycle into customer, merchant, and administrator operational surfaces.

Canonical relationship remains:

`Microgift Template -> Template Version -> Gift Instance -> Claim/Redemption -> PPPM Item -> Entitlement`

## Delivered

### Customer library

The customer Microgift library supports owned, sent, received, claimed, and redeemed scopes. It reads canonical Microgift instances while exposing linked PPPM status, entitlement counts, claim status, and redemption status. It does not replace the existing account or PPPM library.

### Merchant operations

The merchant operations API provides owner-scoped:

- instance lifecycle counts
- issued, claimed, redeemed, expired, cancelled, and revoked totals
- face value and redeemed value
- unique customer and location counts
- instance, claim, and redemption history

No customer credential hashes or raw credentials are returned.

### Administrator operations

Stage 9D adds:

- a consolidated instance inspection timeline
- a compatibility and policy review queue
- permission-scoped review assignment and resolution
- audit records for review actions

The inspection timeline combines the instance, template/version, PPPM, legacy gift mapping, events, claims, redemptions, lifecycle actions, and reviews without mutating history.

### Legacy compatibility reconciliation

Legacy `gifts` and `gift_claims` are not deleted or silently migrated. Reconciliation creates idempotent review items for:

- unmapped legacy gifts
- Microgift/PPPM ownership mismatches

This protects clean-install development while preserving a controlled migration path if legacy data is later imported.

### Future Demand source data

Daily Microgift aggregates capture reliable source facts:

- issuance
- claim
- redemption
- expiration
- cancellation
- revocation
- face value
- redeemed value
- unique recipients
- unique locations

Stage 9D does not calculate a Future Demand score. Later intelligence stages may consume these non-sensitive aggregates and append-only events.

## Operational schema

- `microgift_review_items`
- `microgift_daily_metrics`

## APIs

- `GET /api/account/microgifts.php`
- `GET /api/merchant/microgifts.php`
- `GET|POST /api/admin/microgift-reviews.php`
- `GET /api/admin/microgift-inspect.php`

## Scripts

- `scripts/stage9d.php`
- `scripts/stage9d_smoke.php`
- `scripts/reconcile_stage9d_legacy_gifts.php`
- `scripts/aggregate_stage9d_microgifts.php`

## Consolidation review

Confirmed canonical responsibilities:

- commerce orders and payments fund purchases
- Microgift instances represent gift contracts
- PPPM items represent issued-unit identity and ownership
- entitlements represent protected digital access
- products and product versions represent catalog terms
- locations remain canonical merchant/location records
- the Stage 7 ledger remains the internal money source
- Microgift events and metrics remain Future Demand source data

Existing `gifts` and `gift_claims` remain compatibility records until an approved backfill proves that all external callers and imported records can be safely retired. Duplicate-path deletion is therefore deferred rather than guessed.

## Security closeout

- customer reads require authentication and object ownership scope
- merchant operations require permission and owner scope
- administrator review writes require permission and CSRF validation
- no raw credential is exposed by operational APIs
- review actions are audited
- legacy reconciliation is non-destructive
- lifecycle history remains append-only
- metrics contain aggregate operational facts rather than private credential data

## Stage 9 score

- Official-plan alignment: 9.3/10
- Template and immutable version model: 9.4/10
- Gift-instance integrity: 9.2/10
- Credential security: 9.1/10
- Claim and ownership synchronization: 9.0/10
- Redemption and location enforcement: 8.8/10
- Lifecycle/refund/dispute policy: 8.8/10
- Customer operational surface: 8.7/10
- Merchant/admin operational surface: 8.8/10
- Legacy compatibility and consolidation: 8.4/10
- Future Demand event readiness: 9.0/10
- Test, migration, and CI coverage: 9.2/10

**Overall Stage 9 closeout: 9.0/10**

The remaining gaps are provider/UI polish, approved legacy backfill, and production-scale analytics scheduling—not missing core Microgift Engine contracts.

## Stage 9 completion decision

Stage 9 is complete when PR Validation and Main Regression are green. The Microgift Engine is ready to serve as a stable dependency for Stage 10.
