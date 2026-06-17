# 03O High-Volume Data Foundation

## Purpose

This build adds high-volume data architecture before Stage 2 feature tables are created. It keeps the staged build plan intact while adding scale rules and helper foundations that every future module must follow.

## Database direction

The current implementation remains MySQL-compatible and targets Amazon Aurora MySQL-compatible for AWS production.

Reason:

- Current SQL already uses MySQL-compatible syntax.
- The reference Sngine background is MySQL-oriented.
- Aurora MySQL-compatible gives an AWS-managed growth path without rewriting the current foundation.
- PostgreSQL remains a strong option for future analytics or separate services, but not as a Stage 1 primary rewrite.

## Files added

```text
database/stage_1_high_volume_foundation_03O.sql
includes/ids.php
api/outbox.php
api/idempotency.php
docs/architecture/high_volume_data_model_principles.md
docs/database/microgifter_scale_table_standards.md
docs/database/partition_key_and_id_strategy.md
docs/architecture/outbox_and_idempotency_strategy.md
docs/infrastructure/aws_database_strategy.md
```

## Tables added

```text
accounts
account_members
outbox_events
idempotency_keys
read_model_refreshes
```

## Concepts added

- Account/organization ownership boundary.
- Public ID standard.
- Shard-ready scope keys.
- Outbox event foundation.
- Idempotency-key foundation.
- Read-model refresh foundation.
- Table standards for future high-growth data.

## Install order

```text
database/stage_1_identity.sql
database/stage_1_repair_03M.sql
database/stage_1_security_hardening_03N.sql
database/stage_1_security_hardening_03N_3.sql
database/stage_1_high_volume_foundation_03O.sql
```

## Stage carry-forward rule

No future Stage 2+ product, gift, claim, order, payment, inbox, store, agent, or program table should be accepted without:

1. owner/scope key
2. public ID if externally referenced
3. hot-read index strategy
4. lifecycle category
5. object authorization rule
6. idempotency review for duplicate-sensitive writes
7. outbox review for slow side effects

## Known limitations

- This pass adds the foundation, not workers.
- Outbox processing is not active until a worker is added.
- Idempotency is available as a helper but must be used endpoint-by-endpoint.
- Actual physical sharding is intentionally deferred until measured load requires it.
