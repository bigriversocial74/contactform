# Stage 4E Source Adapter Contracts

## Normalized event

Every adapter submits:

```json
{
  "source_type": "ecommerce",
  "external_event_id": "order-1001-line-2",
  "event_type": "purchase_line_paid",
  "program_id": "optional-program-uuid",
  "payload": {
    "template_id": "published-pppm-template-uuid",
    "quantity": 2,
    "recipient": {
      "user_id": null,
      "external_recipient_id": "customer-900"
    },
    "source": {
      "order_id": "order-1001",
      "line_id": "line-2"
    }
  }
}
```

The event ID must identify the smallest source unit that may create an allocation. For ecommerce, use the invoice/order line rather than only the invoice ID so each quantity can expand into permanent PPPM units without duplicate processing.

## Signed webhook

Headers:

- `X-Microgifter-Timestamp`: Unix timestamp within five minutes of server time.
- `X-Microgifter-Signature`: lowercase hex HMAC-SHA256 of `timestamp + "." + raw_body`.

A connection-specific signing key is derived on the server from `MG_DISTRIBUTION_WEBHOOK_SECRET`, the source public ID, and provider key. Provisioning systems may disclose that derived key once to the integration owner; it is never returned by normal source-read APIs.

## Issuance worker

The worker claims a queued job, reads its normalized request, invokes the existing PPPM item-creation service, and completes the job with the resulting PPPM public ID. The distribution service never inserts directly into `pppm_items`.

Worker actions:

- `claim`
- `complete`
- `fail`

Failures use bounded exponential retry and become `dead_letter` after the configured attempt limit.
