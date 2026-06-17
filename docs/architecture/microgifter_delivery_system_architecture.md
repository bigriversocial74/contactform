# Microgifter Delivery System Architecture

## Core concept

Microgifter's backend is the delivery system for instant phygital gifting. The closest operating metaphor is a trusted delivery network: send, notify, verify, track, confirm, and audit.

The platform should behave less like a simple ecommerce form and more like a reliable delivery protocol for gifts, vouchers, claims, messages, merchant confirmations, and agent-managed workflows.

## Delivery lifecycle

Every real gift workflow should eventually move through a clear lifecycle:

```text
created
validated
queued
sent
notified
opened
claimed
verified
fulfilled
confirmed
settled
archived
```

Not every gift will use every state, but every workflow must be traceable.

## Delivery primitives

The core backend should standardize these primitives before building large features:

- identity: who is sending, receiving, claiming, fulfilling, or administering
- object scope: which user/account/store owns the record
- durable event: what happened and when
- notification intent: who should be notified and by which channel
- verification: proof that the claim/redeem action is allowed
- confirmation: proof that the merchant/platform completed the action
- idempotency: duplicate-safe sends, claims, checkouts, webhook handling, and agent actions
- outbox: slow side effects processed outside the request when possible
- tracking: delivery and state history visible to users/admins
- audit/security log: internal trust and investigation trail

## HostGator mode

HostGator mode is practical for building and private beta:

- PHP/MySQL source of truth
- API polling for notifications/status updates
- database outbox for queued side effects
- no required long-running worker
- no required websocket server
- no required Redis

This mode is slower but simpler and portable.

## AWS mode

AWS mode should preserve the same database and API contracts, but add infrastructure:

- Aurora MySQL-compatible for relational source of truth
- Redis/Valkey for hot cache, fanout acceleration, sessions/rate limits later
- SQS/EventBridge for queue/external event routing later
- S3/CloudFront for media and public assets
- CloudWatch or equivalent for logs/alerts
- websocket/realtime gateway only after durable polling/outbox behavior works

## Practical decision

Do not make realtime delivery the source of truth. Realtime delivery is an enhancement layer.

The database and event/outbox records are the truth. Polling, SSE, websockets, SMS, email, push, and agent notifications are delivery channels layered on top.

## Bad ideas to avoid

- depending on Socket.IO before the app has durable message state
- treating sent notifications as proof of delivery
- storing claims only in transient websocket memory
- letting the frontend decide claim, fulfillment, or permission state
- creating product/gift/order tables without owner scope and idempotency review
- adding queues or Redis as mandatory before HostGator private beta

## Good foundation rule

Every future gift, claim, inbox, agent, or merchant workflow should write durable state first, then trigger side effects through outbox/events.
