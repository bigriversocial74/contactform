# Stage 19 Design Studio import order

Use the split migrations below for staging and production. They are easier to review, retry, and debug than the original combined Stage 19 draft.

Run in this exact order:

```bash
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < database/stage_19a_design_studio_migration_tracking.sql
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < database/stage_19b_design_studio_qr_library.sql
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < database/stage_19c_design_studio_brand_kits.sql
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < database/stage_19d_design_studio_templates_projects.sql
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < database/stage_19e_design_studio_ai_queue.sql
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < database/stage_19f_design_studio_assets_exports_campaigns.sql
```

## What each migration does

### 19a — migration tracking
Creates `microgifter_schema_migrations` so Stage 19 imports are auditable.

### 19b — QR library
Creates merchant QR records and privacy-preserving scan analytics.

### 19c — brand kits
Creates merchant brand kits plus website-scanned logo/image candidate records.

### 19d — templates and projects
Creates reusable print/social/digital templates, template review history, and saved merchant projects. Adds version-family fields and project revision tracking.

### 19e — AI queue
Creates AI prompt presets and a reliable AI generation queue with attempt, lock, retry, failure, and worker metadata fields.

### 19f — assets, exports, campaign links
Creates production/candidate assets with lifecycle fields, export jobs with retry/lock/failure fields, and campaign links with a generated uniqueness hash.

## Verify import

```sql
SELECT migration_key, applied_at
FROM microgifter_schema_migrations
WHERE migration_group = 'design_studio'
ORDER BY migration_key;
```

Expected rows:

```text
stage_19a_design_studio_migration_tracking
stage_19b_design_studio_qr_library
stage_19c_design_studio_brand_kits
stage_19d_design_studio_templates_projects
stage_19e_design_studio_ai_queue
stage_19f_design_studio_assets_exports_campaigns
```

## Notes

- These migrations are intended for a fresh Stage 19 install.
- If an older combined Stage 19 draft was partially imported, do not blindly re-run these files on production. Compare the existing table definitions first.
- The Design Studio page and APIs include setup guards. If required tables are missing, the page will show a setup-required state and APIs will return a clean setup error.
- Export jobs queue records only. A renderer worker still needs to convert queued jobs into final PDF/PNG/SVG/ZIP assets.
