# Feed Loading Regression Hotfix

## Issue
The feed shell, sidebar, and header loaded, but the main feed stayed on three empty loading cards.

## Cause
`assets/js/social-feed.js` expects a `[data-owner-filter]` element and attaches a `change` listener during boot. The current `feed.php` markup did not include that hook, so the script could throw before `configureView()` and `loadFeed(false)` ran.

## Fix
`feed.php` now includes a hidden owner-status filter hook with `[data-owner-filter-wrap]` and `[data-owner-filter]`. This preserves the existing JS contract without changing feed card rendering or greeting-card feed compatibility.

## SQL
None required.
