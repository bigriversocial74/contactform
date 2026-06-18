# Microgifter Delivery Event Contracts

## Purpose

Microgifter's backend is the delivery system for phygital gifting. The system must be able to send, notify, verify, track, confirm, audit, and recover every gift workflow.

Realtime delivery is not the source of truth. Durable state and durable events are the source of truth.

## Core rule

Every meaningful delivery action must produce a durable event before optional notification or realtime delivery.

Required order:

1. Validate the requested action.
2. Write the current-state record inside a transaction.
3. Write a delivery event inside the same transaction when possible.
4. Queue outbox work for email, SMS, push, webhook, agent, or realtime fanout.
5. Return a safe response.

## Event naming convention

Use lower-case dot-separated event names:

```text
gift.created
gift.validated
gift.sent
gift.notification.queued
gift.notification.sent
gift.notification.failed
gift.opened
gift.claim.started
gift.claim.verified
gift.claim.confirmed
gift.claim.rejected
gift.fulfilled
gift.failed
gift.expired
```

## Event categories

### Gift lifecycle

```text
gift.created
gift.updated
gift.cancelled
gift.expired
```

### Delivery lifecycle

```text
gift.validated
gift.sent
gift.delivery.delayed
gift.delivery.failed
gift.delivery.retried
```

### Notification lifecycle

```text
gift.notification.queued
gift.notification.sent
gift.notification.failed
gift.notification.opened
```

### Recipient lifecycle

```text
gift.opened
gift.viewed
gift.claim.started
gift.claim.verified
gift.claim.confirmed
gift.claim.rejected
```

### Fulfillment lifecycle

```text
gift.fulfillment.started
gift.fulfilled
gift.fulfillment.failed
```

### Security and verification lifecycle

```text
gift.verification.required
gift.verification.passed
gift.verification.failed
gift.fraud_review.required
gift.fraud_review.cleared
gift.fraud_review.blocked
```

## Standard event payload

Every delivery event payload should contain:

```json
{
  "actor_user_id": 123,
  "account_id": 456,
  "store_id": 789,
  "request_id": "req_...",
  "source": "api",
  "reason": "human-readable internal reason",
  "metadata": {}
}
```

Do not store secrets, raw claim codes, passwords, full payment data, or private tokens in event payloads.

## Runtime delivery modes

HostGator mode:

```text
DB state + delivery events + DB outbox + polling
```

AWS mode:

```text
DB state + delivery events + DB outbox + worker + optional realtime fanout
```

## Carry-forward rule

No Stage 2+ gift, product, claim, order, inbox, merchant, or agent workflow should be accepted unless it defines:

- current-state field or table
- delivery event name
- allowed transition
- idempotency behavior
- outbox side effects
- notification behavior
- tracking behavior
- object authorization rule
