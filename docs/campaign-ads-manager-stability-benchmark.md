# Campaign Ads Manager Stability Benchmark

Source of truth: PR #594 first post-redesign baseline, restored by PR #657.

This benchmark must pass before adding any Campaign Ads Manager feature back to `/merchant-ad-manager.php`.

## Baseline identity

- Stable page: `/merchant-ad-manager.php`
- Stable design baseline: PR #594, `Redesign merchant Campaign Ads manager and add health alerts`
- Rollback restore PR: PR #657, `Rollback Campaign Ads manager to first redesign baseline`
- Active deploy branch: `integration-from-repair-20260628`

## Baseline file contract

The stable manager page path should include:

- `merchant-ad-manager.php`
- `assets/js/merchant-ad-manager.js`
- `assets/css/merchant-ad-manager.css`
- `assets/js/sponsored-campaign-card.js`
- `assets/js/ad-health-alerts.js`
- `assets/css/sponsored-campaign-card.css`
- `assets/css/ad-health-alerts.css`

The stable manager page path should not include these later layers unless they are being reintroduced in a single scoped feature PR:

- product/reward picker UI
- grouped product picker behavior
- campaign image upload UI
- campaign cover-image/post preview layer
- lifecycle guard script
- page-load failsafe helper replacement
- broad JS fallback layers

## Required page-load checks

Run these checks after every feature PR before merge/deploy acceptance:

1. Open `/merchant-ad-manager.php` as a merchant.
2. Confirm the page does not blank, spin forever, or show a PHP fatal error.
3. Confirm the header renders: `Campaign Ads`.
4. Confirm the sidebar renders.
5. Confirm KPI cards render: Impressions, Clicks, Claims, Redemptions.
6. Confirm tab buttons render:
   - Create Campaign
   - Sponsored Preview
   - Merchant Campaigns
   - Analytics
7. Confirm the Create Campaign tab is active on first load.
8. Confirm the Sponsored Preview tab opens and displays the preview panel.
9. Confirm the Merchant Campaigns tab opens and does not break the page if the API has no campaigns.
10. Confirm the Analytics tab opens.
11. Confirm `+ New Campaign` returns to the Create Campaign tab.
12. Confirm the Campaign Ads health alert section either renders normally or remains harmless if no alerts exist.

## Required form checks

1. Confirm the campaign title field is editable.
2. Confirm headline, description, objective, budget type, budget amount, claim cap, redemption cap, zone, start, end, image URL, CTA, and destination fields are present.
3. Confirm placement checkboxes are present:
   - Feed card
   - Sidebar card
   - World Canvas pin
   - Target Zone drop
   - Inbox recommendation
   - Claim success
4. Confirm changing form fields updates the preview without a console-stopping JS error.
5. Confirm Save Campaign still posts through the existing Campaign Ads APIs.
6. Confirm Save Draft still posts through the existing Campaign Ads APIs.
7. Confirm Submit for Review still posts through the existing Campaign Ads APIs.

## Required browser console checks

A feature PR fails the benchmark if first page load produces any of these:

- uncaught JavaScript exception that stops `merchant-ad-manager.js`
- undefined function or undefined object error in the manager path
- repeating render loop or MutationObserver loop
- API failure that prevents the static page shell from rendering
- missing required DOM selector causing a crash

API errors may be acceptable only when they are contained and visible as status text without breaking the static page shell.

## Feature reintroduction rule

Add features back one at a time.

Each feature PR must:

1. Start from the latest `integration-from-repair-20260628`.
2. Touch the smallest number of files possible.
3. Avoid unrelated redesign or styling changes.
4. Avoid grouped behavior unless the feature is specifically the grouped behavior.
5. Include no SQL unless strictly required.
6. Explain the exact feature slice in the PR body.
7. Pass the page-load checks above before the next feature begins.

## Feature reintroduction order

Recommended order:

1. Plain product/reward picker only.
2. Creative image upload only.
3. Lifecycle guard only.
4. Product picker grouping only.
5. Campaign post / cover-image preview only.
6. Any broader fallback or hardening layer only after a specific failure is reproduced.

Do not combine two feature slices in one PR.
