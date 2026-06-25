# Design Studio release checklist

Use this checklist after the other branch is merged into `main` and this branch is updated from `main`.

## 1. Update branch

```bash
git checkout index-hero-section-update
git fetch origin
git merge origin/main
```

Resolve conflicts before importing SQL.

## 2. PHP lint

```bash
php -l design-studio.php
php -l qr.php
php -l api/merchant/_design_studio_guard.php
php -l api/merchant/_merchant.php
php -l api/merchant/brand-kit.php
php -l api/merchant/design-export.php
php -l api/merchant/design-studio-assets.php
php -l api/merchant/qr-library.php
php -l api/admin/design-studio-templates.php
```

## 3. Import Stage 19 SQL

```bash
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < database/stage_19_design_studio_qr_library.sql
```

## 4. Run smoke test

```bash
php tools/design-studio-smoke-test.php
```

The smoke test should report zero failures before browser testing.

## 5. Browser test

Open:

```text
/design-studio.php
```

Confirm:

- [ ] Page loads for merchant account
- [ ] Setup-required state is not shown after SQL import
- [ ] Sidebar link opens Design Studio
- [ ] Format switching updates the canvas
- [ ] Headline, offer, and CTA fields update the preview
- [ ] Brand scanner can scan a valid public website
- [ ] Brand palette and image candidates render
- [ ] QR library loads
- [ ] Create QR creates an active QR record
- [ ] Save project succeeds
- [ ] Save as template succeeds
- [ ] Generate proof queues an export job
- [ ] Export package queues an export job
- [ ] Queue QR image asset creates an asset/export job
- [ ] Campaign link saves with a campaign reference

## 6. Public QR test

After creating a QR code, open the payload URL from the QR library.

Confirm:

- [ ] Active QR redirects
- [ ] Draft QR does not redirect publicly
- [ ] Scan count increments
- [ ] Invalid short code returns not found

## 7. Database verification

```sql
SELECT migration_key, applied_at
FROM microgifter_schema_migrations
WHERE migration_group = 'design_studio'
ORDER BY applied_at DESC;

SELECT preset_key, name
FROM merchant_design_ai_presets
ORDER BY preset_key;

SELECT status, export_type, COUNT(*)
FROM merchant_design_export_jobs
GROUP BY status, export_type;
```

Expected migration key:

```text
stage_19_design_studio_qr_library
```

Expected AI presets:

```text
fitness-challenge
holiday-gift-card
live-event-promo
local-rewards-campaign
restaurant-food-promo
```

## 8. Known non-blocking limitation

The Design Studio can queue export jobs, but the renderer worker still needs to be implemented before final PDF/PNG/SVG/ZIP files are generated.

Do not present export rendering as complete until the worker exists and has been tested.
