# Stage 13B — Initial Subscription Funding and Activation Reconciliation

Stage 13B corrects the Stage 13 activation boundary without introducing a second payment, wallet, ledger, webhook, target-resolution, or communications authority.

## Corrected lifecycle

- Existing subscriptions remain compatible through `initial_payment_required = 0`.
- New subscriptions are created with `initial_payment_required = 1`.
- Non-trial subscriptions begin in `pending_payment` and create the first payment attempt immediately.
- Trial subscriptions remain `trialing` until trial expiration, when their first payment attempt becomes due.
- Wallet funding activates atomically inside the subscription transaction after the canonical Stage 12 tip posts.
- Stripe funding activates only after the canonical signed webhook confirms success.
- Initial payment success sets `funded_at`, sets `activated_at`, clears `initial_payment_required`, and starts the first paid billing period.
- Renewal success advances from the existing paid period end and does not reuse the activation path.
- Duplicate successful webhook events return an idempotent result and do not advance the subscription twice.
- Initial payment failure uses the existing Stage 13 retry, past-due, pause, and notification workflow.

## Authority boundaries

Stage 13B continues to use:

- `mg_tip_create()` for wallet and Stripe payment creation;
- `mg_tip_finalize_stripe()` for provider settlement;
- the Stage 7 ledger through the Stage 12 tip service;
- the existing subscription events and communications foundations;
- the existing payment webhook event store and signature verification.

Stage 13B does not write directly to wallet balances or ledger entries.

## Compatibility

The new `initial_payment_required` column defaults to `0`. Rows created before Stage 13B therefore retain their existing funded behavior. Only subscriptions created by the corrected Stage 13B service explicitly set the flag to `1`.
