# Stage 2 Foundation Guardrails

Stage 2 should build the real Microgifter engine, but it must not weaken the Stage 1 foundation.

Microgifter is the delivery system for phygital gifting: send, notify, verify, track, confirm, audit, and recover.

## Non-negotiable architecture rules

### 1. Database state is truth

Frontend state, websocket messages, email delivery, SMS delivery, and agent callbacks are not proof of truth.

Truth must be represented by durable database rows and delivery events.

### 2. Every external record needs a public ID

Do not expose sequential database IDs as public handles for gifts, claims, orders, inbox threads, agents, or merchant records.

Use the public ID helpers and keep internal IDs internal.

### 3. Every table needs a scope key

Each major table must clearly define at least one ownership or scope boundary:

```text
account_id
store_id
owner_user_id
```

### 4. Object-level authorization is required

Role checks alone are not enough.

Every read/write by ID must confirm ownership, account membership, merchant/store scope, or explicit permission.

### 5. Duplicate-sensitive writes need idempotency

Required for:

```text
gift send
gift claim
voucher redeem
checkout/order create
payment/webhook handling
message send
agent action execution
notification dispatch
```

### 6. Slow side effects use outbox

Do not make users wait for:

```text
email
SMS
push notification
QR generation
analytics aggregation
agent/LLM calls
webhook dispatch
search indexing
```

Write core state first, enqueue side effects second.

### 7. Delivery events are append-only

Gift/order/claim workflows must record append-only events.

Examples:

```text
gift.created
gift.validated
gift.sent
gift.notification.queued
gift.notification.sent
gift.opened
gift.claim.started
gift.claim.verified
gift.claim.confirmed
gift.fulfilled
gift.failed
gift.expired
```

### 8. Hot reads must have index plans

Each new data module must define the hot read queries and indexes before implementation.

Examples:

```text
WHERE owner_user_id = ? ORDER BY created_at DESC
WHERE account_id = ? AND status = ? ORDER BY created_at DESC
WHERE store_id = ? AND slug = ?
WHERE public_id = ?
```

### 9. HostGator-compatible first, AWS-enhanced later

Stage 2 should continue to run without Redis, websocket servers, long-running queue workers, or AWS-only services.

AWS services can be added as enhanced production adapters, not hard requirements for the early build.

### 10. Universal chrome stays universal

Feature pages must use the shared header/footer/account behavior.

Do not add one-off page headers, duplicate account menus, or divergent nav/dropdown styles.

## Required module design template

Every Stage 2 module plan must answer:

```text
Module name:
Primary owner/scope key:
Public ID format:
Current-state table:
Event/history table:
Allowed statuses:
Allowed state transitions:
Object authorization rule:
Idempotency needs:
Outbox events:
Hot-read queries:
Indexes:
HostGator mode:
AWS enhanced mode:
Security/audit events:
Acceptance tests:
```

## First recommended Stage 2 module

Start with:

```text
04A_microgifter_product_and_gift_schema_design
```

Do not begin with checkout/payments until products, gift offers, vouchers, claims, delivery events, and object ownership are designed cleanly.
