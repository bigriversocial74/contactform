# Stage 13 — Subscriptions and Monetization

Stage 13 adds recurring support plans and subscription lifecycle management on top of Stage 12 Universal Tips. It does not introduce a second payment, wallet, ledger, target-resolution, webhook, or communications authority.

## Core model

- creators and merchants publish recurring support plans for an existing Stage 12 target;
- subscribers choose wallet or Stripe funding as defined by the plan;
- every billing cycle creates one idempotent Stage 12 tip;
- successful wallet renewals post immediately through the Stage 7 ledger;
- successful Stripe renewals finalize through the existing signed payment webhook authority;
- subscription and attempt events preserve lifecycle history.

## Lifecycle

`trialing → active → past_due → paused`

User-controlled states:

- pause;
- resume;
- cancel immediately;
- cancel at the end of the current paid period.

Terminal states:

- canceled;
- expired.

## Dunning

Failed renewal attempts increment the subscription retry counter. The default retry policy allows three attempts and schedules progressively delayed retries. After the configured maximum, the subscription is paused and billing stops until the subscriber resumes it.

The retry maximum is configured through `MG_SUBSCRIPTION_MAX_RETRIES` and is bounded between one and ten attempts.

## Canonical authorities preserved

- Stage 12 resolves monetization targets and creates renewal tips;
- Stage 7 posts balanced wallet and processor-clearing ledger groups;
- Stage 5I verifies payment webhook signatures and records provider events;
- Stage 5H sends renewal and dunning alerts;
- no subscription endpoint writes raw ledger entries or wallet balances;
- subscriptions never mutate Microgift, claim, redemption, PPPM, product, post, or ownership state.

## APIs and jobs

- `GET|POST /api/subscriptions/plans.php`
- `POST /api/subscriptions/create.php`
- `GET /api/subscriptions/index.php`
- `POST /api/subscriptions/manage.php`
- `POST /api/subscriptions/payment-webhook.php?provider=stripe`
- `php scripts/process_subscriptions.php [limit]`

## Integrity controls

- subscriber-scoped subscription idempotency;
- cycle- and attempt-scoped renewal idempotency;
- self-subscription prevention;
- backend target ownership enforcement for plan publishers;
- row locking for plan enrollment, lifecycle mutations, renewal scheduling, and payment settlement;
- provider-event idempotency through `payment_webhook_events`;
- immutable subscription event history;
- explicit retry and terminal cancellation semantics.

## Deferred to Stage 14

Promotional campaigns, referral attribution, affiliate rewards, coupon strategy, audience segmentation, and campaign analytics remain outside Stage 13.
