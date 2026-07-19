# Merchant and Bundle Commission Authority v1

## Purpose

This package makes commission resolution a single, configurable commerce authority. It replaces transaction-time dependence on a global hard-coded assumption with effective-dated merchant profiles, optional bundle profiles, accepted participant terms, and immutable purchase snapshots.

## Resolution order

For ordinary storefront purchases:

1. Active merchant profile.
2. Platform starting commission fallback.

For bundle components:

1. Accepted bundle participant terms.
2. Bundle starting rate when that bundle mode is selected.
3. Active merchant profile.
4. Platform starting commission fallback.

The platform starting rate is configurable. A new merchant receives an explicit fixed profile initialized from the platform rate in effect at activation. Later platform changes do not silently change that merchant unless the profile deliberately follows the platform default.

## Rate modes

Merchant profiles support fixed, contract, promotional, and follow-platform modes. Bundle profiles support merchant defaults, one bundle starting rate, or custom participant rates.

## Money rules

All rates are stored as integer basis points and all amounts as integer cents. Percentage commission is rounded once per component. The optional fixed payment fee is applied once at the order level and never once per bundle merchant.

## Snapshot protection

Checkout creates a draft commission snapshot. Order conversion promotes it to an immutable order snapshot and creates a line snapshot for every commerce order item. Stripe transfers, refunds, reversals, accounting, and reporting must use these snapshots rather than a merchant’s current profile.

## Administration

Authorized administrators manage the platform starting rate and merchant terms at `/admin-commissions.php`. Merchants see a read-only commission summary in Payments. Commission changes require explicit confirmation and are written to immutable history and the general audit log.

## Bundle readiness

The bundle tables use a neutral `bundle_reference` so the commission foundation can land before the full Gift Bundle schema. The future Bundle Builder must create and lock a bundle commission profile, collect participant acceptance where required, and pass the reference into component quoting.

## Deployment

Import `database/20260719_merchant_bundle_commission_authority_v1.sql` after the Main Admin Agent Phase 6 migration, then deploy the code. Existing merchants are initialized from the configured platform rate during migration. Merchants created later receive an explicit profile on their first commission-authority checkout or Payments read.
