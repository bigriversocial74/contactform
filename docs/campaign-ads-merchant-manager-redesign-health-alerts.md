# Campaign Ads Merchant Manager Redesign, Product Picker, and Health Alerts

This stage refreshes the merchant Campaign Ads Manager page, adds read-only Campaign Ads health alerts, and adds a product/reward picker to the ad builder.

## Merchant page

```txt
/merchant-ad-manager.php
```

The merchant page follows a lighter dashboard layout:

- page header
- KPI card row
- tabbed interface
- Create Campaign tab
- Sponsored Preview tab
- Merchant Campaigns tab
- Analytics tab
- right-side sponsored preview/help card
- campaign search/table view
- merchant health alert banner
- product/reward picker in the ad builder

## Product picker

New read-only API:

```txt
/api/ads/merchant-products.php
```

The first product source is the existing merchant-owned `reward_templates` table. This is currently the closest existing product/reward catalog source in the codebase.

The dropdown lets the merchant choose an active reward/product and apply it to the ad draft.

Applying a product can prefill:

- campaign title
- sponsored card headline
- short offer description
- image URL from reward media cover image
- CTA label
- destination URL
- objective
- targeting metadata

Product source metadata is saved into ad targeting rules:

```txt
source_product_id
source_product_type
source_product_title
```

No new product table is created.

## Tabs

```txt
Create Campaign
Sponsored Preview
Merchant Campaigns
Analytics
```

The existing campaign create/update/submit APIs are preserved:

```txt
/api/ads/create.php
/api/ads/update.php
/api/ads/submit.php
/api/ads/list.php
/api/ads/performance.php
```

## Placement options

The create form keeps the original Phase 1 placements and adds the activated recommendation surfaces:

```txt
feed_sponsored_card
sidebar_sponsored_card
world_canvas_sponsored_pin
target_zone_sponsored_drop
inbox_recommendation
claim_success_recommendation
```

`wallet_recommendation` remains excluded because `wallet.php` is not currently a user-facing recommendation page.

## Health alerts

Read-only API:

```txt
/api/ads/health-alerts.php
```

Shared assets:

```txt
/assets/js/ad-health-alerts.js
/assets/css/ad-health-alerts.css
```

Health alert banners are shown on:

```txt
/merchant-ad-manager.php
/admin/ad-review.php
/admin/ad-placements.php
/admin/ad-diagnostics.php
```

## What health alerts check

Admin scope:

- missing Campaign Ads schema
- optional wallet/value attribution readiness
- direct attribution column readiness
- active placement with no approved/active assignments
- assigned placement returning zero ads from the existing renderer
- renderable placement with no impressions yet
- inactive high-value surfaces

Merchant scope:

- no campaigns yet
- campaign with no placements
- approved/active campaign with no impressions yet
- campaign with impressions but no clicks yet

## Read-only scope

No changes are made to:

- billing
- payouts
- wallet state
- claim state
- redemption state
- placement settings
- campaign status

## SQL required

No SQL required.

## Test path

1. Open `/merchant-ad-manager.php`.
2. Confirm KPI cards load across the top.
3. Confirm tabs switch between Create, Sponsored Preview, Merchant Campaigns, and Analytics.
4. Confirm the product dropdown loads active merchant reward templates.
5. Select a product and click Apply Product.
6. Confirm title, headline, description, image URL, CTA, destination URL, and preview update.
7. Save the campaign and load it again from Merchant Campaigns.
8. Confirm the selected product metadata is preserved when available.
9. Open `/api/ads/merchant-products.php` while signed in as a merchant.
10. Open `/api/ads/health-alerts.php?scope=merchant` while signed in as a merchant.
11. Open `/admin/ad-review.php`, `/admin/ad-placements.php`, and `/admin/ad-diagnostics.php` as an ads admin.
12. Confirm the health alert banner loads on each page.
13. Use `/admin/ad-placements.php` to activate/assign placements and confirm the health alert count changes.
