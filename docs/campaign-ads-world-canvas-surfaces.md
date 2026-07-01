# Campaign Ads World Canvas Sponsored Layers

This stage activates existing Campaign Ads map placements on the real global World Canvas page.

## Page

```txt
/world-canvas.php
```

## Scope

Added to `world-canvas.php`:

- `/assets/css/sponsored-campaign-card.css`
- `/assets/js/sponsored-campaign-card.js`
- `world_canvas_sponsored_pin` map layer
- `target_zone_sponsored_drop` map layer

## Why this exists

`merchant-canvas.php` already renders sponsored pin/drop layers inside the merchant Store Canvas. The global World Canvas page is separate and did not yet load or render those sponsored layers.

This PR brings the same existing ad placement system into the real World Canvas page without creating a separate Campaign Drops map surface.

## Placements used

```txt
world_canvas_sponsored_pin
target_zone_sponsored_drop
```

## Existing systems reused

- `/api/ads/render-placement.php`
- Admin placement controls at `/admin/ad-placements.php`
- Existing sponsored card renderer
- Existing impression/click/save tracking
- Existing direct attribution stack
- Existing value attribution dashboard

## SQL required

No SQL required.

## Exclusions

No changes to:

- `wallet.php`
- `wallet_recommendation`
- billing
- payouts
- claim logic
- redemption logic
- wallet business rules

## Test path

1. Open `/admin/ad-placements.php`.
2. Assign approved/demo ads to `world_canvas_sponsored_pin` and `target_zone_sponsored_drop`.
3. Open `/world-canvas.php`.
4. Confirm sponsored pins and sponsored zones render inside the World Canvas map.
5. Click a sponsored pin/drop.
6. Open `/merchant-ad-performance.php` or `/admin/ad-performance.php`.
7. Confirm impressions/clicks are tracked for the World Canvas placements.
