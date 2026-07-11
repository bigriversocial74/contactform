# Loyalty Quest Analytics and Reporting v1

## Purpose

Loyalty Quest Analytics converts existing Microgifter campaign, participation, evidence, notification-delivery, reward-staging, and redemption records into merchant-facing aggregate reports.

It does not create a second event ledger or ownership system. Wallet records remain internal staging evidence; customer reward ownership remains Microgift → PPPM → Inbox.

## Merchant workspace

Open:

`/merchant-loyalty-quest-analytics.php`

The report provides:

- campaign-contact to participant funnel
- started, completed, Inbox-delivered, claimed, and redeemed activity
- completion, evidence-approval, claim, redemption, and delivery rates
- actual lifecycle trends by event timestamp
- evidence-method quality and privacy-thresholded distance averages
- participation source comparisons
- average completion, review, and redemption times
- campaign comparison table
- CSV and JSON aggregate exports

## Date and campaign filters

Supported report windows are 7, 30, 90, 180, and 365 days. Reports may cover every merchant-owned Loyalty Quest or one selected campaign.

Participant completion rate is cohort-based: completed participants among participants who joined during the selected period. Trend lines use actual lifecycle timestamps, including `completed_at`, `verified_at`, `issued_at`, `claimed_at`, and `redeemed_at`.

## Currency handling

Financial values are never combined across currencies. Summary value is returned in `value_by_currency`. A campaign with more than one recorded currency is marked `mixed_currency`, and its value is not shown as one false total.

## Privacy contract

The analytics API and exports exclude:

- participant names and email addresses
- user IDs and contact IDs
- proof notes and proof URLs
- raw QR or signed verification payloads
- claim codes or voucher tokens
- precise latitude and longitude

Average verified distance is returned only when an evidence group contains at least five records. CSV cells beginning with formula-control characters are prefixed to prevent spreadsheet formula execution.

## APIs

JSON report:

`GET /api/merchant/loyalty-quest-analytics.php?days=30&campaign_id=<optional UUID>`

Aggregate export:

`GET /api/merchant/loyalty-quest-analytics-export.php?format=csv|json&days=30&campaign_id=<optional UUID>`

Both endpoints require authenticated merchant ownership. Export also requires the existing `intelligence.exports.create` permission.

## Database and deployment

No new SQL migration is required. The section reads existing indexed tables:

- `campaigns`
- `campaign_contacts`
- `loyalty_quest_participations`
- `loyalty_quest_evidence`
- `wallet_items` as internal reward-staging evidence
- `message_events`
- `message_delivery_jobs`

Notification delivery metrics report as unavailable until the registered Loyalty Quest notification migration has been applied.

## Verification

The dedicated validation workflow runs:

- PHP 8.2 and PHP 8.3 syntax
- JavaScript syntax
- static 10/10 architecture contract
- database-backed merchant-isolation, timing, privacy, currency, source, and delivery-rate behavior
- Loyalty Quest campaign, participant, merchant, marketplace, notification, and migration regressions

Browser rendering and production-volume query performance still require environment-specific verification after deployment.
