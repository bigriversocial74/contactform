# Unified POS Customer & Purchase Sync v1 — API Contracts

## 1. General API rules

- Merchant endpoints require authenticated active users and merchant integration permissions.
- Write endpoints require CSRF protection.
- Provider webhook endpoints do not use session CSRF; they require provider-specific signature verification against the unmodified raw body.
- All merchant-owned resources are resolved through `merchant_user_id` and connection ownership.
- Public resource IDs are used in URLs and payloads.
- Stable JSON response envelope:

```json
{
  "ok": true,
  "data": {},
  "message": "Operation completed."
}
```

Error envelope:

```json
{
  "ok": false,
  "message": "Stable public error message.",
  "error_code": "pos_sync_error_code",
  "details": {}
}
```

Never expose provider tokens, webhook secrets, raw exception traces, internal numeric IDs, or sensitive provider response bodies.

## 2. Permissions

Recommended permissions:

```text
merchant.integrations.view
merchant.integrations.manage
merchant.pos.view
merchant.pos.connect
merchant.pos.sync
merchant.pos.reconcile
merchant.pos.match_review
merchant.pos.retry
merchant.pos.disconnect
```

Selected-merchant entitlement and feature state are additional checks, not replacements for permissions.

## 3. Provider registry

### `GET /api/merchant/pos/providers.php`

Returns providers, rollout state, capabilities, and connection status.

Response:

```json
{
  "ok": true,
  "data": {
    "feature_state": "selected_merchants",
    "providers": [
      {
        "key": "square",
        "name": "Square",
        "status": "available",
        "capabilities": {
          "customer_import": true,
          "customer_webhooks": true,
          "purchase_webhooks": true,
          "refunds": true,
          "line_items": true,
          "custom_attributes": true
        },
        "connections": 1
      }
    ]
  }
}
```

## 4. Connections

### `GET /api/merchant/pos/connections.php`

Query:

```text
provider optional
status optional
```

Response item:

```json
{
  "id": "connection-uuid",
  "provider": "square",
  "external_merchant_id": "square-merchant-id",
  "account_label": "Downtown Restaurant",
  "environment": "production",
  "status": "active",
  "scopes": ["CUSTOMERS_READ", "PAYMENTS_READ", "ORDERS_READ"],
  "locations": {
    "enabled": 2,
    "available": 3
  },
  "initial_sync": {
    "status": "completed",
    "last_customer_sync_at": "2026-07-24T18:00:00Z"
  },
  "health": {
    "last_webhook_at": "2026-07-24T18:10:00Z",
    "last_processed_at": "2026-07-24T18:10:13Z",
    "queued": 0,
    "retryable": 0,
    "dead_letter": 0
  }
}
```

### `GET /api/merchant/pos/connection.php?id=<uuid>`

Returns connection detail, locations, settings, sync runs, health, and capabilities. Credential values are never returned.

### `PATCH /api/merchant/pos/connection.php`

Request:

```json
{
  "connection_id": "connection-uuid",
  "settings": {
    "customer_import_enabled": true,
    "custom_attributes_enabled": true,
    "custom_attribute_allowlist": ["loyalty_tier", "preferred_location"],
    "itemized_orders_enabled": true,
    "anonymous_analytics_enabled": true,
    "service_charges_in_ltv": false,
    "reward_event_bridge_enabled": false
  }
}
```

Validation:

- Connection belongs to merchant.
- Provider supports requested capability.
- Attribute keys are selected from provider-visible definitions, not arbitrary secrets.
- LTV changes affect future projection and explicit reconciliation; do not silently rewrite history without a recorded recalculation run.

### `POST /api/merchant/pos/disconnect.php`

Request:

```json
{
  "connection_id": "connection-uuid",
  "reason": "Merchant disconnected Square integration.",
  "idempotency_key": "client-uuid"
}
```

Behavior:

- Revoke provider access where supported.
- Delete local plaintext/decrypted credential material immediately.
- Clear encrypted credential ciphertext after successful/local revocation decision.
- Mark connection revoked.
- Stop new jobs and event publication.
- Preserve normalized CRM/customer/transaction history.
- Return existing disconnect receipt for duplicate requests.

## 5. Square OAuth

### `POST /api/merchant/pos/square/oauth-start.php`

Request:

```json
{
  "environment": "production",
  "return_url": "/merchant-integrations.php"
}
```

Response:

```json
{
  "ok": true,
  "data": {
    "authorization_url": "https://connect.squareup.com/oauth2/authorize?...",
    "expires_at": "2026-07-24T18:20:00Z"
  }
}
```

Requirements:

- Single-use signed/opaque OAuth state.
- State binds merchant, actor, environment, return URL, and expiry.
- Request minimum read-only scopes required by enabled v1 features.
- Do not request `CUSTOMERS_WRITE` in read-only v1.

### `GET /api/merchant/pos/square/oauth-callback.php`

Provider callback query includes authorization code, state, and provider error fields.

Success behavior:

1. Validate state and initiating merchant.
2. Exchange code for tokens.
3. Encrypt credentials.
4. Retrieve Square merchant/account and location details.
5. Insert/update connection and locations.
6. Validate granted scopes.
7. Redirect to merchant integration setup page.

Callback must not display provider secrets or raw provider errors.

## 6. Location management

### `GET /api/merchant/pos/locations.php?connection_id=<uuid>`

### `PATCH /api/merchant/pos/locations.php`

Request:

```json
{
  "connection_id": "connection-uuid",
  "locations": [
    {"id": "location-uuid-a", "enabled": true},
    {"id": "location-uuid-b", "enabled": false}
  ]
}
```

Rules:

- Transactions from disabled locations may still be durably recorded for audit, but business projection behavior follows the connection setting defined by implementation.
- Recommended v1: receive and record, mark ignored-by-location, do not post CRM/reward effects.

## 7. Customer import

### `POST /api/merchant/pos/customer-import.php`

Request:

```json
{
  "connection_id": "connection-uuid",
  "mode": "initial",
  "idempotency_key": "client-uuid"
}
```

Response:

```json
{
  "ok": true,
  "data": {
    "sync_run_id": "sync-run-uuid",
    "status": "queued"
  }
}
```

Rules:

- One active initial import per connection.
- Duplicate idempotency key returns the existing run.
- Import is processed asynchronously by the one-minute worker.
- Initial import includes customers only, not transaction history.

### `GET /api/merchant/pos/sync-run.php?id=<uuid>`

Response:

```json
{
  "id": "sync-run-uuid",
  "type": "initial_customer_import",
  "status": "running",
  "progress": {
    "records_seen": 650,
    "created": 210,
    "updated": 400,
    "matched": 520,
    "ambiguous": 8,
    "failed": 2
  },
  "started_at": "2026-07-24T18:00:00Z",
  "completed_at": null
}
```

### `POST /api/merchant/pos/customer-reconcile.php`

Starts a customer-directory reconciliation run, not real-time purchase polling.

## 8. Customer mappings and match review

### `GET /api/merchant/pos/customers.php`

Filters:

```text
connection_id
q
match_status
provider
cursor
limit
```

Result fields:

```json
{
  "id": "pos-customer-uuid",
  "provider": "square",
  "external_customer_id": "square-customer-id",
  "display_name": "Jamie Smith",
  "email": "jamie@example.com",
  "phone": "+16025550100",
  "match": {
    "status": "matched",
    "method": "email",
    "crm_contact_id": "crm-contact-uuid"
  },
  "consent": {
    "email": "unsubscribed",
    "sms": "unknown"
  },
  "last_synced_at": "2026-07-24T18:00:00Z"
}
```

### `GET /api/merchant/pos/match-reviews.php`

Returns open ambiguous identity cases.

### `POST /api/merchant/pos/match-review-resolve.php`

Request:

```json
{
  "review_id": "review-uuid",
  "action": "link_existing",
  "crm_contact_id": "crm-contact-uuid",
  "reason": "Verified customer email and phone.",
  "idempotency_key": "client-uuid"
}
```

Actions:

```text
link_existing
create_new
ignore
```

Rules:

- Name-only resolution is manual and requires reason.
- Linking updates the external mapping and may trigger deterministic identity enrichment for existing anonymous/unmatched transactions.
- Never merge two canonical CRM contacts implicitly through this endpoint.

## 9. Provider webhook endpoints

Recommended Square endpoint:

```text
POST /api/integrations/pos/square/webhook.php
```

Required processing:

1. Read raw body once.
2. Resolve Square connection using merchant/account identity from the envelope after safe minimal parsing.
3. Verify `x-square-hmacsha256-signature` using configured notification URL, raw body, and connection/subscription signature key.
4. Extract `event_id`, type, merchant ID, and created timestamp.
5. Insert or resolve durable provider receipt.
6. Detect conflicting replay by event ID + payload hash.
7. Enqueue `pos_webhook_jobs` row.
8. Return `2xx` quickly.

Success response should be intentionally minimal:

```json
{"ok": true}
```

Do not return merchant, connection, queue, or validation details to the provider.

### Webhook status outcomes

- Valid new delivery: `200` or `202` after durable enqueue.
- Valid duplicate with identical hash: `200`.
- Invalid signature: `401` or `403`, quarantined receipt metadata only where safe.
- Invalid JSON/envelope: `400`.
- Unknown/revoked connection: stable rejection/quarantine policy; do not enqueue business processing.
- Internal failure before durable receipt: `500` so provider may retry.

## 10. Worker contract

CLI:

```text
php scripts/run_pos_sync_worker.php --limit=100
```

Options:

```text
--limit=100
--provider=square
--connection=<uuid>
--max-seconds=50
--job=<uuid>
--dry-run
```

Worker algorithm:

1. Claim ready jobs using transaction-safe locking and a unique lock token.
2. Load connection and verify feature/entitlement/status.
3. Instantiate provider adapter.
4. Parse stored envelope/payload.
5. Retrieve missing provider objects when required.
6. Normalize customer/transaction/refund.
7. Upsert canonical ledger and apply unique effects.
8. Project CRM changes.
9. Write outbox events.
10. Mark job processed.
11. On retryable failure, increment attempt and schedule backoff.
12. On permanent failure, dead-letter with stable code.
13. Release stale locks through reconciliation/worker startup logic.

## 11. Retry and dead-letter APIs

### `GET /api/merchant/pos/jobs.php`

Filters:

```text
connection_id
status
provider
event_type
from
to
cursor
```

Payloads are redacted and minimized.

### `POST /api/merchant/pos/job-retry.php`

Request:

```json
{
  "job_id": "job-uuid",
  "reason": "Credentials refreshed; retry delivery.",
  "idempotency_key": "client-uuid"
}
```

Rules:

- Only retryable/dead-letter jobs may be queued.
- Invalid signatures/conflicting replays cannot be retried into trusted processing.
- Retry action is audited.

## 12. Reconciliation APIs

### `POST /api/merchant/pos/reconcile.php`

Request:

```json
{
  "connection_id": "connection-uuid",
  "scope": "all",
  "from": "2026-07-01T00:00:00Z",
  "to": "2026-07-24T23:59:59Z",
  "repair": false,
  "idempotency_key": "client-uuid"
}
```

Scopes:

```text
all
customers
transactions
crm_rollups
queue
outbox
```

Response returns a queued sync/reconciliation run ID.

Repair mode requires elevated permission and explicit confirmation. Deterministic repairs only.

## 13. Merchant dashboard summary

### `GET /api/merchant/pos/dashboard.php`

Response:

```json
{
  "connections": {
    "active": 1,
    "action_required": 0,
    "revoked": 0
  },
  "sync": {
    "customers": 1240,
    "matched": 1100,
    "ambiguous": 12,
    "anonymous_transactions": 44
  },
  "purchases": {
    "completed": 3200,
    "gross_paid_cents": 18450000,
    "net_paid_cents": 17920000,
    "merchandise_ltv_cents": 15180000,
    "refunded_cents": 530000
  },
  "health": {
    "queued": 2,
    "retryable": 0,
    "dead_letter": 1,
    "last_webhook_at": "2026-07-24T18:10:00Z"
  }
}
```

## 14. Transaction APIs

### `GET /api/merchant/pos/transactions.php`

Filters:

```text
connection_id
provider
location_id
crm_contact_id
identity_status
status
from
to
q
cursor
```

### `GET /api/merchant/pos/transaction.php?id=<uuid>`

Returns:

- Canonical monetary breakdown.
- Provider and location references.
- Customer mapping and CRM contact where authorized.
- Line items.
- Effects.
- CRM projection status.
- Event-publication status.
- Audit-safe provider metadata.

Never return full raw provider payload or credentials.

## 15. Canonical normalized customer contract

```json
{
  "schema_version": "pos.customer.v1",
  "provider": "square",
  "connection_id": "connection-uuid",
  "external_customer_id": "square-customer-id",
  "provider_version": "7",
  "status": "active",
  "identity": {
    "display_name": "Jamie Smith",
    "given_name": "Jamie",
    "family_name": "Smith",
    "company_name": null,
    "email": "jamie@example.com",
    "phone": "+16025550100"
  },
  "consent": {
    "email": "unsubscribed",
    "sms": "unknown"
  },
  "custom_attributes": {},
  "provider_created_at": "2026-07-20T12:00:00Z",
  "provider_updated_at": "2026-07-23T18:00:00Z"
}
```

## 16. Canonical normalized transaction contract

```json
{
  "schema_version": "pos.transaction.v1",
  "provider": "square",
  "connection_id": "connection-uuid",
  "provider_event_id": "event-uuid",
  "event_type": "payment.updated",
  "transaction": {
    "external_transaction_id": "payment-id",
    "external_order_id": "order-id",
    "external_customer_id": "customer-id",
    "external_location_id": "location-id",
    "status": "completed",
    "source_channel": "in_store",
    "identity_status": "identified",
    "currency": "USD",
    "subtotal_cents": 4200,
    "discount_cents": 500,
    "tax_cents": 320,
    "tip_cents": 800,
    "service_charge_cents": 0,
    "gross_total_cents": 4820,
    "refunded_cents": 0,
    "net_total_cents": 4820,
    "ltv_eligible_cents": 3700,
    "gift_card_sale_cents": 0,
    "occurred_at": "2026-07-23T18:20:00Z",
    "completed_at": "2026-07-23T18:21:00Z",
    "provider_updated_at": "2026-07-23T18:21:00Z"
  },
  "customer": {
    "email": null,
    "phone": null
  },
  "items": [
    {
      "external_line_item_id": "line-id",
      "catalog_object_id": "catalog-id",
      "sku": "MEAL-01",
      "name": "Family Meal",
      "variant_name": null,
      "quantity": "1.000000",
      "unit_price_cents": 4200,
      "gross_cents": 4200,
      "discount_cents": 500,
      "tax_cents": 320,
      "net_cents": 3700,
      "is_gift_card": false
    }
  ],
  "metadata": {}
}
```

## 17. Canonical event outbox payloads

### `pos.purchase.completed`

```json
{
  "schema_version": "pos.event.v1",
  "event_type": "pos.purchase.completed",
  "event_id": "outbox-event-uuid",
  "merchant_id": "merchant-public-id",
  "connection_id": "connection-uuid",
  "provider": "square",
  "transaction_id": "transaction-uuid",
  "crm_contact_id": "crm-contact-uuid",
  "identity_status": "identified",
  "location_id": "location-uuid",
  "currency": "USD",
  "gross_total_cents": 4820,
  "net_total_cents": 4820,
  "ltv_eligible_cents": 3700,
  "completed_at": "2026-07-23T18:21:00Z",
  "items": [
    {"sku": "MEAL-01", "catalog_object_id": "catalog-id", "quantity": "1.000000", "net_cents": 3700}
  ]
}
```

### `pos.purchase.refunded`

Includes transaction, refund effect key, refund amount, allocated merchandise refund, and resulting LTV values.

## 18. Error codes

Recommended stable codes:

```text
pos_feature_disabled
pos_entitlement_required
pos_permission_denied
pos_connection_not_found
pos_connection_inactive
pos_connection_action_required
pos_scope_missing
pos_oauth_state_invalid
pos_oauth_exchange_failed
pos_signature_invalid
pos_webhook_envelope_invalid
pos_conflicting_replay
pos_job_not_retryable
pos_provider_rate_limited
pos_provider_auth_failed
pos_provider_object_not_found
pos_normalization_failed
pos_currency_mismatch
pos_customer_match_ambiguous
pos_transaction_conflict
pos_reconciliation_required
```

## 19. Rate and request limits

Suggested merchant API defaults:

- Provider/connection reads: 120/minute per user.
- Settings writes: 30/minute.
- Import/reconcile starts: 10/hour per connection.
- Manual retries: 30/hour per connection.
- Match review actions: 60/hour per merchant.

Webhook endpoints are governed by signature validation, body-size limits, connection-level event rate monitoring, and abusive invalid-signature throttling. Do not rate-limit trusted provider traffic so aggressively that valid bursts are dropped.

## 20. Audit events

```text
pos.connection.oauth_started
pos.connection.activated
pos.connection.settings_updated
pos.connection.disconnected
pos.customer_import.started
pos.customer_import.completed
pos.customer.match_resolved
pos.webhook.quarantined
pos.job.retried
pos.reconciliation.started
pos.reconciliation.repaired
```

## 21. API status

These contracts are planning artifacts only.

- Endpoints implemented: no
- OAuth configured: no
- Webhook configured: no
- Worker installed: no
- Production API changed: no