# Stage 9C — Claim, Redemption, Ownership, and Lifecycle Integration

## Purpose

Stage 9C completes the transactional lifecycle around the Stage 9B Microgift template, version, instance, and credential foundation.

Canonical relationship remains:

`Microgift Template -> Template Version -> Gift Instance -> Claim/Redemption -> PPPM Item -> Entitlement`

## Delivered

### Credential verification

- normalized human-safe credential input
- prefix and last-four candidate lookup
- constant-time HMAC hash comparison
- failed-attempt increments
- automatic credential lockout
- credential expiration
- credential consumption after successful claim
- administrator credential rotation

Raw credentials remain absent from database records, logs, events, audits, and compatibility reports.

### Canonical claim completion

Claim processing is transaction locked and idempotent. It:

- verifies instance and credential eligibility
- enforces named-recipient assignment
- records one canonical claim
- updates instance ownership and claim timestamps
- consumes the credential
- synchronizes Stage 8 entitlement ownership when a PPPM item is linked
- records append-only Microgift events

PPPM remains the permanent unit identity. Entitlements remain the protected-access authority.

### Redemption

Redemption is transaction locked and idempotent. It verifies:

- authenticated owner
- redeemable instance status
- expiration
- merchant context
- location allow-list/exclusion policy
- unique redemption idempotency key

Successful redemption creates an immutable redemption record and transitions the Microgift and linked PPPM item to redeemed state.

### Lifecycle policy

Stage 9C supports:

- cancellation
- revocation
- expiration
- replacement
- full-refund revocation
- dispute-open policy
- merchant-win policy
- customer-win policy

Every lifecycle request requires a source reference and idempotency key. Historical records are preserved.

### Replacement

Replacement creates a new linked instance from the existing immutable snapshot, invalidates prior active credentials, marks the prior instance replaced, and creates a new credential without deleting history.

### Compatibility

Existing `gifts` and `gift_claims` remain active compatibility surfaces. The Stage 9C compatibility report identifies legacy gifts that have or have not been mapped to canonical Microgift instances. No automatic destructive backfill is performed.

## Schema added

- `microgift_claims`
- `microgift_redemptions`
- `microgift_lifecycle_actions`

## APIs added

- `POST /api/microgifts/claim.php`
- `POST /api/microgifts/redeem.php`
- `POST /api/microgifts/payment-policy.php`
- `POST /api/admin/microgift-lifecycle.php`
- `POST /api/admin/microgift-replace.php`

## Security controls

- authentication on customer claim and redemption
- permission-scoped administrator lifecycle actions
- CSRF on all writes
- row locks for credentials, instances, claims, and redemption
- constant-time credential comparison
- generic claim/redemption failures
- attempt counters and lockout
- idempotency for claims, redemption, lifecycle actions, and replacement
- immutable lifecycle and event history
- server-side location and ownership checks

## Validation

Stage 9C adds migration, smoke, compatibility-report, and PHPUnit contracts. The consolidated Stage 9B smoke step now applies and validates the Stage 9C schema, so no new GitHub Actions workflow is added.

## Deferred to Stage 9D

- polished customer account read models
- merchant claim and redemption dashboards
- administrative review UI
- full legacy gift backfill after compatibility review
- Future Demand event completeness review
- duplicate-path deletion
- Stage 9 closeout and Stage 10 handoff
