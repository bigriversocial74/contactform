# V1 Stage F — Stripe Payments and Connect

Stage F moves the focused V1 lifecycle from the sandbox adapter to a Stripe-ready payment boundary while preserving the existing order, ledger, issuance, Action Center, and redemption authorities.

## Canonical flow

1. An administrator configures Stripe test or live mode and the Microgifter platform-share policy.
2. A merchant completes Stripe Connect Express onboarding and must have both charges and payouts enabled.
3. Checkout snapshots the platform share. The default is 1,500 basis points, or 15%.
4. The customer pays the existing gift price. The platform share is included in that amount rather than added as a surcharge.
5. Stripe Checkout creates a destination charge with an application fee and the merchant connected account as the destination.
6. A signed Stripe webhook is the payment-confirmation authority.
7. The webhook validates amount, currency, internal metadata, event identity, and the fee snapshot before recording payment.
8. The canonical paid-order workflow posts the balanced ledger split, finalizes the receipt, issues PPPM items and Microgifts, projects the Action Center, and sends confirmations.

## Platform configuration

The application supports environment-managed credentials and an encrypted server credential store. Environment values take precedence.

```text
MG_PAYMENT_PROVIDER=stripe
MG_PAYMENT_MODE=test
MG_APP_URL=https://your-domain.example
MG_STRIPE_PUBLISHABLE_KEY_TEST=pk_test_...
MG_STRIPE_SECRET_KEY_TEST=sk_test_...
MG_STRIPE_WEBHOOK_SECRET_TEST=whsec_...
MG_STRIPE_CONNECT_CLIENT_ID_TEST=ca_...        # optional for Express
MG_PLATFORM_FEE_BPS=1500
MG_PLATFORM_FIXED_FEE_CENTS=0
```

Use the corresponding `_LIVE` variables for live mode. Database-stored secret and webhook values require a 32-byte `MG_PAYMENT_CREDENTIAL_KEY`, supplied directly or as base64.

The administrative readiness page is `/admin-payments.php`.

## Merchant Connect

Merchants manage onboarding in `/merchant-payments.php`. The platform creates or reuses one Stripe Express account per merchant and mode. Checkout remains blocked until the account is active with charges and payouts enabled.

## Webhook

Configure Stripe to send payment events to:

```text
/api/payments/webhook.php?provider=stripe
```

The endpoint verifies the raw request body against the `Stripe-Signature` header and the mode-specific webhook secret. Provider event IDs are idempotent and conflicting signed replays are rejected.

Handled checkout events include:

- `checkout.session.completed` when payment status is paid
- `checkout.session.async_payment_succeeded`
- `payment_intent.succeeded`
- corresponding payment-failure events

## Test adapter

CI and local behavior tests may use:

```text
MG_STRIPE_TEST_STUB=1
```

The stub is available only in test mode. It creates deterministic Stripe-shaped Checkout, PaymentIntent, Connect account, and account-link responses without network access or real charges.

## Live readiness

Live mode requires:

- enabled Stripe live configuration
- correctly prefixed live publishable and secret keys
- webhook signing secret
- HTTPS `MG_APP_URL`
- PHP cURL
- environment-managed secrets or an encryption key for stored secrets
- a valid platform-fee policy
- each selling merchant to have a ready connected account

Microgifter never stores raw card numbers or card-verification data.
