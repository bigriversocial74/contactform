# Card Payment Deep Dive — 2026-06-30

## Current card-payment architecture

Microgifter card checkout is Stripe-hosted checkout with Stripe Connect destination charges.

The customer cart creates this chain:

1. `api/commerce/checkout-draft.php`
2. `api/commerce/orders.php`
3. `api/payments/order-checkout-session.php`
4. `api/payments/_checkout_session.php`
5. `api/payments/_stripe.php`
6. Stripe hosted checkout
7. `api/payments/webhook.php`
8. `api/payments/_webhook.php`
9. `api/payments/_capture.php`
10. `api/payments/_fulfillment.php`

## Why card checkout is hidden right now

`api/payments/checkout-options.php` only exposes card checkout when Stripe readiness passes.

Stripe readiness currently requires:

- Runtime provider is `stripe` through `MG_PAYMENT_PROVIDER=stripe`.
- Runtime mode matches the selected admin mode through `MG_PAYMENT_MODE`.
- Stripe provider is enabled in platform credentials.
- Publishable key matches the active mode prefix:
  - test: `pk_test_`
  - live: `pk_live_`
- Secret key matches the active mode prefix:
  - test: `sk_test_`
  - live: `sk_live_`
- Webhook signing secret starts with `whsec_`.
- `MG_APP_URL` is configured.
- Production/live mode requires HTTPS `MG_APP_URL`.
- PHP cURL is available.
- Database secret encryption or environment secret source is ready.
- Platform fee policy is valid.

## Additional blocker after platform readiness

Even after platform Stripe readiness passes, checkout still requires the selling merchant to have a ready Stripe Connect account:

- `payment_provider_accounts.status = active`
- `charges_enabled = 1`
- `payouts_enabled = 1`
- `provider_account_reference` is present

This is enforced by `mg_payment_assert_checkout_ready()` before hosted checkout is created.

Because `mg_stripe_checkout_session()` uses `payment_intent_data[transfer_data][destination]`, card checkout cannot work without a valid connected merchant account.

## Webhook path

Successful Stripe payment events are handled by:

- `checkout.session.completed`
- `checkout.session.async_payment_succeeded`
- `payment_intent.succeeded`
- `payment.succeeded`

The webhook must include enough metadata or provider references to match the internal order/payment intent/session. If matched, `mg_finance_record_paid_order()` records payment and triggers fulfillment.

## Practical launch checklist

For test card checkout:

1. Set `MG_PAYMENT_PROVIDER=stripe`.
2. Set `MG_PAYMENT_MODE=test`.
3. Save Stripe test keys:
   - `pk_test_...`
   - `sk_test_...`
   - `whsec_...`
4. Set `MG_APP_URL` to the deployed app URL.
5. Confirm PHP cURL is enabled on the host.
6. Create or sync the merchant Stripe Connect account.
7. Confirm that connected account is active with charges and payouts enabled.
8. Use Stripe CLI or Dashboard webhook forwarding to send events to `/api/payments/webhook.php?provider=stripe`.
9. Run one test card purchase and verify:
   - order becomes paid
   - checkout session becomes completed
   - receipt is present
   - purchased gift is issued to the buyer inbox

## Current recommended behavior

Until Stripe readiness and merchant Connect readiness both pass, card checkout should remain hidden and cash checkout should remain the active workaround.
