# Stage API-5 — Developer Webhooks and Lifecycle Callbacks

Developer apps can receive lifecycle callbacks through an outbox-backed webhook system.

## What is now programmed

The Developer API workspace includes a webhook management surface backed by:

- `GET /api/merchant/developer-webhooks.php`
- `POST /api/merchant/developer-webhooks.php`
- `POST /api/merchant/developer-webhook-test.php` compatibility/test queue endpoint
- `POST /api/distribution/webhook-worker.php`
- `GET /api/distribution/webhook-worker.php`
- `php scripts/run_distribution_webhook_worker.php --limit=25`

The merchant workspace can now:

1. View developer apps and webhook status.
2. Save a webhook URL.
3. Generate a reveal-once webhook signing secret when needed.
4. Rotate the webhook signing secret.
5. Send a `webhook.test` event.
6. View recent webhook events.
7. View recent delivery attempts and HTTP status.

## Schema

- `developer_webhook_events` stores queued lifecycle events.
- `developer_webhook_attempts` stores delivery attempts and HTTP results.

Run migration:

```bash
mysql < database/stage_public_distribution_api_webhooks.sql
```

## Merchant webhook management API

### Read configuration and recent delivery history

```http
GET /api/merchant/developer-webhooks.php
```

Returns:

```json
{
  "ok": true,
  "apps": [],
  "events": [],
  "attempts": [],
  "signature_base": "<timestamp>.<raw request body>",
  "signature_version": "v1"
}
```

### Save webhook URL

```http
POST /api/merchant/developer-webhooks.php
```

Payload:

```json
{
  "action": "save_webhook",
  "app_id": "developer-app-public-id",
  "webhook_url": "https://example.app/microgifter/webhook"
}
```

If the app does not already have a signing secret, the response includes a reveal-once `webhook_secret`.

### Rotate signing secret

```json
{
  "action": "rotate_secret",
  "app_id": "developer-app-public-id"
}
```

The new signing secret is shown once. The merchant/developer must copy it into the receiving app backend immediately.

### Send test delivery

```json
{
  "action": "send_test",
  "app_id": "developer-app-public-id"
}
```

This queues a `webhook.test` event and attempts immediate delivery using the same delivery function as the webhook worker.

## Worker entry points

- Manual/API: `POST /api/distribution/webhook-worker.php`
- Queue status: `GET /api/distribution/webhook-worker.php`
- CLI/cron: `php scripts/run_distribution_webhook_worker.php --limit=25`

## Initial lifecycle events

- `account_link.started`
- `account_link.approved`
- `account_link.cancelled`
- `account_link.expired`
- `reward.queued`
- `reward.issued`
- `reward.delivered`
- `reward.failed`
- `webhook.test`

## Delivery headers

Webhook requests include:

```text
Content-Type: application/json
User-Agent: Microgifter-Webhooks/1.0
X-Microgifter-Event: reward.delivered
X-Microgifter-Delivery: delivery-id
X-Microgifter-Timestamp: unix-timestamp
X-Microgifter-Signature: sha256=...
X-Microgifter-Signature-Version: v1
```

## Signature verification

Signature base string:

```text
<timestamp>.<raw request body>
```

Expected signature:

```php
$expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $webhookSecret);
```

Reject requests when:

- timestamp is missing
- signature is missing
- timestamp is outside the receiver tolerance window
- computed signature does not match `X-Microgifter-Signature`

## Retry behavior

The worker retries `queued` and `failed` events when `next_attempt_at` is due. Successful 2xx responses mark events `delivered`. Failed responses are retried with exponential backoff until `max_attempts`, then marked `dead_letter`.

## Environment URL policy

- Test apps may use `http` or `https` webhook URLs.
- Live apps must use `https`.
- Live apps may not use literal private or localhost addresses.

## Notes

- Events without an app webhook URL are marked `skipped`.
- Webhook signing secrets are reveal-once values.
- The `webhook_secret_hash` column currently stores the signing material used by the delivery worker. The name is legacy and should be renamed in a future migration to avoid confusion.
