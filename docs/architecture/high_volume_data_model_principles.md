# Microgifter High-Volume Data Model Principles

## Purpose

This document defines the high-volume strategy that sits underneath the existing staged build plan. It does not replace the stage plan. It adds scale rules that every future stage must follow.

## Core principle

Microgifter should be simple to reason about at small scale and naturally shardable at large scale.

The database should avoid scattered ownership, ambiguous records, and hot-path cross-domain joins.

## Big-platform pattern

Large systems generally simplify data around these ideas:

1. Small number of core entities.
2. Clear ownership boundaries.
3. Stable public IDs.
4. Current-state tables separated from event/history tables.
5. Append-only operational logs for important workflows.
6. Async outbox processing for slow or unreliable work.
7. Idempotency keys for duplicate-safe writes.
8. Read models for hot public reads.
9. Cache-ready lookup patterns.
10. Future shard keys included from the beginning.

## Microgifter data planes

### Identity plane

- users
- accounts
- account_members
- roles
- permissions
- user_roles
- role_permissions

### Commerce plane

Future stages should add commerce tables under account/store ownership:

- stores
- products
- orders
- payments

### Gift plane

Future stages should add gift tables under account/store/user ownership:

- gift_vouchers
- gift_claims
- gift_events

### Messaging plane

Future stages should keep inbox data scoped by owner/account:

- inbox_threads
- inbox_messages

### Agent plane

Future stages should keep agent data scoped by account/user/store:

- agents
- agent_runs
- agent_events

### Operational plane

- outbox_events
- idempotency_keys
- audit_logs
- security_logs
- rate_limits
- schema_migrations

### Read-model plane

Future modules should add snapshot tables for public and hot reads, such as:

- store_public_snapshots
- product_public_snapshots
- gift_claim_snapshots
- inbox_summaries

## Required questions before creating a table

Every new table must answer:

1. Who owns this record?
2. What is the partition key?
3. Is this current truth or history?
4. Can this be processed asynchronously?
5. Does this need a public ID?
6. What is the hot read query?
7. What indexes support the hot read query?
8. Can this table grow forever?
9. Can this data be cached safely?
10. What object-level authorization rule protects it?

## Stage carry-forward rule

No future stage may add high-growth data tables without an ownership key, public identifier strategy, hot-read index strategy, lifecycle rule, and authorization rule.
