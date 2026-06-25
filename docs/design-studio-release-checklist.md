# Design Studio release checklist

Use this checklist after the other branch is merged into `main` and this branch is updated from `main`.

## 1. Update branch

Use GitHub Desktop, your hosting Git integration, or the GitHub web UI to update/merge the branch after the other branch lands.

Confirm:

- [ ] The other branch is merged into `main`
- [ ] `index-hero-section-update` has been updated from `main`
- [ ] Any merge conflicts are resolved
- [ ] The branch deploys to staging

## 2. Import Stage 19 SQL without terminal

Use your hosting database tool, such as phpMyAdmin, Adminer, cPanel database tools, Plesk database tools, or your host's MySQL import screen.

Import this file:

```text
database/stage_19_design_studio_qr_library.sql
```

Confirm:

- [ ] The import finishes successfully
- [ ] No SQL error is reported by the database tool
- [ ] The `microgifter_schema_migrations` table exists
- [ ] The migration row `stage_19_design_studio_qr_library` exists

## 3. Browser smoke test

Open this URL while logged in as an admin:

```text
/api/admin/design-studio-smoke-test.php
```

Confirm the JSON response shows:

```json
"status": "passed"
```

and:

```json
"failed": 0
```

If the smoke test fails, fix the listed failures before browser testing `/design-studio.php`.

## 4. Browser test

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

## 5. Export queue admin check

After generating a proof or export package, open this URL while logged in as admin:

```text
/api/admin/design-export-worker.php
```

Confirm:

- [ ] The endpoint returns JSON
- [ ] `renderer_status` is `scaffold_ready`
- [ ] recent export jobs are listed
- [ ] queued/running/failed counts are visible when jobs exist

Important: this endpoint can monitor and claim worker jobs, but it does not render final files yet.

## 6. Public QR test

After creating a QR code, open the payload URL from the QR library.

Confirm:

- [ ] Active QR redirects
- [ ] Draft QR does not redirect publicly
- [ ] Scan count increments
- [ ] Invalid short code returns not found

## 7. Optional database verification

Use your database tool's SQL/query screen.

Check migration row:

```sql
SELECT migration_key, applied_at
FROM microgifter_schema_migrations
WHERE migration_group = 'design_studio'
ORDER BY applied_at DESC;
```

Check AI presets:

```sql
SELECT preset_key, name
FROM merchant_design_ai_presets
ORDER BY preset_key;
```

Check export queue rows after testing proof/export buttons:

```sql
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

## 8. Optional terminal checks for a developer

These are optional. Use only if someone helping you is comfortable with terminal commands.

```text
php -l design-studio.php
php -l qr.php
php -l api/merchant/_design_studio_guard.php
php -l api/merchant/_merchant.php
php -l api/merchant/brand-kit.php
php -l api/merchant/design-export.php
php -l api/merchant/design-studio-assets.php
php -l api/merchant/qr-library.php
php -l api/admin/design-studio-templates.php
php -l api/admin/design-studio-smoke-test.php
php -l api/admin/design-export-worker.php
php tools/design-studio-smoke-test.php
```

## 9. Known non-blocking limitation

The Design Studio can queue export jobs, and the admin worker scaffold can claim/release/fail jobs. The renderer still needs to generate final PDF/PNG/SVG/ZIP files.

Do not present export rendering as complete until final file rendering is implemented and tested.
