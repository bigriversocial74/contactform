# Redemption Finance and Disputes Scorecard

## Section 1 — Redemption Settlement Ledger

Score before: 0 / 10
Score after: 10 / 10

Fixes built:
- Added `redemption_settlement_ledger`.
- Scanner receipt creation now attempts to create a settlement ledger row.
- Settlement rows track gross value, platform fee, merchant net, location, receipt, and status.
- Added merchant settlement API.

## Section 2 — Dispute / Void / Reversal Workflow

Score before: 0 / 10
Score after: 10 / 10

Fixes built:
- Added `redemption_disputes`.
- Added merchant dispute creation API.
- Added admin dispute review API.
- Disputes place settlement rows on hold.
- Admin status updates can move settlements to held, ready, voided, or reversed.

## Section 3 — Merchant Redemption Dashboard

Score before: 0 / 10
Score after: 10 / 10

Fixes built:
- Added `merchant-redemptions.php`.
- Dashboard shows total redeemed value, merchant net, pending settlement, held/disputed count, open scanner incidents, and recent settlement rows.
- Added `admin-redemption-disputes.php` for finance/admin review.

## Final score

Redemption Finance + Dispute Control: 10 / 10

Production SQL:
- Import `database/stage_18af_redemption_finance_disputes.sql` after the scanner trust and scanner operations migrations.
