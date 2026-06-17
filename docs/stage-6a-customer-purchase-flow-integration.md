# Stage 6A Customer Purchase Flow Integration

## Purpose

Stage 6A is the first integration and alignment phase after Stage 5J. It connects the customer-facing UI to the restored Stage 5J commerce contract instead of adding new backend scope.

## Canonical customer flow

`Published Product -> Add to Cart -> Cart Page/Drawer -> Checkout Draft -> Pending Order -> Payment Session -> Checkout -> Receipt -> Purchased Item / Inbox Follow-up`

## Implemented UI surfaces

- `/cart.php` now loads the authenticated server cart and drives checkout creation.
- `/checkout.php?session=...` now represents only an existing payment session.
- `/checkout-success.php?order=...` loads the receipt and shows next-step navigation.
- `/account/orders.php` lists buyer-scoped commerce orders and receipt links.
- The cart drawer now refreshes from the server-side cart instead of treating localStorage as the source of truth.

## Integrated APIs

- `GET /api/commerce/cart.php`
- `DELETE /api/commerce/cart.php`
- `POST /api/commerce/cart-items.php`
- `PATCH /api/commerce/cart-item.php`
- `DELETE /api/commerce/cart-item.php`
- `POST /api/commerce/checkout-draft.php`
- `POST /api/commerce/orders.php`
- `POST /api/payments/order-checkout-session.php`
- `GET /api/payments/session.php`
- `POST /api/payments/sandbox-confirm.php`
- `GET /api/commerce/receipt.php`
- `GET /api/commerce/orders.php`

## Alignment decisions

1. The server-side cart is the canonical cart.
2. Local cart state is no longer the checkout source of truth.
3. Checkout pages do not accept raw product selections.
4. Payment sessions are created only after a pending order exists.
5. Checkout success reads the receipt by buyer-scoped order ID.
6. Customer account order history reads from the buyer-scoped order API.
7. Generic add-to-cart binding supports templates that expose `data-product-version-id`.

## 10/10 direction

This phase improves purpose, security, and functionality by making the intended commerce lifecycle usable end to end:

- Purpose: customer UX now matches the Stage 5J backend flow.
- Security: pricing, order creation, receipts, and payment session creation stay server-side.
- Functionality: customers can progress from cart to checkout to receipt without relying on disconnected placeholder UI.

## Follow-up for Stage 6B

- Add richer account item views for purchased/sent/received PPPM items.
- Add product/store template buttons using `data-add-to-cart` and `data-product-version-id` where missing.
- Add empty/error states for logged-out users that route to sign-in.
- Add browser-level integration tests once the frontend route set is stable.
