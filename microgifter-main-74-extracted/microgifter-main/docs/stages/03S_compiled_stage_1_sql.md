# 03S Compiled Stage 1 SQL

## Purpose

Compile the current Stage 1 SQL foundation into one fresh-install file for simpler HostGator/cPanel/phpMyAdmin setup.

## Added

```text
database/compiled/microgifter_stage_1_current_compiled.sql
docs/database/compiled_stage_1_sql_import_guide.md
```

## Source migrations compiled

```text
database/stage_1_identity.sql
database/stage_1_repair_03M.sql
database/stage_1_security_hardening_03N.sql
database/stage_1_security_hardening_03N_3.sql
database/stage_1_high_volume_foundation_03O.sql
database/stage_1_delivery_events_03R.sql
```

## Compilation notes

The compiled file is intended for a fresh database only.

The compile pass also normalized two issues discovered while reviewing the split migrations:

1. Later migrations expected a `permissions.description` column, so the compiled `permissions` table includes it from the start.
2. The active security-log endpoint requires `admin.security_logs.view`, so the compiled seed includes that permission in addition to the earlier `security.logs.view` permission.

## Fresh install command/path

For HostGator/phpMyAdmin, import:

```text
database/compiled/microgifter_stage_1_current_compiled.sql
```

For CLI MySQL:

```bash
mysql -u USER -p DATABASE_NAME < database/compiled/microgifter_stage_1_current_compiled.sql
```

## Existing database warning

Do not import the compiled file over an existing database. Use migrations for existing installs.

## Next recommendation

Add a lightweight SQL validation checklist after the first HostGator import:

- confirm all expected tables exist
- confirm required permissions exist
- confirm roles are seeded
- confirm delivery event types are seeded
- confirm `/api/health.php` works
