# Runtime Profiles and Feature Flags

## Purpose

Runtime profiles let Microgifter run on simple HostGator/cPanel hosting while preserving a clean path to AWS production infrastructure.

The application should ask runtime helpers what is available instead of hard-coding infrastructure assumptions into feature code.

## Profiles

```text
local       developer machine
hostgator   shared hosting/private beta
staging     production-like testing
aws         AWS managed infrastructure
production  live production profile
```

## Feature flags

```text
MG_ENABLE_POLLING_NOTIFICATIONS
MG_ENABLE_DB_OUTBOX
MG_ENABLE_QUEUE_WORKER
MG_ENABLE_REDIS
MG_ENABLE_WEBSOCKETS
MG_ENABLE_SSE
```

## HostGator default

```text
MG_RUNTIME_PROFILE=hostgator
MG_ENABLE_POLLING_NOTIFICATIONS=true
MG_ENABLE_DB_OUTBOX=true
MG_ENABLE_QUEUE_WORKER=false
MG_ENABLE_REDIS=false
MG_ENABLE_WEBSOCKETS=false
MG_ENABLE_SSE=false
```

HostGator should use polling and DB-backed durability. Do not depend on long-running workers or websocket servers.

## AWS default direction

```text
MG_RUNTIME_PROFILE=aws
MG_ENABLE_POLLING_NOTIFICATIONS=true
MG_ENABLE_DB_OUTBOX=true
MG_ENABLE_QUEUE_WORKER=true
MG_ENABLE_REDIS=true
MG_ENABLE_WEBSOCKETS=false initially, true later
MG_ENABLE_SSE=false or true only when supported by the runtime
```

AWS can enable queue workers and Redis/Valkey after the core outbox/polling workflow is stable.

## Practical rule

Polling is the universal fallback. Websockets should never be the only way a user receives gift status, claim status, inbox messages, or agent updates.

## Public runtime endpoint

```text
/api/runtime.php
```

This endpoint exposes non-secret runtime mode information to the frontend so the UI can choose polling or enhanced delivery behavior safely.
