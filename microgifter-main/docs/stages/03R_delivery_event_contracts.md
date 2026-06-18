# 03R Microgifter Delivery Event Contracts

## Purpose

03R defines the durable delivery-event backbone before Stage 2 gift/product/order logic is implemented.

Microgifter is being treated as the delivery system for phygital gifting: send, notify, verify, track, confirm, audit, and recover.

## Files added

```text
docs/architecture/delivery_event_contracts.md
docs/architecture/delivery_state_machine.md
database/stage_1_delivery_events_03R.sql
includes/delivery.php
```

## Database install order

```text
database/stage_1_identity.sql
database/stage_1_repair_03M.sql
database/stage_1_security_hardening_03N.sql
database/stage_1_security_hardening_03N_3.sql
database/stage_1_high_volume_foundation_03O.sql
database/stage_1_delivery_events_03R.sql
```

## What changed

The new migration adds:

```text
delivery_event_types
delivery_status_transitions
delivery_events
```

The new helper adds:

```text
mg_delivery_statuses()
mg_delivery_terminal_statuses()
mg_delivery_is_terminal()
mg_delivery_transition_allowed()
mg_delivery_require_transition()
mg_delivery_record_event()
```

## Practical architecture decision

The source of truth remains durable state and database events. Polling, notifications, websocket fanout, Socket.IO, SMS, email, push, and agent callbacks are delivery surfaces, not proof of truth.

## Good idea now

```text
Define statuses and event names before feature tables.
Use delivery_events as append-only proof.
Use outbox_events for slow side effects.
Require idempotency for send, claim, verify, fulfill, and webhook-style actions.
Keep HostGator-compatible polling as the baseline.
```

## Bad idea now

```text
Making realtime delivery required.
Treating a frontend message as confirmation.
Letting gift states change without event proof.
Creating gift/order/claim tables without delivery transitions.
Skipping idempotency on claim/send actions.
```

## Stage 2 carry-forward rule

Every Stage 2 gift, product, order, claim, inbox, and agent workflow must map to:

1. current state
2. allowed transition
3. delivery event
4. idempotency rule
5. outbox action
6. polling/realtime behavior
7. object authorization rule
