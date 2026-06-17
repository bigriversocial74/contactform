# Stage 8B Acceptance Checklist

## Entitlement foundation

- [x] Canonical entitlement table exists.
- [x] Entitlements link PPPM items, product assets, product versions, order items, entitled users, and merchants.
- [x] Entitlement creation is idempotent.
- [x] PPPM item remains the owned-unit source.
- [x] Product asset catalog remains the asset source.
- [x] No duplicate purchase or owned-item system is introduced.

## Purchase and PPPM integration

- [x] Paid-order PPPM issuance grants entitlements for eligible assets.
- [x] Repeated fulfillment calls reuse existing entitlement grants.
- [x] Entitlement grants are based on issued PPPM items, not payment IDs.

## Protected access

- [x] Customer access requires authentication.
- [x] Protected access requires an active entitlement.
- [x] Access checks entitlement, asset, user, start time, and expiration.
- [x] Denied access is recorded.
- [x] Authorized access is recorded.
- [x] Delivery grants are short-lived.
- [x] Delivery tokens are stored as hashes.
- [x] Raw storage paths are not returned to clients.

## Customer library

- [x] Existing PPPM account item scopes remain intact.
- [x] Account item responses include entitlement counts.
- [x] Dedicated library entitlement API exists.

## Refund and review policy

- [x] Full refunds revoke active entitlements.
- [x] Partial refunds create review items.
- [x] Review items preserve context for later admin resolution.

## Administration

- [x] Admin entitlement list exists.
- [x] Suspend, revoke, and restore operations exist.
- [x] Admin writes require dedicated permission.
- [x] Admin writes require CSRF protection.
- [x] Entitlement lifecycle events are recorded.
- [x] Audit records are created for admin changes.

## Validation

- [x] Migration runner exists.
- [x] Smoke checks exist.
- [x] PHPUnit contract coverage exists.
- [x] PR Validation includes Stage 8B migration and smoke checks.
- [ ] PR Validation passes.
- [ ] Main Regression passes after merge.
