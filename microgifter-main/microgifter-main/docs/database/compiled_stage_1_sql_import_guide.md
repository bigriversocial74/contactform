# Compiled Stage 1 SQL Import Guide

## Fresh install path

For a brand-new database, import this single compiled file:

```text
database/compiled/microgifter_stage_1_current_compiled.sql
```

This compiled file includes the current Stage 1 foundation through 03R:

```text
Stage 1 identity foundation
03M Stage 1 repair tables
03N security/stability hardening
03N-3 session-management permissions
03O high-volume data foundation
03R delivery event contracts
```

## Existing database path

If the database already has Stage 1 tables, do not import the compiled file on top of it. Use the ordered migrations instead:

```text
database/stage_1_identity.sql
database/stage_1_repair_03M.sql
database/stage_1_security_hardening_03N.sql
database/stage_1_security_hardening_03N_3.sql
database/stage_1_high_volume_foundation_03O.sql
database/stage_1_delivery_events_03R.sql
```

## Why this compiled file exists

The compiled SQL is for HostGator/cPanel/phpMyAdmin installs where importing one file is easier and less error-prone than importing six separate migrations.

## Important compatibility fix included

During compilation, the `permissions` table was normalized to include `description VARCHAR(255) NULL`, because later migrations insert permission descriptions. The compiled file also includes the `admin.security_logs.view` permission required by the active security log endpoint.

## After import

1. Configure environment/database settings.
2. Load `/api/health.php`.
3. Register a test user.
4. Promote the trusted owner/admin using the first-run admin setup guide.
5. Confirm `/account.php` loads.
6. Confirm protected admin endpoints reject normal users.

## Rule going forward

After each major schema pass, update this compiled file or create a new compiled snapshot so HostGator/cPanel installs stay simple.
