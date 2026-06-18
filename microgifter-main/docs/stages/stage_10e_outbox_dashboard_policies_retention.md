# Stage 10E — Outbox Workers, Merchant Dashboards, Configurable Rate Policies, and Retention Jobs

## Delivered

- CLI outbox worker with row locking, retry backoff, delivery state, and dead-letter transition.
- Merchant dashboard API with attempt totals, approval rate, result mix, location activity, actor counts, and escalation summaries.
- Admin rate-policy API with global, merchant, and location overrides for actor, merchant, location, gift, and network scopes.
- Configured merchant claim execution that resolves the most specific active policy before falling back to Stage 10D defaults.
- Retention job that expires stale rate buckets, removes old delivered outbox messages, and anonymizes old attempt fingerprints and metadata.
- Persistent retention-run audit records.

## APIs and jobs

- `GET /api/merchant/microgift-claim-dashboard.php`
- `GET|POST /api/admin/microgift-rate-policies.php`
- `php scripts/stage10e_outbox_worker.php [batch-size]`
- `php scripts/stage10e_retention.php [attempt-days] [rate-days] [outbox-days]`

## Policy precedence

The resolver chooses active policies by scope and then prefers location-specific rules, merchant-specific rules, and finally global rules. Lower priority values win within the same specificity. Built-in Stage 10D limits remain the final fallback.

## Worker behavior

The outbox worker claims eligible rows with `FOR UPDATE SKIP LOCKED`, marks them Processing, emits the registered operational event, and marks delivery complete. Failures use bounded exponential retry and transition to Dead after the maximum attempts.

## Retention behavior

Retention does not delete canonical claims, redemptions, or audit outcomes. It removes expired operational buckets and delivered queue rows, while anonymizing old network/client fingerprints and optional attempt metadata.
