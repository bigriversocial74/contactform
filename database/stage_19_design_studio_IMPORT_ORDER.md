# Stage 19 Design Studio migration

Stage 19 uses **one authoritative SQL file** for this feature:

```text
 database/stage_19_design_studio_qr_library.sql
```

## No-terminal import path

Use your hosting database tool, such as phpMyAdmin, Adminer, cPanel database tools, Plesk database tools, or your host's MySQL import screen.

General steps:

1. Open your database tool.
2. Select the Microgifter database.
3. Open the import screen.
4. Choose this file from the deployed codebase or your local copy:

```text
database/stage_19_design_studio_qr_library.sql
```

5. Run/import the file.
6. After import finishes, open this URL while logged in as an admin:

```text
/api/admin/design-studio-smoke-test.php
```

The smoke-test URL returns JSON. Look for:

```json
"status": "passed"
```

and:

```json
"failed": 0
```

## What the migration installs

The single SQL file creates and seeds the full Design Studio foundation:

- `microgifter_schema_migrations`
- `merchant_qr_codes`
- `merchant_qr_code_scans`
- `merchant_brand_kits`
- `merchant_brand_kit_assets`
- `merchant_design_templates`
- `merchant_design_template_reviews`
- `merchant_design_projects`
- `merchant_design_ai_jobs`
- `merchant_design_ai_presets`
- `merchant_design_assets`
- `merchant_design_export_jobs`
- `merchant_design_campaign_links`
- Design Studio / QR / brand kit / asset / template / AI permissions
- System AI prompt presets

## Browser smoke test

Open this URL after importing the SQL:

```text
/api/admin/design-studio-smoke-test.php
```

The smoke test checks:

- required files exist
- database connection works
- required Stage 19 tables exist
- migration ledger has `stage_19_design_studio_qr_library`
- required permissions exist
- seeded AI presets exist
- export queue reliability columns exist
- campaign link uniqueness column exists
- admin export worker endpoint file exists

## Export queue monitor

After a merchant queues a proof or export package in `/design-studio.php`, open this admin URL:

```text
/api/admin/design-export-worker.php
```

It returns JSON with queue counts and recent jobs.

Expected renderer status for this build:

```json
"renderer_status": "scaffold_ready"
```

That means the queue can be monitored and claimed, but final PDF/PNG/SVG/ZIP rendering still needs the renderer implementation.

## Manual database verification, optional

Use your database tool's SQL/query screen and run:

```sql
SELECT migration_key, applied_at
FROM microgifter_schema_migrations
WHERE migration_group = 'design_studio'
ORDER BY applied_at DESC;
```

Expected Stage 19 row:

```text
stage_19_design_studio_qr_library
```

Confirm AI presets:

```sql
SELECT preset_key, name
FROM merchant_design_ai_presets
ORDER BY preset_key;
```

Expected presets:

```text
fitness-challenge
holiday-gift-card
live-event-promo
local-rewards-campaign
restaurant-food-promo
```

## Full release checklist

Use this checklist for staging and merge validation:

```text
docs/design-studio-release-checklist.md
```

## Notes

- This migration is intended for a fresh Stage 19 install.
- If an older Stage 19 draft was partially imported, compare existing table definitions before running this file against production.
- The Design Studio page and APIs include setup guards. If required tables are missing, the page shows a setup-required state and APIs return a clean setup error.
- Export jobs queue records and can now be monitored/claimed by the admin worker scaffold. Final file rendering is still a separate build step.
