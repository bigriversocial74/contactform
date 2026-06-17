# Stage 8B — Entitlements, Customer Library, and Protected Downloads

## Purpose

Stage 8B adds the missing entitlement and protected-access layer around the existing product assets, paid commerce orders, PPPM issued items, customer account library views, and Stage 7 refund/dispute policy inputs.

This build preserves the established relationship:

`PPPM Item -> Entitlement -> Product Asset -> Authorized Access / Download Event`

It does not create another purchase, payment, receipt, owned-item, product asset, or wallet system.

## Delivered backend contracts

### Entitlements

Adds a canonical `entitlements` table that links:

- PPPM item
- commerce order item
- product version
- product asset
- entitled user
- merchant user
- source type/reference
- idempotency key
- access status

The entitlement is the access right. The PPPM item remains the permanent issued unit.

### Events and history

Adds append-only records for:

- entitlement lifecycle events
- authorized and denied access events
- delivery grant consumption
- entitlement review items

### Protected delivery

Adds a two-step access model:

1. `POST /api/library/access.php` checks the authenticated user's active entitlement and creates a short-lived delivery grant.
2. `GET /api/library/download.php` validates the delivery grant and token, records access, and returns safe delivery metadata.

Raw storage keys are not returned to clients.

### Customer library

Existing account item scopes remain intact:

- owned
- purchased
- sent
- received
- redeemed

The account item read model is extended with entitlement counts and active entitlement counts rather than replacing the PPPM-based library.

### Paid-order integration

Paid-order PPPM issuance now grants entitlements for eligible product-version assets with protected asset roles, such as download, audio, video, and document.

Grant creation is idempotent by PPPM item and asset identity.

### Refund policy

Successful full refunds revoke active entitlements for the order.

Successful partial refunds create entitlement review items instead of guessing which access should be revoked when the affected unit cannot be determined.

### Administrative lifecycle

Adds permission-scoped administrative entitlement operations for:

- suspend
- revoke
- restore
- inspect

All writes require CSRF protection and create entitlement events and audit records.

## Schema added

- `entitlements`
- `entitlement_events`
- `entitlement_access_events`
- `asset_delivery_grants`
- `entitlement_review_items`

## APIs added

- `GET /api/library/entitlements.php`
- `POST /api/library/access.php`
- `GET /api/library/download.php`
- `GET|POST /api/admin/entitlements.php`

## Services added

- `api/entitlements/_entitlements.php`

The service owns grant creation, access authorization, delivery grant creation, access-event recording, refund policy application, and review item creation.

## Security controls

- authenticated customer access
- CSRF protection for access-grant and admin writes
- server-side entitlement resolution
- active status checks
- expiration checks
- ready asset checks
- no raw storage path exposure
- short-lived delivery grants
- hashed delivery tokens
- append-only access events
- permission-scoped admin lifecycle transitions

## Deferred

- real storage-provider signed URL generation
- streaming proxy delivery
- transfer/claim entitlement migration
- dispute webhook integration
- asset removal replacement/refund workflow
- per-product download limits
- customer UI refinement for downloadable assets
- merchant entitlement analytics

Stage 8B establishes the backend entitlement and access-control foundation needed for those later improvements.
