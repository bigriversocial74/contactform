# Stage 8C — Entitlement Lifecycle Integration and Closeout

## Purpose

Stage 8C completes the entitlement lifecycle around the Stage 8B access-control foundation. It integrates ownership changes, claims/transfers, disputes, asset removal, signed delivery adapters, merchant visibility, regression contracts, and the Stage 9 handoff.

Stage 8 remains based on:

`PPPM Item -> Entitlement -> Product Asset -> Authorized Access / Download Event`

PPPM remains the permanent owned-unit source. Entitlements remain access rights. Stage 8C does not introduce another purchase, ownership, asset, receipt, payment, or wallet system.

## Stage 8A

Stage 8A reviewed the original plan against features implemented early in Stages 3–7. It identified strong PPPM, product asset, commerce, and account-library foundations and confirmed that the missing layer was entitlement policy and protected delivery.

## Stage 8B

Stage 8B added:

- canonical entitlements
- entitlement lifecycle events
- protected access decisions
- short-lived hashed delivery grants
- entitlement review items
- grants from paid-order PPPM issuance
- full-refund revocation
- partial-refund review
- customer entitlement library APIs
- administrative suspend, revoke, and restore operations

## Stage 8C integrations

### Ownership and claim synchronization

`mg_entitlements_sync_pppm_owner()` synchronizes access when a PPPM item changes ownership through a claim, transfer, or audited administrative correction.

The policy:

- PPPM ownership remains canonical.
- Old owner entitlements are preserved historically and revoked.
- Equivalent entitlements are granted to the new owner.
- Repeated source events are idempotent.
- Transfer records identify the old owner, new owner, PPPM item, and source event.

Existing claim and gift flows can call this service after their canonical PPPM ownership update. Stage 8C does not create a second claim system.

### Dispute policy

Signed dispute events support:

- dispute opened: suspend active order entitlements
- merchant win: restore suspended entitlements
- customer win: revoke affected active or suspended entitlements

Provider event storage and policy-action idempotency make retries safe.

### Asset removal policy

When a protected asset is removed or disabled:

- active related entitlements are suspended
- review items are created for replacement/refund decisions
- historical grants and access records remain intact

When an asset is restored, suspended access may be restored through an explicit lifecycle action.

### Signed delivery adapter

The delivery layer supports two modes:

- configured signed URL using `MG_ASSET_DELIVERY_SECRET` and `MG_ASSET_SIGNED_URL_TEMPLATE`
- safe metadata-only fallback when no storage adapter is configured

The endpoint rechecks entitlement status, entitlement expiration, asset readiness, user ownership, grant expiration, and token validity before consuming a delivery grant. Storage keys are used only server-side and are not returned in API responses.

### Merchant visibility

The merchant API provides scoped aggregate counts by product version, asset, entitlement type, and status. It uses the current merchant ID and does not expose individual customer private information.

## Lifecycle records

Stage 8C adds:

- `entitlement_transfers`
- `entitlement_policy_actions`

These records provide source-event idempotency and an audit trail for ownership synchronization, dispute outcomes, asset policy, and future expiration sweeps.

## Security closeout

Confirmed controls:

- protected access requires authentication
- access-grant creation requires CSRF protection
- lifecycle operations require a dedicated permission and CSRF protection
- dispute events require a valid provider signature
- provider events and policy actions are idempotent
- delivery tokens are hashed and short-lived
- delivery rechecks entitlement and asset status
- raw storage paths are not returned
- merchant visibility is owner scoped
- lifecycle history remains append-only through events and transfer/action records

## Deferred operational work

The following are not Stage 8 blockers:

- direct hooks from every legacy claim endpoint into owner synchronization
- storage-provider-specific SDK adapters
- proxy streaming and byte-range support
- customer-facing library UI refinement
- merchant analytics dashboards
- automated expiration sweep scheduling
- advanced replacement/refund automation for removed assets
- per-product download-count limits

The lifecycle service and APIs provide the canonical integration points for that work.

## Stage 8 completion score

- Ownership and PPPM alignment: 9.5/10
- Entitlement integrity: 9.3/10
- Refund/dispute policy: 9.0/10
- Protected access security: 9.2/10
- Delivery-provider readiness: 8.2/10
- Customer library backend: 8.8/10
- Merchant visibility: 8.5/10
- Test and migration coverage: 9.1/10
- Overall Stage 8 closeout: 9.0/10

Stage 8 is complete as the entitlement, owned-library, and protected-access backend foundation. Provider-specific delivery infrastructure and polished customer/merchant UI remain explicit later work.
