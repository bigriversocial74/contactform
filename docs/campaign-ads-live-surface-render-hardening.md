# Campaign Ads Live Surface QA and Render Hardening

## Scope

This pass hardens the shared Campaign Ads renderer used by active sponsored surfaces.

No SQL is required.

## Active surfaces covered

- `/feed.php` sidebar sponsored card
- `/merchant-agent-chat.php` desktop sidebar sponsored card through `includes/merchant-agent-chat-view.php`
- `/world-canvas.php` sponsored World Canvas pins and Target Zone drops
- `/inbox.php` inbox recommendation through `includes/gift-action-center.php`
- Isolated claim success modal recommendation through `assets/js/gift-action-center-claim-modal.js`

## Changes

- Adds safer placement API response metadata for empty/inactive/error states.
- Filters malformed render items that do not include a public campaign id or headline.
- Normalizes sponsored item data in the browser before rendering.
- Adds card fallbacks for missing image, description, CTA, merchant name, destination URL, placement key, and tracking payload.
- Hides empty sponsored placement containers without leaving visual gaps.
- Adds broken-image handling for campaign images and merchant avatars.
- Clamps sponsored map pin and zone coordinates to safe percentages.
- Preserves attribution tracking behavior for clicks, saves, and impressions.

## Boundaries

- No wallet recommendation was added.
- No separate Campaign Drops map was created.
- No auction, bidding, billing, CPC/CPM, payout, or external advertiser marketplace behavior was added.
- No wallet, claim, redemption, or payout business-rule writes were added.

## Manual QA checklist

1. Open `/feed.php` and confirm the sidebar ad either renders cleanly or leaves no blank gap when empty.
2. Open `/merchant-agent-chat.php` on desktop and confirm the sidebar sponsored card renders without crowding the chat rail.
3. Open `/world-canvas.php` and confirm World Canvas sponsored pins and Target Zone drops render on top of the map and remain clickable.
4. Open `/inbox.php` and confirm the inbox recommendation renders above the gift list or hides cleanly when empty.
5. Trigger the isolated claim success modal and confirm the claim-success recommendation renders or hides cleanly.
6. Test an approved campaign with an uploaded creative image and confirm the image renders on Feed/sidebar, Inbox, Claim Success, and preview contexts.
7. Temporarily test a missing/broken image URL and confirm the sponsored placeholder appears instead of a broken image icon.
8. Confirm click, save, and impression tracking calls still fire without blocking navigation.
9. Confirm `/admin/ad-diagnostics.php`, `/admin/ad-placements.php`, `/merchant-ad-performance.php`, and `/admin/ad-performance.php` still load.
