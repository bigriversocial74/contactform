# Admin merchant and catalog operations center

The protected workspace at `/merchant-catalog-operations.php` provides one administrative view across merchant workspaces, storefronts, catalog products, product versions, placements, and catalog assets.

## Operational domains

- merchant account, profile, eligibility, onboarding, payment-readiness, location, and team context;
- storefront draft and published revisions, readiness checks, and product placements;
- catalog product versions, builder drafts, PPPM templates, feed distribution, pricing, and media;
- catalog asset processing state, ownership, linked products/storefronts, and orphan detection.

The center reads from the canonical merchant, storefront, builder, catalog, asset, feed, and publishing tables. It does not create an alternate builder or product-version pipeline.

## Permissions

- `admin.merchants.view` — inspect merchant workspaces and storefronts;
- `admin.merchants.manage` — activate, review, suspend, restore, or archive workspaces and storefronts;
- `admin.catalog.view` — inspect products, versions, placements, drafts, and assets;
- `admin.catalog.manage` — review, publish, pause, restore, or archive products and quarantine, retry, or archive assets.

Stage 18M grants all four permissions to the `admin` and `super_admin` roles.

## Lifecycle safeguards

- storefront publication uses the existing storefront revision and readiness helpers;
- product publication only restores an existing ready current version; new immutable versions remain the responsibility of the canonical builder publish flow;
- suspending a workspace suspends its storefront and pauses its published products;
- archiving a workspace archives its storefront and non-archived products;
- quarantining a linked asset pauses affected published products and suspends affected published storefronts;
- writes require CSRF validation, rate limiting, database transactions, an action reason, and user confirmation;
- successful actions create an operation event, audit log, domain event, and security log.

## Stage 18M schema

The migration adds:

- the missing `suspended` storefront state already enforced by the storefront service;
- the `paused` product state for reversible administrative takedowns;
- `merchant_catalog_operation_events` for bounded administrative lifecycle history;
- the dedicated merchant and catalog administrative permissions.

## Deployment

Run the canonical migration runner after deployment:

```bash
php scripts/run_migrations.php
```

This applies `database/stage_18m_admin_merchant_catalog_operations.sql` through `config/migrations.php`.
