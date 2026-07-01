# Campaign Ads Inbox and Claim Success Surfaces

This stage activates two high-intent recommendation surfaces without using `wallet.php`.

## Scope

Included surfaces:

- `inbox_recommendation`
- `claim_success_recommendation`

Excluded surface:

- `wallet_recommendation`

`wallet.php` is intentionally left alone because it is not currently treated as a user-facing recommendation page.

## Inbox recommendation

`/inbox.php` now loads the existing sponsored ad assets:

- `/assets/css/sponsored-campaign-card.css`
- `/assets/js/sponsored-campaign-card.js`

`includes/gift-action-center.php` renders the inbox recommendation slot only when the active Gift Action Center folder is `inbox`:

```txt
inbox_recommendation
```

The slot uses the existing `/api/ads/render-placement.php` API, admin placement controls, impression tracking, click tracking, save tracking, and direct attribution storage.

## Claim success recommendation

`assets/js/gift-action-center-claim-modal.js` now renders a sponsored recommendation slot inside the isolated claim modal success step:

```txt
claim_success_recommendation
```

Because the claim modal content is dynamic, the success renderer explicitly calls:

```js
Microgifter.renderSponsoredPlacements(modal)
```

when the sponsored renderer is available.

## SQL required

No SQL required.

This uses placements already seeded by Campaign Ads Manager Phase 1.

## Test path

1. Open `/admin/ad-placements.php`.
2. Assign an approved/demo ad to `inbox_recommendation`.
3. Assign an approved/demo ad to `claim_success_recommendation`.
4. Open `/inbox.php`.
5. Confirm the inbox recommendation slot renders above the gift list.
6. Complete a claim through the isolated claim modal.
7. Confirm the claim success recommendation renders on the success step.
8. Open `/merchant-ad-performance.php` or `/admin/ad-performance.php`.
9. Confirm impressions/clicks from these surfaces are tracked.

## Notes

No billing, payout, wallet balance, redemption, claim, or attribution business-rule changes are included in this stage.
