# 03Q Runtime Profile Helpers

## Purpose

This pass added runtime profile helpers so Microgifter can remain HostGator-compatible during foundation building while keeping a clean AWS path for production scale.

The backend is treated as the delivery system for instant phygital gifting: send, notify, verify, track, confirm, and audit.

## Files added

```text
includes/runtime.php
api/runtime.php
docs/architecture/microgifter_delivery_system_architecture.md
docs/deployment/runtime_profiles.md
```

## Files updated

```text
includes/app.php
api/config.php
.env.example
```

## Practical decision

Do not require Socket.IO, Redis, queue workers, or AWS services during Stage 1/HostGator mode.

Use:

```text
polling + database truth + DB outbox
```

as the portable delivery baseline.

## AWS carry-forward

AWS mode can later enable:

```text
queue workers
Redis/Valkey
websocket gateway
SQS/EventBridge
CloudWatch monitoring
```

without changing the core data truth model.

## Runtime flags

```text
MG_RUNTIME_PROFILE
MG_ENABLE_POLLING_NOTIFICATIONS
MG_ENABLE_DB_OUTBOX
MG_ENABLE_QUEUE_WORKER
MG_ENABLE_REDIS
MG_ENABLE_WEBSOCKETS
MG_ENABLE_SSE
```

## Security/practicality notes

Realtime delivery is not proof of delivery. Database/event state is proof. Websockets are a convenience channel, not a source of truth.

Every future gift, claim, inbox, merchant, and agent workflow must write durable state before triggering notifications or realtime delivery.
