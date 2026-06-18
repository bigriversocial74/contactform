# Stage 9E-3 — Early Install Upgrade Manifest and CI Alignment

## Current deployment context

The live server has an early Stage 1 install. It can create accounts and users can log in. Only a very small number of accounts exist, and the Stage 2–9 code has not been uploaded yet.

That means this is not a blank install, but it is also not a full production migration with real commerce, PPPM, entitlement, Microgift, or lifecycle data.

## Deployment style

The expected deployment style is:

1. download the latest repository zip,
2. upload it to the server,
3. extract it over the existing codebase,
4. update environment/config values,
5. run additive stage scripts,
6. run smoke checks,
7. verify existing login still works.

This is acceptable for the current phase as long as the database is backed up first and the database is not dropped or recreated.

## What must be preserved

Do not wipe or rebuild these records:

- `users`
- `roles`
- `permissions`
- `role_permissions`
- session/account/auth related records

The current two accounts are not a migration burden, but they are useful proof that the Stage 1 foundation remains intact.

## Safe upgrade sequence

Run from the project root after upload/extract:

```bash
php scripts/stage9e3_preflight.php
composer migrate
php scripts/run_stage3_delivery.php
php scripts/run_stage4_product_assets.php
php scripts/run_stage4b_builder.php
php scripts/run_stage4c_feed_stream.php
php scripts/stage4d.php
php scripts/stage4e.php
php scripts/stage4f.php
php scripts/stage5a.php
php scripts/stage5c.php
php scripts/stage5d.php
php scripts/stage5e.php
php scripts/stage5f.php
php scripts/stage5g.php
php scripts/stage5h.php
php scripts/stage5i.php
php scripts/stage5j.php
php scripts/stage7b.php
php scripts/stage8b.php
php scripts/stage9b.php
php scripts/stage9d.php
php scripts/stage9e3_smoke.php
```

`php scripts/stage9e3_upgrade_manifest.php` prints the same sequence as JSON for operational reference.

## Zip upload/extract notes

When extracting the zip over the existing files:

- back up the current files first,
- back up the database first,
- keep any server-specific `.env`, `api/config.php`, uploaded media, and storage files that are not tracked by the repo,
- do not overwrite production secrets with local placeholders,
- set file permissions after extraction if the host requires it,
- stop immediately if any migration or smoke command fails.

## Stage 10 gate

Stage 10 should begin only after:

- Stage 9E-3 PR validation is green,
- Stage 9E-3 main regression is green,
- the live early install has been backed up,
- the latest merged code has been uploaded/extracted,
- preflight passes,
- additive migrations run,
- smoke checks pass,
- existing login still works.
