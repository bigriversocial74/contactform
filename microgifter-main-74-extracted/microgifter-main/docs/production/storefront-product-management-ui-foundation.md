# Storefront and product management UI foundation

## Scope

This phase upgrades the existing merchant storefront and catalog operating surfaces without replacing the established Stage 4 and Stage 5 authorities.

The build is divided into four production sections:

1. Storefront identity, media, product placement, preview, and publishing
2. Product list, filters, pagination, status, and lifecycle operations
3. Product draft editor, pricing, media, readiness, versions, and publishing
4. Validation, regression coverage, and merchant workspace integration

## Canonical merchant routes

- `/merchant-storefront.php`
- `/merchant-storefront-preview.php`
- `/merchant-products.php`
- `/merchant-product.php?id=<public-product-id>`
- `/merchant-media.php`
- `/build.php?id=<public-product-id>` for the full creative builder

The merchant workspace navigation remains the canonical entry point. No new admin or catalog authority is introduced.

## Section 1 — Storefront management

The storefront workspace uses the existing versioned storefront authority:

- `merchant_storefronts`
- `merchant_storefront_revisions`
- `merchant_storefront_revision_products`
- `merchant_storefront_states`

The editor includes:

- store name and public slug
- headline and description
- logo and cover asset selection
- secure logo and cover uploads
- public contact details
- accent theme
- published-product selection
- featured state and display ordering
- live draft preview
- private full-page preview
- publish-readiness checks
- immutable published revision summary
- save, publish, archive, and unsaved-change protection

Publishing remains transactional. A new revision becomes public only after required identity fields and at least one visible published product are present. The prior published revision is retired rather than overwritten.

## Section 2 — Product management list

The product list uses the existing catalog authority and adds a bounded management projection.

Supported filters:

- search by title, slug, or public identifier
- catalog status
- product type
- builder type
- updated time, title, or value ordering

Results are limited to 50 records per page and use deterministic pagination. Search wildcard characters are escaped explicitly for MySQL.

Each result includes:

- current title, slug, type, and builder type
- current version and version status
- value and currency
- media count
- storefront placement count
- draft-change indicator
- last update time
- permission-aware management and archive actions

Archived products remain visible for historical and operational review. They cannot be edited or republished through the management UI.

## Section 3 — Product editor

The management editor uses the existing builder-draft authority rather than creating a second product editor schema.

Managed fields include:

- title and slug
- builder type and product category
- merchant and location labels
- headline and recipient message
- price and currency
- offer and visibility
- recipient, claim, collaboration, audio, and video labels
- expiration and terms
- cover, inside-cover, audio, and video assets

The editor provides:

- ready-asset selection
- secure media uploads
- draft lock version handling
- readiness checks
- unsaved-change protection
- current published record summary
- storefront placement count
- immutable version history
- current published media
- explicit publish and archive controls

The full creative builder remains available from the product record for layout-intensive editing.

## Live-product preservation

The existing builder-draft save behavior previously changed a published product back to `draft` while replacement edits were still in progress. That could remove a valid live product from storefront availability.

This phase corrects the lifecycle:

- saving a replacement builder draft preserves the current published product status and current immutable version
- the live slug and product type remain unchanged while the draft is incomplete
- publishing the replacement atomically creates a new immutable version, retires the prior published version, updates the product identity, and keeps the product published
- archived products remain non-editable

## Media authority

Uploads continue through `/api/catalog/upload.php` and private local storage.

The management UI adds supported roles for:

- storefront logo
- storefront cover
- product gallery images

Existing cover, inside-cover, audio, and video roles remain available. Uploads are authenticated, permission checked, CSRF protected, MIME inspected, size bounded, and stored privately. Owner previews use `/api/catalog/asset-file.php`.

## Safety and authority boundaries

- Existing catalog and storefront tables remain canonical
- No checkout, cart, order, ledger, PPPM ownership, or claim authority is duplicated
- Published storefront and product versions remain immutable
- All writes retain existing permission and CSRF enforcement
- Management controllers use DOM node projection rather than HTML-string injection
- Public links use canonical `/store.php` and `/product.php` routes
- Owner-only asset previews are never exposed as public publication authority
- Product archive continues to retire associated PPPM templates

## Validation

The focused workflow covers:

- PHP and JavaScript syntax
- complete ordered schema application
- real-MySQL storefront and product lifecycle behavior
- live-product preservation during replacement draft editing
- storefront readiness and asset projection
- immutable replacement version publishing
- storefront resolution of the current product version
- focused PHPUnit contracts
- frontend contracts
- complete repository PHPUnit suite
- focused Playwright storefront, product-list, and product-editor workflows
- complete browser regression suite

## Deferred

- bulk product actions
- permanent product deletion
- archived-product restoration
- advanced image cropping and transformations
- inventory quantity management
- shipping-rate configuration
- multi-storefront support
- product import and export
