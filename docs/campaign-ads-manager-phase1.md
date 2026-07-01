# Campaign Ads Manager Phase 1

Repository branch: `feature/advertising-module-phase1`

## Product scope

Campaign Ads Manager Phase 1 is a controlled promotional CRM advertising layer for Microgifter. It supports merchant campaign boosts and sponsored local drops across:

- `feed_sponsored_card`
- `sidebar_sponsored_card`
- `world_canvas_sponsored_pin`
- `target_zone_sponsored_drop`

This is intentionally not a full ad marketplace yet. Phase 1 does not include auctions, CPM/CPC bidding, external advertiser onboarding, automated billing, or open advertiser competition.

## Implemented sections

### Schema

Migration file:

`database/microgifter_ads_manager_phase1.sql`

Tables:

- `ad_campaigns`
- `ad_creatives`
- `ad_placements`
- `ad_campaign_placements`
- `ad_targeting_rules`
- `ad_events`
- `ad_reviews`

The migration avoids foreign keys to prevent shared-host import errors when legacy referenced tables are absent or use mixed engines. Application-level ownership checks and indexes are used instead.

### APIs

- `api/ads/create.php`
- `api/ads/update.php`
- `api/ads/submit.php`
- `api/ads/review.php`
- `api/ads/placements.php`
- `api/ads/list.php`
- `api/ads/performance.php`
- `api/ads/track.php`
- `api/ads/render-placement.php`

Shared helper:

- `api/ads/_ads.php`

### Merchant UI

- `merchant-ad-manager.php`
- `merchant-ad-create.php`
- `merchant-ad-performance.php`

The create and performance routes currently load the same manager shell so the first PR keeps UX centralized and avoids page sprawl.

### Admin UI

- `admin/ad-review.php`
- `admin/ad-placements.php`

The placements route currently loads the same review shell while placement management remains seeded and API-driven.

### Frontend assets

- `assets/css/merchant-ad-manager.css`
- `assets/css/sponsored-campaign-card.css`
- `assets/js/merchant-ad-manager.js`
- `assets/js/admin-ad-review.js`
- `assets/js/sponsored-campaign-card.js`

### Placement wiring

- `feed.php` now includes a feed sponsored card slot and sidebar sponsored card slot.
- `merchant-canvas.php` now includes sponsored map-layer containers for World Canvas sponsored pins and Target Zone sponsored drops.

## Phase 1 limitations

- No real billing or charging is connected.
- Value fields such as claimed value, redeemed value, and Pre Sale Revenue impact are reserved and return zero until wired to live claim/wallet/redemption value sources.
- World Canvas and Target Zone rendering uses reusable placement containers; deeper geospatial logic can be layered onto the same API later.
- Wallet, Inbox, and Claim Success placements are seeded for future use but not rendered in Phase 1.

## Long-run 10/10 suggestions already accounted for

- Built an approval gate before public rendering.
- Added placement keys now so future surfaces can opt in without changing the campaign schema.
- Added tracking for downstream events before every downstream flow is wired, so claim/wallet/redemption integration will not need schema changes.
- Kept no-FK migration style to avoid the MySQL import issue that has happened in prior admin ops migrations.
- Kept billing out of Phase 1 to avoid creating accidental merchant payout/liability behavior.
