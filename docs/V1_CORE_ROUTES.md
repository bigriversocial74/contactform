# Microgifter V1 Core Routes

This document defines the canonical V1 application surface. Existing advanced modules remain in the repository but are frozen unless they directly support this flow.

## Canonical V1 lifecycle

1. Merchant creates and publishes a product.
2. Customer discovers the product and adds a published version to the cart.
3. Customer completes sandbox checkout.
4. The order issues one PPPM item and one Microgift instance per purchased quantity.
5. The purchaser keeps the gift or sends it to another registered user.
6. The current owner may regift an available gift to another registered user.
7. Only the most recent sender may follow up with the current owner through the transfer-scoped message thread.
8. A merchant location verifies its claim code and atomically redeems the available Microgift.
9. The recipient sees the gift in Claimed; the most recent sender sees the Sent record marked Claimed.

## Customer routes

| Purpose | Page | Canonical API |
|---|---|---|
| Discover products | `/discover.php` | `/api/public/product-discovery.php` |
| View product | `/product.php` | `/api/public/product.php` |
| Cart | `/cart.php` | `/api/commerce/cart-items.php` |
| Checkout | `/checkout.php` | `/api/commerce/checkout-draft.php`, `/api/commerce/orders.php`, `/api/payments/order-checkout-session.php` |
| Sandbox confirmation | `/checkout.php` | `/api/payments/sandbox-confirm.php` |
| Inbox | `/inbox.php` | `/api/account/action-center.php` |
| Sent | `/sent.php` | `/api/account/action-center.php` |
| Claimed | `/claimed.php` | `/api/account/action-center.php` |
| Send or regift | Action Center modal | `/api/account/action-center-send.php` |
| Follow up | Action Center message modal | transfer-scoped messaging endpoint introduced in Stage D |

## Merchant routes

| Purpose | Page | Canonical API |
|---|---|---|
| Workspace | `/merchant.php` | `/api/merchant/overview.php` |
| Products | `/merchant-products.php` | `/api/merchant/products.php` |
| Product editor | `/merchant-product.php` and `/build.php` | `/api/catalog/builder-draft.php` |
| Storefront | `/merchant-storefront.php` | canonical storefront APIs |
| Locations | `/merchant-locations.php` | `/api/merchant/locations.php` |
| Claims | `/merchant-claims.php` | `/api/merchant/microgift-claim.php` |
| Payments | `/merchant-payments.php` | canonical merchant financial APIs |

## Canonical domain authorities

- Product publication: `api/catalog/builder-draft.php`
- Product distribution: `api/catalog/_publish_distribution.php`
- Cart and order creation: `api/commerce/*`
- Payment capture: `api/payments/_capture.php`
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

Ownership transfer does not create a new lifecycle status. Send and Regift are transfer events applied while the gift remains available.

### User-facing actions and views

- Send as a gift
- Regift
- Follow Up
- Inbox
- Sent
- Claimed

### Rules

- Original merchant, product version, order origin, value, and PPPM identity are immutable.
- Every ownership change is recorded as a transfer.
- The current owner is the only normal user allowed to regift.
- Terminal gifts cannot be transferred.
- Follow Up does not resend or transfer the gift; it creates a message in the latest transfer thread.
- Only the most recent sender can initiate Follow Up with the current owner.
- Earlier senders retain historical Sent records but cannot access later recipients or later transfer conversations.
- Customer-facing Claimed corresponds to canonical merchant redemption.

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

No new V1 frontend may call legacy gift verification or redemption routes when a canonical Microgift authority exists. In particular, merchant redemption must converge on `/api/merchant/microgift-claim.php`. Legacy routes remain temporarily available only for compatibility until their callers are migrated and tested.
