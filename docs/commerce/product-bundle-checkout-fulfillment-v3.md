# Product Bundle Checkout and Fulfillment v3

## Scope

Phase 3 turns a reserved bundle order into an executable payment and component-fulfillment workflow.

## Checkout authority

`mg_bundle_checkout_start()`:

- Requires the authenticated buyer to own the bundle order.
- Accepts only reserved or awaiting-payment orders.
- Uses the canonical `mg_payment_create_source_intent()` adapter.
- Binds the payment intent to `source_type=gift_bundle_order`.
- Enforces amount, currency, source, and idempotency consistency.
- Stores a durable checkout-attempt record.

## Payment finalization

`mg_bundle_checkout_mark_paid()`:

- Resolves the checkout attempt by provider intent reference.
- Idempotently marks the canonical payment intent and bundle order paid.
- Commits active component inventory reservations.
- Creates one durable Microgift fulfillment dispatch per bundle component.
- Does not create merchant transfers or release settlement.

## Component fulfillment

`mg_bundle_fulfillment_dispatch()`:

- Refuses fulfillment before payment.
- Locks and processes one component dispatch at a time.
- Passes the component merchant, product/version, recipient owner, quantity, and idempotency key to an injected Microgift issuer.
- Records the resulting Microgift instance link.
- Advances the parent bundle order only after every component dispatch completes.

The callable issuer boundary allows the next integration step to reuse the production Microgift engine without duplicating issuance rules in the bundle service.

## Database

Migration:

`database/20260719_product_bundle_checkout_fulfillment_v3.sql`

Import after:

1. `20260719_merchant_bundle_commission_authority_v1.sql`
2. `20260719_product_bundles_foundation_builder_v1.sql`
3. `20260719_product_bundle_orders_components_v2.sql`

## Explicitly deferred

- Stripe Separate Charges and Transfers.
- Merchant settlement release.
- Refund and transfer reversal execution.
- Customer-facing bundle storefront and checkout UI.
- Action Center parent projection.
- Sending, claims, and redemption UX.
