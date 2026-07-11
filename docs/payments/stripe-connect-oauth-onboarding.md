# Stripe Connect OAuth onboarding

## Purpose

`/merchant-payments.php` now uses Stripe Standard OAuth when a merchant chooses **Connect or create Stripe account**. Stripe handles account login, account selection, new-account creation, authorization, identity requirements, and Stripe credentials. Microgifter stores the connected Stripe account ID and account readiness only.

The legacy Express Account Link helper remains available for existing integrations and regression coverage, but the merchant UI starts the official Standard OAuth connection flow.

## Required deployment configuration

1. Import `database/stage_v1g_stripe_connect_oauth.sql`.
2. Configure the active Stripe mode in `/admin-payments.php`.
3. Save the matching Stripe values for that mode:
   - `pk_test_...` or `pk_live_...`
   - a standard `sk_test_...` or `sk_live_...` platform secret key
   - `whsec_...`
   - the mode-matching Connect OAuth client ID beginning with `ca_`
4. Set `MG_PAYMENT_PROVIDER=stripe`.
5. Set `MG_PAYMENT_MODE=test` or `MG_PAYMENT_MODE=live`.
6. Set `MG_APP_URL` to the deployed Microgifter origin. Live mode requires HTTPS.
7. In Stripe Connect OAuth settings, enable OAuth onboarding and register this exact redirect URL:

   `https://YOUR-MICROGIFTER-HOST/api/merchant/stripe-connect-callback.php`

A restricted `rk_` key can still be used by supported payment operations when properly permissioned, but Stripe OAuth token exchange requires the platform standard `sk_` secret key.

## Admin credential persistence

The admin payment settings API writes the selected Test or Live record, reads the exact `payment_platform_credentials` row back inside the same transaction, and verifies every submitted public field, fee, enabled state, and newly submitted decrypted secret before committing. An unverified write is rolled back rather than reported as successful.

After saving, the browser performs another GET read-back and displays the database `updated_at` value plus saved credential hints. Stripe API keys and webhook secrets intentionally remain blank after reload because those inputs are write-only.

The legacy browser mode value is cleared so saved Live credentials cannot appear missing because an old local-storage setting silently reopened Test. The selected mode is preserved in the page URL, and the API reports when server environment variables override database values.

## Merchant flow

1. Merchant opens `/merchant-payments.php` and enables Stripe payments.
2. Merchant clicks **Connect or create Stripe account**.
3. Microgifter creates a random, hashed, expiring OAuth state record.
4. Merchant is redirected to Stripe.
5. Stripe lets the merchant sign in and select an existing eligible account, or create a new Stripe account.
6. Stripe redirects to `/api/merchant/stripe-connect-callback.php` with a one-time authorization code and the original state.
7. Microgifter consumes the state once, exchanges the code server-side, stores only the connected `acct_...` ID, retrieves account readiness, and redirects back to Merchant Payments.
8. Merchant can refresh readiness, open the Stripe Dashboard, or disconnect an OAuth-connected Standard account.

## Security boundaries

- Merchant Stripe passwords are never received or stored by Microgifter.
- OAuth state values are random, stored only as SHA-256 hashes, expire, and are single-use.
- Authorization codes are exchanged server-side and are never stored.
- Deprecated OAuth access and refresh tokens are not persisted.
- A Stripe account cannot be connected to two different Microgifter merchant users in the same mode.
- Connection actions require `merchant.payments.manage`.
- Read-only status requires `merchant.payments.view`.
- Checkout remains fail-closed until the account is active with charges and payouts enabled.
- Signed `account.updated` and `account.application.deauthorized` events update connection readiness.

## Staging QA

- Save Live Stripe configuration, reload the page, and confirm the Live database timestamp and masked saved hints remain visible.
- Verify the secret and webhook password inputs are blank after reload while their saved hints remain present.
- Verify the Connect button is disabled with clear blockers when platform configuration is incomplete.
- Verify Test and Live client IDs stay isolated by mode.
- Connect an existing Stripe account.
- Create a new Stripe account from the Stripe authorization screen.
- Deny authorization and confirm no connection changes occur.
- Replay the callback and confirm the state is rejected.
- Let a state expire and confirm it is rejected.
- Confirm a connected account cannot be attached to a second Microgifter merchant.
- Complete Stripe requirements and use **Refresh Stripe status**.
- Verify charges and payouts readiness display correctly.
- Disconnect and confirm Stripe checkout becomes unavailable.
- Send signed `account.updated` and `account.application.deauthorized` webhook events.
- Run a real test-mode checkout only after the connected account reports ready.
