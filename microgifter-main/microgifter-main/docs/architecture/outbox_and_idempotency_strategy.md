# Outbox and Idempotency Strategy

## Purpose

Microgifter will use an outbox pattern and idempotency keys before building high-volume workflows.

This protects checkout, gift sending, claims, emails, notifications, agent actions, and webhook processing from duplicate execution and slow synchronous requests.

## Outbox rule

User-facing requests should complete the main database transaction quickly and enqueue slow work into `outbox_events`.

Examples of outbox work:

- send email
- send SMS
- generate QR or voucher media
- notify merchant
- trigger analytics
- run agent/LLM task
- process webhook side effects
- refresh read-model snapshots

## Idempotency rule

Any endpoint that can create money movement, gift value, claims, orders, or external side effects must support idempotency.

Examples:

- checkout
- send gift
- claim gift
- redeem voucher
- start agent action
- process webhook

## Flow

1. Client sends request with idempotency key.
2. Server reserves the key under user/account/store scope.
3. Server performs the transaction once.
4. Server stores the response or final resource ID.
5. Duplicate requests return the original result or a safe conflict.

## Worker strategy

Stage 1 only adds the database and helper foundation. A later worker can process pending outbox events by:

1. locking one available row
2. attempting the work
3. marking complete or failed
4. retrying with backoff
5. sending permanently failed jobs to manual review

## Carry-forward rule

No future money, gift, claim, checkout, webhook, or agent side-effect endpoint should be accepted without idempotency and outbox review.
