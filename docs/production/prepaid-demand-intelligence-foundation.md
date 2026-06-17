# Prepaid Demand Intelligence and Commitment Foundation

## Product rule

Committed demand is created only from a Microgift linked to a paid commerce order. The demand owner is the commerce-order buyer, not the merchant that issued the Microgift.

Unpaid or promotional Microgifts do not become committed demand merely because they have a face value. This phase does not add a manual future-visit or gifting-intent form.

A future low-confidence “I might visit someday” feature remains separate from prepaid demand and is not included in this build.

## Canonical lifecycle

Every eligible paid Microgift reconciles to one Stage 15 `purchase_signal_records` row with `signal_type=committed_demand` and `confidence_score=1.0`.

Eligibility follows the canonical chain from `microgift_instances.commerce_order_item_id`, through `commerce_order_items.order_id`, to `commerce_orders.buyer_user_id` and the order payment status.

`microgift_demand_commitment_links` enforces one durable relationship between the Microgift instance and the Purchase Signal Record. Reconciliation updates that record instead of creating another demand or financial authority.

Lifecycle mapping:

- paid and issued → purchased, outstanding committed demand
- delivered or claim pending → sent, outstanding committed demand
- claimed or redeemable → claimed, outstanding near-term demand
- redeemed → realized demand linked to the canonical redemption
- cancelled or revoked → canceled demand
- refunded order or refund lifecycle action → refunded and canceled demand
- expired → expired demand
- replaced → replaced and canceled demand

Rescheduling changes the expected demand window on the same record. The expected date is derived from canonical Microgift or template metadata in this order: scheduled delivery, delivery date, occasion date, explicit demand window, delivered time, then issued time.

## Customer workspace

`/commitments.php` shows purchased and received Microgift commitments privately.

The workspace provides:

- upcoming, redeemed, canceled/refunded, and expired views
- committed and realized value summaries
- merchant, product, recipient assignment, and expected-date context
- loading, sign-in, empty, error, retry, and cursor states

Purchased commitments are resolved from the paid order buyer. Claiming a gift does not move the purchase signal away from that buyer, although the recipient can view the related commitment.

The API returns public identifiers and presentation fields only. Emails, internal numeric IDs, provider references, payment methods, and moderation data are not exposed.

## Merchant intelligence

`/intelligence.php` is rebuilt around four explicit categories:

- committed: prepaid and outstanding
- realized: canonically redeemed
- forecast: statistical projection and not prepaid
- recommendation: agent-generated suggestion that does not execute automatically

Merchant filters include horizon, location, product, and minimum privacy cohort. Daily, product, location, and entire filtered-scope results below the configured unique-purchaser threshold are suppressed. When a filtered scope is below the threshold, totals, lifecycle counts, snapshots, and recommendations are also suppressed.

No customer identity is returned by merchant intelligence APIs.

The dashboard preserves both demand authorities: Stage 15 supplies committed-demand snapshots and signals, while Stage 4F remains the forecast, intelligence-score, alert, and export authority.

## Agent handoff

Existing Stage 15 signals and Stage 17B orchestration records are shown with signal source, confidence, age, expiration, recommendation-only labeling, orchestration status, and approval-required state.

This phase does not introduce a second workflow record or bypass Stage 16 execution policy.

## Reconciliation

The system reconciles during authorized customer and merchant reads and through:

`php scripts/reconcile_prepaid_demand_commitments.php [limit] [updated-after]`

The processor is replay-safe because the Microgift link and PSR idempotency key are unique. It scans payment-backed Microgifts and previously linked commitments requiring lifecycle updates.

## Validation

Focused validation includes:

- complete ordered MySQL schema import
- paid-order commitment creation
- paid buyer ownership
- unpaid Microgift exclusion
- one signal per Microgift
- scheduled date-window derivation
- send, claim, redemption, refund, and expiration transitions
- replay and batch reconciliation
- privacy-safe customer projection
- filtered-scope cohort suppression
- Stage 4F overview preservation
- PHPUnit authority contracts
- customer and merchant Playwright workflows on desktop and mobile
- full repository PHPUnit and browser regression suites
