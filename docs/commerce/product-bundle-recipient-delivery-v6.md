# Product Bundle Recipient Delivery v6

Phase 6 adds buyer-controlled recipient delivery for issued Product Bundle components.

## Scope

- Recipient email delivery for issued Microgift components.
- Send and resend controls on the parent bundle order page.
- Maximum of three delivery attempts per component per hour.
- Delivery attempt history derived from canonical audit logs.
- Buyer ownership and CSRF enforcement.
- Safe handling of unexpected runtime errors.
- Existing Microgift inbox remains the lifecycle and claim authority.

## Runtime routes

- `GET /api/bundles/delivery.php?action=status&order_id={bundle_order_public_id}`
- `POST /api/bundles/delivery.php` with `action=send` and `component_id`
- `/bundle-order.php?id={bundle_order_public_id}`

## Delivery rules

A delivery can be sent only when:

1. The authenticated user owns the parent bundle order.
2. The component has a linked Microgift instance.
3. The Microgift is not already claimed, redeemed, refunded, or regifted.
4. The order has a valid recipient email.
5. The component has fewer than three attempts in the previous hour.

## Persistence

No new tables are introduced. Delivery attempts use the existing `audit_logs` authority with action `bundle.component.delivery`.

## Deferred

- SMS delivery.
- Push notifications.
- Provider webhook delivery receipts.
- Stripe transfers and merchant settlement release.
- Refund transfer reversals.
