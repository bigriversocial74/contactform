# Stage API-5 — Developer Webhooks and Lifecycle Callbacks

Developer apps can receive lifecycle callbacks through an outbox-backed webhook system.

## Schema

- `developer_webhook_events` stores queued lifecycle events.
- `developer_webhook_attempts` stores delivery attempts and HTTP results.

Run migration:

```bash
mysql < database/stage_public_distribution_api_webhooks.sql
```

## Worker entry points

- Manual/API: `POST /api/distribution/webhook-worker.php`
- Queue status: `GET /api/distribution/webhook-worker.php`
- CLI/cron: `php scripts/run_distribution_webhook_worker.php --limit=25`

## Merchant test endpoint

- `POST /api/merchant/developer-webhook-test.php`

Payload:

```json
{
  "app_id": "developer-app-public-id"
}
```

## Initial lifecycle events

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
```

## Retry behavior

The worker retries `queued` and `failed` events when `next_attempt_at` is due. Successful 2xx responses mark events `delivered`. Failed responses are retried with exponential backoff until `max_attempts`, then marked `dead_letter`.

## Notes

- Events without an app webhook URL are marked `skipped`.
- Signing currently uses the app webhook secret hash stored on `merchant_developer_apps` as signing material. A future merchant-facing secret rotation/display flow should return a webhook signing secret once, similar to API credential creation.
