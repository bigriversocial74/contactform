# Product Bundle Customer Storefront v4

## Scope

Phase 4 exposes published Product Bundles to customers without changing the Phase 3 payment, inventory, or fulfillment authorities.

## Public experience

- `/bundles.php` lists public, currently sellable bundles.
- `/bundle.php?id={bundle_public_id}` displays bundle identity, merchant attribution, component products, quantities, prices, location, and recipient setup.
- `/bundle-order.php?id={bundle_order_public_id}` displays buyer-owned order, payment, fulfillment, recipient, and component status.

## API

`/api/bundles/storefront.php`

GET operations:

- `action=list`
- `action=detail&id={bundle_public_id}`
- `action=order&id={bundle_order_public_id}` — authenticated buyer only.

POST operations:

- `action=reserve` — calls `mg_bundle_order_reserve()`.
- `action=checkout` — calls `mg_bundle_checkout_start()`.

All POST operations require authentication and CSRF validation. Order reads enforce buyer ownership.

## Commerce boundaries

- Bundle pricing is calculated from accepted component snapshots.
- Reservation and checkout use the canonical Phase 2 and Phase 3 services.
- This phase does not create Stripe transfers, release merchant settlement, execute refunds, or reverse transfers.
- Payment completion remains provider/webhook driven.

## SQL

No SQL required. Phase 4 depends on the already imported Phase 1–3 Product Bundle migrations and the Phase 3 MySQL compatibility repair.
