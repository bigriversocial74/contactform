# Campaign Ads Value Attribution

This stage turns Campaign Ads reporting from activity metrics into read-only business-value metrics.

## Scope

This is a reporting-only attribution layer. It does not create billing, payouts, wallet writes, claim writes, or redemption writes.

## Attribution sources

The performance API now reads value from existing Microgifter wallet records when they are available:

- `wallet_items.value_cents_snapshot`
- `wallet_items.status`
- `wallet_items.claimed_at`
- `wallet_items.redeemed_at`
- `wallet_items.expires_at`

The API uses two attribution paths:

1. **Direct attribution**
   - `ad_events.wallet_item_id -> wallet_items.id`
   - This is the strongest attribution path.
   - Direct attribution wins if the same wallet item is also available through campaign-assisted attribution.

2. **Campaign-assisted attribution**
   - `ad_campaigns.campaign_id -> wallet_items.campaign_id`
   - This attributes value when an ad campaign is tied to an existing Microgifter campaign.
   - This is useful when the ad surface drives activity into a campaign that issues wallet items.

## Calculated values

- Claimed value
- Redeemed value
- Unredeemed future demand
- Pre Sale Revenue impact
- Attributed wallet items
- Attributed claimed items
- Attributed redeemed items
- Direct wallet items
- Campaign-assisted wallet items
- Cost per attributed claim
- Cost per attributed redemption

## Pre Sale Revenue logic

For this stage, PSR impact equals the value of claimed wallet items because a claimed wallet item represents committed future customer intent.

Unredeemed future demand is claimed value minus redeemed value, excluding expired items.

## Dashboard impact

The existing dashboards now show real value fields when wallet links exist:

- `/merchant-ad-performance.php`
- `/admin/ad-performance.php`

Placement rows and campaign rows also include claimed value, redeemed value, future demand, and PSR impact.

## No SQL required

This stage uses the existing Campaign Ads and Stage 12 wallet/campaign tables.

Required existing tables:

- `ad_campaigns`
- `ad_events`
- `wallet_items`

Optional linked tables already used elsewhere:

- `campaigns`
- `reward_templates`
- `campaign_events`

## Test notes

1. Create or approve an ad tied to a real `campaign_id`.
2. Issue wallet items for that campaign.
3. Claim one wallet item.
4. Redeem one claimed wallet item.
5. Open `/merchant-ad-performance.php` and `/admin/ad-performance.php`.
6. Confirm claimed value, redeemed value, future demand, and PSR impact update.

If no wallet items are linked yet, the dashboards still load but value fields remain zero.
