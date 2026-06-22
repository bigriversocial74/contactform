# Stage API-11 — System health migration recovery hardening

Stage API-11 starts the production hardening pass by making the System Health critical migration state actionable.

## Problem

The admin System Health page can show Database migrations as critical with a missing migration count, but the previous view did not expose the missing migration file names or provide an operator-safe recovery path from the dashboard.

## What changed

- Database migration health now reports missing migration file names in the service details.
- Migration health includes the canonical recovery command: `php scripts/run_migrations.php`.
- The System Health API exposes a protected `migration_plan` recovery action when migration health is not healthy.
- The action is read-only. It returns the missing files and runner command, and it does not execute DDL from the browser.
- The admin System Health UI now includes a **Prepare migration recovery** button.
- The System Health JavaScript displays a clear result with the missing count and migration runner command.

## Operator command

Run the canonical migration runner from the application root:

```bash
php scripts/run_migrations.php
```

If production requires separate migration database credentials, set:

```bash
MG_MIGRATION_DB_USER="..."
MG_MIGRATION_DB_PASS="..."
php scripts/run_migrations.php
```

## Safety

The dashboard action is intentionally read-only. Applying schema changes remains a CLI/operator task so DDL is not executed through a browser request.
