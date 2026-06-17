# HostGator Feature Flags and Runtime Modes

## Purpose

Microgifter should run safely on HostGator while we build, but future AWS-only capabilities must be explicitly disabled or downgraded when the platform is in shared-hosting mode.

## Recommended runtime flag

Add this to environment/config when available:

```text
MG_RUNTIME_PROFILE=hostgator
```

Supported values:

```text
hostgator
aws
local
staging
production
```

## HostGator defaults

For HostGator/cPanel mode:

```text
MG_ENABLE_WEBSOCKETS=false
MG_ENABLE_QUEUE_WORKER=false
MG_ENABLE_REDIS=false
MG_ENABLE_S3_UPLOADS=false
MG_ENABLE_REALTIME_SSE=false
MG_ENABLE_LONG_POLLING=false
MG_ENABLE_POLLING_NOTIFICATIONS=true
MG_ENABLE_DB_OUTBOX=true
MG_ENABLE_DB_RATE_LIMITS=true
MG_ENABLE_DB_SESSIONS=true
```

## AWS defaults later

For AWS production mode:

```text
MG_ENABLE_WEBSOCKETS=true
MG_ENABLE_QUEUE_WORKER=true
MG_ENABLE_REDIS=true
MG_ENABLE_S3_UPLOADS=true
MG_ENABLE_REALTIME_SSE=false
MG_ENABLE_POLLING_NOTIFICATIONS=true
MG_ENABLE_DB_OUTBOX=true
MG_ENABLE_DB_RATE_LIMITS=false_or_hybrid
MG_ENABLE_DB_SESSIONS=false_or_hybrid
```

## Messaging behavior by profile

### hostgator

- use API polling
- store messages in database
- store notifications in database
- use outbox table for durable side effects
- avoid long-running workers
- process slow tasks manually or with cron if available

### aws

- use API plus websocket/realtime gateway
- use Redis/Valkey for presence, hot channels, and fanout
- use SQS/EventBridge or worker service for durable async processing
- keep database as source of truth

## Required coding rule

Every feature that depends on infrastructure must check a runtime capability flag.

Examples:

- realtime delivery
- queue workers
- Redis/Valkey cache
- S3 media storage
- websocket agent status
- background notification fanout

If the capability is disabled, the feature must degrade safely instead of crashing.

## Stage carry-forward

Future stages must document whether each new feature is:

```text
hostgator-compatible
aws-preferred
aws-required
admin-only
worker-required
realtime-enhanced
```
