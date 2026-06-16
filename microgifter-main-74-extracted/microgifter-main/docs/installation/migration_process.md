# Microgifter Migration Process

## Purpose

The Stage 1 migration command provides an ordered, repeatable way to apply SQL files and record them in the `schema_migrations` table.

## Command

From the project root, run:

```bash
php scripts/run_migrations.php
```

## Current order

1. `database/stage_1_identity.sql`
2. `database/stage_1_repair_03M.sql`
3. `database/stage_1_security_hardening_03N.sql`
4. `database/stage_1_security_hardening_03N_3.sql`

## Rules

- Back up the database first.
- Do not edit a migration after it has been applied.
- Create a new migration for every future schema change.
- A checksum mismatch should stop deployment until reviewed.
- Keep operational scripts blocked from browser access.
