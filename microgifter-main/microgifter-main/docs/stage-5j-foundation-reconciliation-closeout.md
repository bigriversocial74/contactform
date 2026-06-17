# Stage 5J Foundation Reconciliation and Closeout

## Purpose

Stage 5J restores the original Stage 5 transaction contract beneath the later Stage 5A-5I merchant, payment, financial, and PPPM systems.

Canonical flow:

`Published Product -> Active Cart -> Checkout Draft -> Pending Commerce Order -> Payment Checkout Session -> Paid Order -> PPPM Issuance`

## Clean-install assumption

The application has not been deployed with production users or production data. Stage 5J is therefore implemented as a clean-install schema addition without legacy-data conversion, compatibility views, or production migration choreography.

## Added foundation resources

- `carts`
- `cart_items`
- `checkout_drafts`
- `order_fee_snapshots`
- `order_status_history`
- `receipts`
- `order_audit_events`

`commerce_orders` and `commerce_order_items` remain the canonical order tables. They are not duplicated or renamed.

## Customer API contract

- `GET /api/commerce/cart.php`
- `DELETE /api/commerce/cart.php`
- `POST /api/commerce/cart-items.php`
- `PATCH /api/commerce/cart-item.php`
- `DELETE /api/commerce/cart-item.php`
- `POST /api/commerce/checkout-draft.php`
- `GET /api/commerce/orders.php`
- `POST /api/commerce/orders.php`
- `GET /api/commerce/order.php?order_id=...`
- `GET /api/commerce/receipt.php?order_id=...`
- `POST /api/payments/order-checkout-session.php`

## Administrative API contract

- `GET /api/admin/commerce-orders.php`
- `GET /api/admin/commerce-order.php?order_id=...`

Both require the `super_admin` role.

## Invariants

1. Product status, product-version status, unit value, merchant, and currency are resolved server-side.
2. A cart contains one merchant and one currency.
3. Money is stored in integer cents.
4. A checkout draft freezes cart items and totals for 30 minutes.
5. A checkout draft is converted at most once.
6. Order creation is idempotent per buyer and idempotency key.
7. `commerce_orders` is the canonical immutable order header.
8. `commerce_order_items` stores immutable line snapshots.
9. Every order receives a fee snapshot, including zero-fee orders.
10. Every order receives a pending receipt at creation.
11. Payment capture finalizes the receipt and appends payment history.
12. Payment sessions are created from an existing unpaid order, not from client-submitted prices.
13. Payment and PPPM identifiers remain separate.
14. Paid orders continue through the existing PPPM issuance handler.

## Deliberate compatibility decision

The existing `POST /api/payments/checkout-session.php` endpoint remains available for the current UI. New code should use the Stage 5J flow and call `POST /api/payments/order-checkout-session.php` only after creating a pending order.

## Acceptance checklist

- [x] Persistent cart and cart items
- [x] Server-calculated cart totals
- [x] Published-product validation
- [x] Single-merchant and single-currency enforcement
- [x] Checkout draft with frozen snapshots and expiration
- [x] Idempotent pending-order creation
- [x] Immutable order lines
- [x] Fee snapshot
- [x] Pending receipt
- [x] Order status history
- [x] Order audit events
- [x] Buyer-scoped order and receipt reads
- [x] Super-admin order list and detail reads
- [x] Payment session created from pending order
- [x] Receipt finalization on successful payment
- [x] Existing PPPM issuance preserved
- [x] Schema runner, smoke validation, PHPUnit contract tests, and CI
