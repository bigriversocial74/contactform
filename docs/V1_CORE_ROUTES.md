# Microgifter V1 Core Routes

This document defines the canonical V1 application surface. Existing advanced modules remain in the repository but are frozen unless they directly support this flow.

## Canonical V1 lifecycle

1. A merchant creates and publishes a positive-value Public product.
2. A customer discovers the product and adds its immutable published version to the cart.
3. The customer creates a frozen checkout draft and pending order.
4. Stripe Hosted Checkout collects the existing gift price. The configured platform share is included in that price rather than added as a surcharge.
5. A signed Stripe webhook is the payment-confirmation authority.
6. The paid-order transaction posts the balanced ledger split and issues one PPPM item and one Microgift instance per purchased quantity.
7. The purchaser keeps the gift or sends it to another registered user.
8. The current owner may regift an available gift to another registered user.
9. Only the most recent sender may follow up with the current owner through the transfer-scoped message thread.
10. An authorized merchant location verifies its private claim code and atomically redeems the available Microgift.
11. The current owner sees the gift in Claimed; historical senders retain Sent history marked Redeemed.

The sandbox confirmation adapter remains available only for deterministic CI and local behavior tests through `/api/payments/sandbox-confirm.php`. It is not the production payment authority.

## Customer routes

| Purpose | Page | Canonical API |
|---|---|---|
| Discover products | `/discover.php` | `/api/public/product-discovery.php` |
| View product | `/product.php` | `/api/public/product.php` |
| Cart | `/cart.php` | `/api/commerce/cart-items.php` |
| Checkout | `/checkout.php` | `/api/commerce/checkout-draft.php`, `/api/commerce/orders.php`, `/api/payments/order-checkout-session.php` |
| Hosted payment return | `/checkout-success.php` | `/api/commerce/order-confirmation.php` |
| Inbox | `/inbox.php` | `/api/account/action-center.php` |
| Sent | `/sent.php` | `/api/account/action-center.php` |
| Claimed | `/claimed.php` | `/api/account/action-center.php` |
| Send or regift | Action Center modal | `/api/account/action-center-send.php` |
| Follow Up | Action Center message modal | `/api/account/action-center-follow-up.php` |

## Merchant routes

| Purpose | Page | Canonical API |
|---|---|---|
| Workspace | `/merchant.php` | `/api/merchant/overview.php` |
| Products | `/merchant-products.php` | `/api/merchant/products.php` |
| Product editor | `/merchant-product.php` and `/build.php` | `/api/catalog/builder-draft.php` |
| Storefront | `/merchant-storefront.php` | canonical storefront APIs |
| Locations | `/merchant-locations.php` | `/api/merchant/locations.php` |
| Claims | `/merchant-claims.php` | `/api/merchant/microgift-claim.php` |
| Stripe Connect | `/merchant-payments.php` | `/api/merchant/payment-connect.php`, `/api/merchant/payment-account.php` |

## Administrative payment route

| Purpose | Page | Canonical API |
|---|---|---|
| Stripe credentials, fee policy and readiness | `/admin-payments.php` | `/api/admin/payment-settings.php` |

## Canonical domain authorities

- Product publication: `api/catalog/builder-draft.php`
- Product distribution: `api/catalog/_publish_distribution.php`
- Cart and order creation: `api/commerce/*`
- Hosted checkout creation: `api/payments/_checkout_session.php`
- Payment confirmation: `api/payments/webhook.php`
- Paid-order capture and fulfillment orchestration: `api/payments/_capture.php`
- Stripe platform and Connect readiness: `api/payments/_readiness.php`
- PPPM issuance and ownership: `api/pppm/*`
- Microgift lifecycle: `api/microgifts/*`
- Action Center projections: `api/microgifts/_action_center_projection.php`
- Merchant redemption: `api/merchant/microgift-claim.php`
- Messaging: `api/messages/*`
- Notifications: `api/communications/*`

## V1 terminology

### Domain status

- `available`
- `redeemed`
- `expired`
- `cancelled`
- `revoked`

Ownership transfer does not create a new terminal lifecycle status. Send and Regift are transfer events applied while the gift remains available or delivered.

### User-facing actions and views

- Send as a gift
- Regift
- Follow Up
- Inbox
- Sent
- Claimed

### Rules

- Original merchant, product version, order origin, value, platform-fee snapshot, and PPPM identity are immutable.
- The customer pays the published gift price; the platform share is retained from that amount.
- A paid order is fulfilled only after a valid provider confirmation reaches the canonical payment service.
- Signed webhook event IDs are idempotent, and conflicting signed replays are rejected.
- Every ownership change is recorded as a transfer.
- The current owner is the only normal user allowed to regift.
- Terminal gifts cannot be transferred.
- Follow Up does not resend or transfer the gift; it creates a message in the latest transfer thread.
- Only the most recent sender can initiate Follow Up with the current owner.
- Earlier senders retain historical Sent records but cannot access later recipients or later transfer conversations.
- Customer-facing Claimed corresponds to canonical merchant-location redemption.

## Frozen for post-V1 work

The following modules remain available to administrators and developers where required, but are not part of the V1 customer or merchant navigation:

- Agents and orchestration
- Demand forecasting and predictive sales
- Social feed publishing beyond product distribution requirements
- Distribution program management
- Subscriptions
- Tips
- AI model settings
- Advanced intelligence dashboards
- Enterprise gifting programs
- SMS and push notification delivery
- Advanced disputes and payout operations

## Legacy routes

No new V1 frontend may call legacy gift verification, redemption, or payment-confirmation routes when a canonical authority exists. Merchant redemption must converge on `/api/merchant/microgift-claim.php`, and production payment confirmation must converge on `/api/payments/webhook.php`. Legacy routes remain temporarily available only for compatibility or deterministic tests until their callers are migrated and verified.
