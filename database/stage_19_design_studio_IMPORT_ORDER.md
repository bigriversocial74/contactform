# Stage 19 Design Studio migration

Stage 19 now uses **one authoritative SQL file** for this feature:

```text
 database/stage_19_design_studio_qr_library.sql
```

Run it once on staging after this branch is merged/updated and before opening `/design-studio.php`:

```bash
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < database/stage_19_design_studio_qr_library.sql
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

## Verify import

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

Confirm core tables:

```sql
SHOW TABLES LIKE 'merchant_qr_codes';
SHOW TABLES LIKE 'merchant_brand_kits';
SHOW TABLES LIKE 'merchant_design_templates';
SHOW TABLES LIKE 'merchant_design_projects';
SHOW TABLES LIKE 'merchant_design_assets';
SHOW TABLES LIKE 'merchant_design_export_jobs';
SHOW TABLES LIKE 'merchant_design_ai_presets';
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

## Notes

- This migration is intended for a fresh Stage 19 install.
- If an older Stage 19 draft was partially imported, compare existing table definitions before running this file against production.
- The Design Studio page and APIs include setup guards. If required tables are missing, the page shows a setup-required state and APIs return a clean setup error.
- Export jobs queue records only. A renderer worker still needs to convert queued jobs into final PDF/PNG/SVG/ZIP assets.
