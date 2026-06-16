# Realtime Delivery and Messaging Strategy

## Decision

Do not make Socket.IO or any long-running realtime server a hard Stage 1 requirement.

Microgifter should support realtime-style UX through progressive delivery layers:

1. HostGator/shared hosting mode: short polling first.
2. Private beta mode: polling plus optional Server-Sent Events if hosting supports it.
3. AWS production mode: dedicated WebSocket/realtime service.
4. High-volume mode: WebSocket service backed by Redis/Valkey pub/sub or a managed event bus.

## Why not Socket.IO as a required dependency now?

Socket.IO normally requires a long-running Node.js process. Basic HostGator/cPanel shared hosting is not the right place for always-on Node workers, websocket gateways, Redis adapters, or process supervisors.

If Socket.IO is added too early as a hard dependency, the app becomes harder to run on basic hosting while we are still building the foundation.

## Recommended Microgifter approach

Use the database as the source of truth and use realtime delivery as an enhancement.

Core truth tables later:

```text
inbox_threads
inbox_messages
message_recipients
message_delivery_events
agent_runs
agent_events
notifications
```

Operational delivery tables already started:

```text
outbox_events
idempotency_keys
read_model_refreshes
```

The browser should never depend on a websocket message as the only source of truth. A websocket event should tell the browser to refresh or append from an API-backed source.

## Delivery modes

### Mode 1: HostGator-compatible polling

Use normal API polling for early private beta:

```text
GET /api/inbox/threads.php?since=timestamp
GET /api/notifications.php?since=timestamp
```

Suggested intervals:

- inbox active screen: 5-10 seconds
- background account page: 30-60 seconds
- admin/security dashboard: 30-60 seconds

This works on shared hosting and keeps the platform simple.

### Mode 2: Server-Sent Events fallback

SSE can support one-way server-to-browser updates, but it still ties up PHP workers on shared hosting. Use only if the host can support it.

Good for:

- notifications
- inbox new-message signals
- agent run status signals

Not ideal for:

- high-volume bidirectional chat
- thousands of concurrent users

### Mode 3: AWS WebSocket service

For real production realtime, add a separate realtime service.

Recommended options:

- API Gateway WebSocket API plus Lambda or container handlers
- ECS/Fargate Node.js Socket.IO service
- App Runner container websocket service if supported by final deployment choice

Use Redis/Valkey for fanout and presence when scale requires it.

### Mode 4: High-volume fanout

At higher volume, separate concerns:

- relational DB remains source of truth
- outbox captures durable events
- queue/event bus distributes work
- websocket gateway handles connection fanout
- Redis/Valkey handles short-lived presence and channel membership
- object storage/CDN handles media

## Message lifecycle rule

A message should be saved before it is delivered.

Correct flow:

```text
1. User sends message.
2. API validates auth/object permission.
3. API writes inbox_messages row.
4. API writes outbox_events row.
5. API returns accepted/sent state.
6. Worker/realtime layer delivers event.
7. Browser refreshes thread from API if needed.
```

Do not build a system where the websocket is the source of truth.

## Socket.IO decision

Socket.IO is acceptable later, but only as a dedicated realtime service. It should not be embedded into the PHP request/response foundation.

If selected later:

- run it outside shared hosting
- use Redis/Valkey adapter for horizontal scaling
- authenticate websocket connections with short-lived signed tokens
- authorize channel joins server-side
- never trust client-selected room IDs
- store all important messages/events in the database first

## Stage carry-forward

For Stage 2 and beyond:

- Build inbox/message data tables before realtime transport.
- Build notification polling first.
- Add outbox events for every message/notification.
- Add websocket service only after core workflows are stable.
- Keep HostGator mode functional without websockets.
