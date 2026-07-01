# Campaign Ads Direct Attribution Tracking

This stage strengthens Campaign Ads attribution by carrying the sponsored ad source into wallet save, claim, and redemption actions.

## Scope

Read/write behavior is limited to attribution metadata and ad event records.

No billing, payout, claim, redemption, wallet balance, or commerce settlement logic is added.

## Client behavior

`assets/js/sponsored-campaign-card.js` now stores the most recent sponsored ad source when a user clicks or saves a sponsored card.

Stored attribution fields:

- `ad_campaign_id`
- `placement_key`
- `surface`
- `source`
- `captured_at`

The attribution is stored in session/local storage for 14 days and exposed through:

```js
Microgifter.getAdAttribution()
Microgifter.applyAdAttribution(payload)
```

Any wallet/client flow can call `Microgifter.applyAdAttribution(payload)` before sending a wallet save, claim, or redemption request.

## Server behavior

Shared helper:

```txt
api/ads/_direct_attribution.php
```

The helper safely extracts ad attribution from request input or existing wallet metadata, then writes direct ad events when possible.

## Wallet save

`api/public/wallet/add.php` now:

- accepts `ad_attribution` in the request payload
- stores attribution in `wallet_items.metadata_json`
- writes a direct `wallet_save` ad event with `wallet_item_id`
- does not fail wallet save if ad tracking fails

## Wallet claim

`api/account/wallet-claim.php` now:

- accepts `ad_attribution` in the request payload
- falls back to attribution already stored in wallet metadata
- persists attribution metadata onto the wallet item
- writes a direct `claim` ad event with `wallet_item_id`
- does not fail claim if ad tracking fails

## Wallet redemption

`api/merchant/wallet-redeem.php` now:

- accepts `ad_attribution` in the request payload
- falls back to attribution already stored in wallet metadata
- persists attribution metadata onto the wallet item
- writes a direct `redeem` ad event with `wallet_item_id`
- records the claimant user as the ad event user when available
- does not fail redemption if ad tracking fails

## Why this matters

The previous value attribution layer could calculate value when wallet links already existed. This stage makes those links stronger during the actual user journey:

```txt
Sponsored ad -> click/save -> wallet item -> claim -> redemption -> value attribution
```

## SQL required

No SQL required.

Uses existing fields:

- `ad_events.wallet_item_id`
- `wallet_items.metadata_json`
- `wallet_items.value_cents_snapshot`
- `wallet_items.claimed_at`
- `wallet_items.redeemed_at`

## Test path

1. Open a page with sponsored ads, such as `/feed.php` or `/merchant-agent-chat.php`.
2. Click or save a sponsored ad.
3. Add a linked offer to wallet.
4. Claim the wallet item.
5. Redeem the wallet item.
6. Open `/merchant-ad-performance.php` and `/admin/ad-performance.php`.
7. Confirm direct wallet items, claimed value, redeemed value, future demand, and PSR impact update.
