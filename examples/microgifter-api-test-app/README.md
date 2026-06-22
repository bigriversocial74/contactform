# Microgifter API test app

This is a tiny third-party-style PHP app for validating the Microgifter Public Distribution API documentation.

The app is intentionally simple. It has no framework, database, build step, or authentication. Its job is to prove that an outside developer can follow the docs and complete the API flow.

## What it tests

- API credential configuration.
- Program listing.
- Sandbox linked-account creation.
- Reward issue with `X-Idempotency-Key`.
- Reward status polling.
- Webhook receipt and signature verification.

## Setup

Copy the example config:

```bash
cp config.example.php config.php
```

Edit `config.php`:

```php
return [
    'base_url' => 'https://microgifter.com',
    'api_key' => 'mg_test_replace_with_server_side_key',
    'program_id' => 'dist_prog_replace_me',
    'template_id' => 'tmpl_replace_me',
    'webhook_secret' => 'replace_with_rotated_webhook_signing_value',
];
```

Start the app:

```bash
php -S 127.0.0.1:8088 -t examples/microgifter-api-test-app
```

Open:

```text
http://127.0.0.1:8088/index.php
```

For webhook testing, expose `webhook.php` with a public HTTPS tunnel and paste that URL into the merchant developer app webhook configuration.

## Expected flow

1. Click **List programs**.
2. Confirm the configured program/template values.
3. Click **Create sandbox linked account**.
4. Copy or keep the returned `linked_account_id`.
5. Click **Issue reward**.
6. Click **Check status**.
7. Trigger a webhook test from the Microgifter merchant developer app screen.
8. Confirm that `webhook-events.log` records the delivery.

## Documentation rule

Any missing value, unclear response, wrong endpoint path, or undocumented behavior discovered by this app is a documentation bug.
