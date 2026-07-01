# Campaign Ads Diagnostics Panel

This stage adds a read-only admin QA panel for Campaign Ads delivery.

## Page

```txt
/admin/ad-diagnostics.php
```

## API

```txt
/api/ads/admin-diagnostics.php
```

## Purpose

The diagnostics panel answers the operational question:

```txt
Why is this ad placement not showing ads?
```

It checks each placement for:

- schema readiness
- seeded placement presence
- active/inactive placement status
- max ads configuration
- approved/active campaign assignments
- render output using the same placement renderer used by public surfaces
- impression/click/save/claim/redeem counts
- last tracked event times
- direct-attribution column readiness
- optional wallet value attribution readiness

## Read-only scope

This panel does not change:

- placement settings
- campaign status
- campaign assignments
- billing
- payout logic
- wallet state
- claim logic
- redemption logic

Placement edits remain in:

```txt
/admin/ad-placements.php
```

## Sidebar

The admin sidebar now includes:

```txt
Ad diagnostics -> /admin/ad-diagnostics.php
```

Visible to the same Campaign Ads admin permission group used by Ad review and Ad placements.

## No SQL required

No SQL migration is required.

The panel reads existing Campaign Ads tables and optional wallet/value attribution tables when present.

## Test path

1. Open `/admin/ad-diagnostics.php` as an ads admin.
2. Confirm schema/table checks load.
3. Confirm all seeded placements appear.
4. Open `/admin/ad-placements.php` and assign approved/demo ads to target placements.
5. Return to `/admin/ad-diagnostics.php`.
6. Confirm the target placements show active assignments.
7. Open public surfaces such as `/feed.php`, `/merchant-agent-chat.php`, `/world-canvas.php`, `/inbox.php`, or the claim success modal.
8. Return to diagnostics and confirm impressions/clicks update.

## Notes

The wallet recommendation placement remains flagged as not user-facing until a real user-facing wallet surface is created later.

The campaign drops map placement remains flagged as reserved because the real map surface is currently `/world-canvas.php`.
