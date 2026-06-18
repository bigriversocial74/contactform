# 03P HostGator Compatibility and Realtime Strategy

## Purpose

This pass documents how Microgifter can run on HostGator/cPanel during the build phase while preserving the AWS/high-volume architecture path.

It also defines the realtime delivery strategy so messaging, inbox, notifications, and agent status can evolve safely without making Socket.IO or websockets a hard requirement too early.

## Files added

```text
docs/deployment/hostgator_cpanel_compatibility_profile.md
docs/installation/hostgator_cpanel_install_checklist.md
docs/deployment/hostgator_feature_flags_and_runtime_modes.md
docs/architecture/realtime_delivery_and_messaging_strategy.md
docs/architecture/foundation_build_watchlist.md
```

## Key decisions

### HostGator role

HostGator is acceptable for:

- Stage 1 smoke testing
- private beta
- early UI/auth testing
- low-traffic prototype hosting

HostGator is not the final high-volume production target.

### AWS role

AWS remains the production scale target:

- Aurora MySQL-compatible
- ElastiCache Redis/Valkey
- S3
- CloudFront
- SQS/EventBridge or equivalent worker/event services
- CloudWatch or equivalent monitoring

### Realtime delivery

Do not require Socket.IO during Stage 1.

Delivery path:

1. HostGator: polling first.
2. Private beta: optional SSE only if safe.
3. AWS: dedicated websocket/realtime service.
4. High volume: websocket gateway plus Redis/Valkey fanout and durable outbox.

## Carry-forward rules

Every future feature must declare whether it is:

```text
hostgator-compatible
aws-preferred
aws-required
worker-required
realtime-enhanced
admin-only
```

Every future data module must continue using:

- object-level authorization
- scope/owner keys
- outbox for slow side effects
- idempotency for duplicate-sensitive writes
- polling fallback before websocket dependency

## Next recommended implementation

The next foundation pass should add runtime profile helpers into PHP config so code can check capabilities like:

```text
MG_RUNTIME_PROFILE
MG_ENABLE_WEBSOCKETS
MG_ENABLE_QUEUE_WORKER
MG_ENABLE_REDIS
MG_ENABLE_POLLING_NOTIFICATIONS
MG_ENABLE_DB_OUTBOX
```
