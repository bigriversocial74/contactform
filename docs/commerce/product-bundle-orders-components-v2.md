# Product Bundle Orders and Components v2

## Purpose

This phase establishes the durable parent-order and merchant-component records required before customer checkout, Stripe multi-merchant transfers, PPPM issuance, and recipient Action Center projection are enabled.

## Canonical model

One customer bundle purchase becomes one `gift_bundle_orders` parent record. Every accepted bundle component becomes one `gift_bundle_order_components` record with its own merchant, immutable product/version snapshot, financial allocation, claim policy, settlement policy, and future PPPM/Microgift linkage.

A bundle order is not a universal voucher. Each component remains independently authorized to its actual merchant.

## Snapshot authority

At reservation time the service freezes:

- Bundle identity and terms version.
- Component product and product-version authority.
- Quantity and customer price.
- Commission rate, amount, source, and rule version.
- Merchant estimated net.
- Settlement and claim policies.
- Inventory commitment and reservation details.

Money is stored in integer cents and percentages in basis points.

## Inventory

`gift_bundle_inventory_reservations` provides idempotent component-level reservations. Reservations can later be committed after payment or released after cancellation, timeout, or checkout failure.

## Commerce linkage

`mg_bundle_order_link_commerce()` links the parent bundle order to a future customer-facing `commerce_orders` record and links each bundle component allocation to its corresponding `commerce_order_items` row.

This phase does not create a Stripe charge and does not execute merchant transfers.

## Fulfillment linkage

Each component record contains nullable links for:

- `pppm_issuance_request_id`
- `pppm_item_id`
- `microgift_instance_id`

Later fulfillment phases will populate these links idempotently, one component at a time, while maintaining one parent bundle presentation.

## Import order

1. `database/20260719_merchant_bundle_commission_authority_v1.sql`
2. `database/20260719_product_bundles_foundation_builder_v1.sql`
3. `database/20260719_product_bundle_orders_components_v2.sql`

## Deferred

- Customer bundle checkout UI and payment intent creation.
- Stripe Separate Charges and Transfers.
- Payment webhooks and transfer release.
- PPPM and Microgift issuance execution.
- Parent Action Center and recipient wallet projections.
- Sending, claims, redemption, refunds, and reversals.
