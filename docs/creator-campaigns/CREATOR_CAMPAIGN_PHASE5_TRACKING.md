# Creator Campaign Phase 5 — Tracking and Attribution

Phase 5 adds first-party Creator Campaign tracking and attribution on top of accepted participation agreements and verified content delivery.

## Delivered

- Creator-specific share sources with random tracking codes and internal Microgifter destinations.
- Creator and merchant source management with channel, platform, attribution model, and conversion-window controls.
- Public redirect tracking through `/creator-campaign-track.php`.
- Same-origin browser events for landing views and engagement.
- An internal conversion recording service for lead, checkout, purchase, claim, and redemption events.
- Privacy-safe session, visitor, and request hashes. Raw IP addresses and user-agent strings are not stored.
- Immutable event facts with accepted, duplicate, suspect, and invalidated validation states.
- Replay and velocity risk controls.
- One canonical attribution decision per conversion event.
- First-touch, last-touch, direct, and manual attribution models.
- Automatic attribution, manual override, invalidation, and reprocessing.
- Append-only attribution decision audit history.
- Merchant and Creator performance workspaces.

## Routes

Merchant:

- `/merchant-creator-tracking.php`
- `/api/merchant/creator-campaign-tracking.php`

Creator:

- `/creator-campaign-tracking.php`
- `/api/creator/campaign-tracking.php`

Public/internal:

- `/creator-campaign-track.php?c={tracking_code}`
- `/api/public/creator-campaign-events.php`
- `mg_creator_campaign_tracking_record_conversion(PDO $pdo, array $input)`

## Schema

Migration: `database/20260722_creator_campaign_tracking_attribution_v5.sql`

Tables:

- `creator_campaign_tracking_sources`
- `creator_campaign_tracking_events`
- `creator_campaign_attributions`
- `creator_campaign_attribution_events`

## Integrity rules

1. Tracking sources belong to one active Creator Campaign participant with an accepted agreement.
2. Share destinations must remain internal Microgifter paths.
3. Tracking codes are random and globally unique.
4. Event keys are unique within each campaign and replay-safe.
5. Raw IP addresses, user-agent strings, visitor identifiers, and session identifiers are not stored.
6. Event facts and timestamps remain immutable; only their validation state may be invalidated.
7. Duplicate and high-velocity activity is retained for audit but excluded from automatic attribution.
8. One canonical attribution record exists per conversion event and references the touch event used for the decision when available.
9. Every override, invalidation, and reprocessing action creates an append-only attribution audit record.
10. Compensation, earnings, budget ledger, payouts, disputes, and MCP execution remain outside Phase 5.

## Conversion integration

Trusted application services should call `mg_creator_campaign_tracking_record_conversion()` with:

- `event_type`: `lead`, `checkout`, `purchase`, `claim`, or `redemption`
- `event_key`: a stable idempotency key owned by the source transaction
- `tracking_code` when the conversion directly carries a Creator source
- or `campaign_id` plus `session_key` for session-based attribution
- optional privacy-safe metadata that does not contain payment credentials or raw personal identifiers

Public clients cannot submit purchase, claim, redemption, or checkout events.

## Deployment

1. Merge the approved Phase 5 PR into `integration-from-repair-20260628`.
2. Deploy the updated integration branch.
3. Import `database/20260722_creator_campaign_tracking_attribution_v5.sql` once.
4. Open the merchant tracking workspace and create a source for an active participant.
5. Verify redirect, landing-view, engagement, conversion, attribution, invalidation, and override flows.
