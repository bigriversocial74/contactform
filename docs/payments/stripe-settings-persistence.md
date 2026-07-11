# Stripe settings persistence

`/admin-payments.php` stores Test and Live Stripe configuration in separate `payment_platform_credentials` rows.

## Secret fields

Stripe API keys and webhook signing secrets are write-only in the browser. After a successful save and page reload, those password fields intentionally remain blank. The page displays masked saved-value hints and the database `updated_at` time instead.

## Verified save contract

The payment settings API now:

1. writes the selected mode inside a database transaction;
2. reads the exact row back from `payment_platform_credentials`;
3. compares public fields, enabled state, fees, and any newly submitted decrypted secrets;
4. commits only when the read-back matches;
5. returns `persistence_verified=true` and a database storage snapshot.

No unverified update is reported as successful.

## Mode selection

The browser no longer trusts the legacy `mgAdminStripeConfigurationMode` local-storage value. The selected mode is represented in the page URL, while an unqualified page load asks the server to select the most recently relevant saved mode.

This prevents saved Live credentials from appearing missing because the browser silently reopened the Test record.

## Environment overrides

The admin form shows the database record separately from effective runtime values. When Stripe environment variables override database credentials, the page explicitly reports that condition.

## Required server setting

Saving a Stripe API key or webhook secret requires `MG_PAYMENT_CREDENTIAL_KEY`. When it is unavailable, the API rejects the save and states that nothing was stored. Public fields can still be saved only when secret fields are left blank.
