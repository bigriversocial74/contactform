# Product Bundles Foundation and Merchant Builder v1

## Scope

This phase introduces the merchant-facing Product and Experience Bundle foundation without charging customers or creating Stripe transfers.

Delivered:

- Canonical bundle, component, participant, and audit records.
- Merchant Bundle dashboard and seven-step Bundle Builder.
- Published catalog product selection with immutable product/version snapshots.
- Canonical commission resolution through `api/payments/_commissions.php`.
- Merchant-default, bundle-starting-rate, and custom participant rate modes.
- Live commission and merchant-net previews.
- Versioned merchant invitation workflow with accept, counter, decline, and question states.
- Publish validation for products, terms, merchant acceptance, claim policy, settlement policy, pricing, and cover creative.
- Merchant permissions through existing catalog permission authority.
- Dedicated contract tests and GitHub Actions workflow.

## Required SQL

Import in this order:

1. `database/20260719_merchant_bundle_commission_authority_v1.sql`
2. `database/20260719_product_bundles_foundation_builder_v1.sql`

The API checks both schemas and fails safely when either migration is missing.

## Merchant routes

- `/merchant-bundles.php`
- `/merchant-bundle-invitations.php`

## API

`/api/merchant/bundles.php`

GET actions:

- `list`
- `detail`
- `products`
- `merchants`
- `invitations`

POST actions:

- `create`
- `add_component`
- `invite`
- `respond`
- `publish`

## Financial rules

- Money is stored and calculated in integer cents.
- Commission rates use basis points.
- No transaction-time hard-coded percentage is permitted.
- Component quotes store commission rate, amount, source, rule version, and merchant net.
- Published terms are snapshotted.
- Another merchant must accept the current terms version before publication.

## Deferred

- Customer bundle checkout.
- Stripe Separate Charges and Transfers.
- Bundle order and fulfillment records.
- PPPM and Microgift child issuance.
- Action Center parent projections.
- Recipient wallet, sending, claims, redemption, refunds, and public discovery.
