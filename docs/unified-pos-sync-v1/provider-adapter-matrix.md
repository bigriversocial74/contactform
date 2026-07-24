# Unified POS Customer & Purchase Sync v1 — Provider Adapter Matrix

## 1. Purpose

This matrix defines the provider-specific edge of the system. All providers must normalize into the same Microgifter customer, transaction, refund, location, and event contracts.

Provider adapters stop at normalization. Core CRM, LTV, campaign, and reward logic remains provider-neutral.

## 2. Shared provider capabilities

Each adapter should declare:

```text
customer_directory
customer_webhooks
custom_attributes
purchase_webhooks
refund_webhooks
line_items
locations
historical_backfill
token_refresh
webhook_signature
```

A merchant UI must hide unsupported settings rather than simulate support.

## 3. Square — Phase 1 provider

### Connection

- OAuth authorization.
- Production and Sandbox environments.
- Merchant/account discovery.
- Location discovery and merchant selection.
- Token expiry/refresh handling according to Square OAuth behavior.

### Read-only scopes

Request the minimum scopes required for enabled functionality:

```text
CUSTOMERS_READ
PAYMENTS_READ
ORDERS_READ
MERCHANT_PROFILE_READ
```

Do not request `CUSTOMERS_WRITE` until an approved Square write-back phase exists.

### Customer import

Use Square Customers API pagination.

Normalize:

- Customer ID.
- Version.
- Given/family/display/company names.
- Email.
- Phone.
- Creation/update timestamps.
- Email unsubscribe preference.
- Selected visible custom attributes.

### Customer events

```text
customer.created
customer.updated
customer.deleted
```

When custom attributes are enabled, subscribe to supported visible definition/value events, including applicable `customer.custom_attribute.visible.updated` and deleted events.

Custom-attribute changes do not reliably produce a standard `customer.updated`, so they require independent processing.

### Purchase events

Primary event:

```text
payment.updated
```

Rules:

- Process completion effect only when `payment.status == COMPLETED`.
- The event may repeat after completion because another payment field changed.
- Use Square `event_id` for delivery idempotency.
- Use Square payment ID as the external transaction ID.
- Preserve `order_id`, `customer_id`, `location_id`, timestamps, amounts, and provider update/version signals.
- Retrieve the order when line-item data is required and an order ID exists.

### Refund events

```text
refund.updated
```

- Apply only completed canonical refund states.
- Use provider refund ID in the unique refund effect key.
- Preserve pending/failed refund events for audit without changing CRM totals.

### Anonymous/late identity

Recommended identity fallback:

```text
Payment.customer_id
→ retrieved Payment customer information
→ Order.customer_id
→ trusted buyer email present in a provider object
→ anonymous
```

A later completed payment update may enrich identity without creating a second completion effect.

### Signature

Verify `x-square-hmacsha256-signature` using:

```text
configured notification URL
+ unmodified raw request body
+ subscription signature key
```

Use constant-time comparison or the official SDK helper. Do not use the repository's current generic timestamp/body HMAC helper unchanged.

### Acknowledgement/retries

- Durably record and enqueue quickly.
- Return `2xx` promptly.
- Treat delivery order as non-guaranteed.
- Duplicate deliveries are normal.

### Square official references

- `https://developer.squareup.com/docs/payments-api/webhooks`
- `https://developer.squareup.com/docs/webhooks/overview`
- `https://developer.squareup.com/docs/webhooks/step3validate`
- `https://developer.squareup.com/reference/square/customers-api/webhooks`
- `https://developer.squareup.com/docs/customer-custom-attributes-api/overview`
- `https://developer.squareup.com/docs/webhooks/v2webhook-events-tech-ref`

Verify the selected API version at implementation time.

## 4. Shopify POS — future adapter

### Connection

- Shopify app installation/OAuth.
- Shop domain identifies the provider account.
- Store granted scopes and API version.
- Locations retrieved from Shopify.

### Candidate topics

```text
orders/create
orders/updated or orders/paid
refunds/create or applicable refund topic
customers/create
customers/update
customers/delete/redact topics where required
app/uninstalled
```

Exact topics and payload contracts must be verified against the Shopify API version selected for implementation.

### POS isolation

The adapter must prove the order came from Shopify POS before producing an in-store canonical transaction.

Use tested provider fixtures and current Shopify source/channel fields. Do not rely on an unverified hardcoded `source_name == "pos"` assumption across API versions.

### Idempotency

- Delivery/webhook ID from Shopify headers.
- Order ID as external transaction/order identity, with provider transaction/refund IDs for effects.
- Order update timestamp/version/hash for revision control.

### Signature

Verify Shopify HMAC against the unmodified raw body using the app secret and current Shopify requirements.

### Privacy

Shopify customer/data-erasure webhook requirements must be handled by the adapter and Microgifter privacy workflow.

## 5. Clover — future adapter

### Connection

- Clover app installation/OAuth.
- Merchant ID identifies provider account.
- Retrieve merchant locations/devices only as needed.

### Platform webhook event types

Candidate normal Clover platform event groups:

```text
C — Customers
O — Orders
P — Payments
```

The normal in-store platform webhook must not be confused with Clover Hosted Checkout payment webhooks, which use a different contract and signature model.

### Processing

Clover platform events may identify the changed object and operation. The adapter retrieves the current customer/order/payment object before normalization.

### Idempotency

Use the most stable provider event identifier supplied by the installed Clover webhook contract. If Clover does not provide a globally unique delivery ID, derive a deterministic receipt key from merchant, object type, object ID, operation, provider timestamp/version, and payload hash while preserving conflicting replay detection.

### Purchase composition

- Payment object supplies financial state.
- Order object supplies line items.
- Customer object supplies identity where linked.
- Refund/void behavior must be normalized as independent effects.

### Reference

- `https://docs.clover.com/dev/docs/webhooks`

Reverify installation-specific event and signature requirements during the Clover phase.

## 6. Toast — partner-gated future adapter

### Prerequisite

- Toast Developer Partner access.
- Confirm API product access and webhook event categories granted to Microgifter.

### Target behavior

- Receive closed/completed check events or the applicable Toast webhook category.
- Retrieve check/order details and line items when the event does not contain a complete object.
- Handle high-volume quick-service transactions where customer identity is often absent.
- Preserve location/restaurant identity and business date.

### Anonymous behavior

Toast anonymous transactions should remain merchant aggregate activity until a reliable customer identity is available. Do not infer customer identity from employee, device, table, or guest-count data.

### Signature

Implement exactly the signing contract supplied for Microgifter's Toast subscription. Do not prebuild assumptions before partner access is confirmed.

### Reference

- `https://doc.toasttab.com/doc/devguide/apiWebhookBasics.html`

## 7. Lightspeed — future discovery/adapter

Before implementation, verify for the selected Lightspeed product family:

- Product/API family and merchant tenancy model.
- OAuth/installation contract.
- Customer directory endpoints.
- Sale/order/payment events.
- Refund/void lifecycle.
- Locations/outlets/registers.
- Webhook signature and replay semantics.
- Catalog/line-item identifiers.
- Rate limits and historical query constraints.

Lightspeed product families may expose different APIs. The provider key may need a more specific adapter identifier, such as:

```text
lightspeed_retail
lightspeed_restaurant
```

Core provider columns remain strings so this does not require a schema alteration.

## 8. Shared normalized status mappings

### Customer status

```text
active
deleted
```

### Transaction status

```text
pending
authorized
completed
partially_refunded
refunded
cancelled
failed
```

### Identity status

```text
identified
anonymous
ambiguous
deleted_customer
```

### Failure class

```text
retryable_rate_limit
retryable_provider_error
retryable_network
connection_action_required
permanent_invalid_object
permanent_unsupported_event
quarantine_signature
quarantine_conflicting_replay
```

## 9. Shared normalized monetary rules

Adapters must provide or calculate:

```text
currency
subtotal_cents
discount_cents
tax_cents
tip_cents
service_charge_cents
gross_total_cents
refunded_cents
net_total_cents
ltv_eligible_cents
gift_card_sale_cents
```

Provider rounding must be preserved and reconciliation must confirm line-item totals against provider totals. Do not silently invent a balanced total when the provider object is incomplete; mark metadata and retrieve the authoritative object when possible.

## 10. Shared source-channel rules

Canonical values may include:

```text
in_store
online
invoice
terminal
mobile
unknown
```

Each provider adapter decides whether an event qualifies as POS/in-store for this feature. Non-POS transactions may be retained only when the merchant explicitly enables omnichannel ingestion in a future phase.

## 11. Shared custom-attribute rules

- Merchant-selected allowlist.
- Import visible, non-sensitive values only.
- Store definition key, display name, type, provider version, and normalized value where useful.
- Never import fields matching secret/token/password/card/device/payment credential patterns.
- Attribute removal is represented explicitly; stale values are not silently retained.

## 12. Adapter fixture requirements

Each provider phase must include fixtures for:

- Valid signature.
- Invalid signature.
- Duplicate identical delivery.
- Conflicting replay.
- Out-of-order update.
- Completed purchase.
- Anonymous purchase.
- Later customer enrichment.
- Partial refund.
- Full refund.
- Disabled location.
- Deleted/merged provider customer.
- Provider rate limit.
- Token expiration/revocation.
- Unsupported event type.

## 13. Provider release rule

A provider may not be marked available until:

1. Official current documentation has been reviewed.
2. OAuth/scopes are verified.
3. Signature verification passes provider fixtures.
4. Delivery and effect idempotency pass.
5. Customer and purchase normalization pass.
6. Refund reconciliation passes.
7. Merchant tenancy passes.
8. Production webhook configuration is confirmed separately.