# Campaign Ads Admin QA and Diagnostics Polish

## Scope

This phase polishes the admin-side Campaign Ads review and diagnostics experience after the live surface render hardening phase.

No SQL is required.

## Covered pages

- `/admin/ad-review.php`
- `/admin/ad-placements.php`
- `/admin/ad-diagnostics.php`
- `/admin/ad-performance.php`
- `/merchant-ad-performance.php` remains compatible with the shared reporting/dashboard assets.

## Changes

- Improves the admin review queue with clearer campaign rows, creative thumbnails, merchant/objective/placement metadata, and review-ready issue pills.
- Improves the admin review detail panel with a sponsored preview, image/CTA/destination/placement checks, and safer review-action busy states.
- Extends read-only diagnostics to flag approved or active campaigns with missing headline, missing image, missing CTA label, missing destination URL, or no active placement assignment.
- Adds a diagnostics panel for creative and placement gaps.
- Adds diagnostics details for assignment-level creative readiness and render sample metadata.
- Adds shared admin QA styling for review rows, diagnostics gap cards, issue pills, and responsive admin QA layouts.
- Adds admin navigation polish between review, placement controls, diagnostics, and performance pages.

## Boundaries

- No SQL migration was added.
- No auction, bidding, billing, CPC/CPM, payout, or external advertiser marketplace behavior was added.
- No wallet recommendation or separate Campaign Drops map was added.
- No wallet, claim, redemption, or payout business-rule writes were added.
- Diagnostics remain read-only.

## Manual QA checklist

1. Open `/admin/ad-review.php` and confirm the review queue loads with thumbnails, merchant/objective/placement metadata, and issue pills.
2. Select a campaign and confirm the detail panel shows the sponsored preview plus image, CTA, destination, and placement checks.
3. Approve, pause, reactivate, and reject from existing review controls only where appropriate for the selected test campaign.
4. Open `/admin/ad-diagnostics.php` and confirm the summary KPIs load, including creative issues and campaign gaps.
5. Confirm diagnostics flag approved/active campaigns missing creative headline, image URL, CTA label, destination URL, or active placement assignment.
6. Confirm placement cards still show render samples, events, issues, and assignments.
7. Open `/admin/ad-placements.php` and confirm the placement board still loads and saves existing placement settings.
8. Open `/admin/ad-performance.php` and `/merchant-ad-performance.php` and confirm existing performance dashboards still load.
9. Confirm no new SQL is required and no billing/auction controls appear.
