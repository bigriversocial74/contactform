# Stage 4 Plan Reconciliation and Coding Kickoff

## Purpose

Stage 4 begins after the Stage 3 PPPM foundation expanded beyond the original gifting-only assumptions. The original Stage 4 direction—assets and digital products—remains valid, but it must now be built as a consumer of PPPM rather than as a parallel lifecycle.

## Reconciled model

- PPPM owns source-neutral identity, issuance, assignment, delivery, transfer, claim, verification, redemption, audit history, and future-demand facts.
- Microgifting owns the gifting presentation, builder, card experience, Inbox, Sent, Claimed, messaging, merchant tools, and distribution workflows.
- Stage 4 owns reusable product definitions, product versions, digital assets, fulfillment rules, publishable merchant catalog records, and mappings into PPPM issuance templates.

## Original Stage 4 intent retained

1. Merchant-owned products and digital products.
2. Reusable media and downloadable assets.
3. Draft, review, publish, archive, and version states.
4. Product pages and merchant storefront/profile pages.
5. Builder integration with persisted product definitions.
6. Product value, terms, expiration, eligibility, and redemption configuration.
7. API-ready issuance through purchases, merchant grants, games, contests, fundraising, workplace rewards, and external systems.

## Adjustments caused by Stage 3 deviations

The following moved into Stage 3 and must not be rebuilt in Stage 4:

- permanent per-unit IDs;
- quantity expansion into individual PPPM units;
- source-event idempotency;
- assignment and transfer;
- delivery scheduling and attempts;
- merchant-location verification;
- redemption records;
- item lifecycle events and snapshots;
- future-demand fact foundations.

Stage 4 product records define what may be issued. PPPM items represent individual issued units.

## Stage 4 package sequence

1. **4A Product Catalog and Asset Foundation**
2. **4B Builder Persistence and Product Publishing**
3. **4C Product Pages and Merchant Storefronts**
4. **4D Digital Fulfillment and Secure Downloads**
5. **4E Distribution Programs and External Source Adapters**
6. **4F Product Analytics and Future-Demand Intelligence Views**

## Package 4A scope

- merchant product definitions;
- immutable product versions;
- reusable asset metadata;
- product-to-asset ordering and presentation roles;
- digital fulfillment configuration;
- product lifecycle permissions;
- source-neutral PPPM issuance template mapping;
- non-destructive compatibility with existing builder and gift records;
- regression coverage and CI migration execution.

## Domain boundaries

### Product definition

A reusable merchant-controlled offer, product, prize, reward, voucher, entitlement, reservation, credit, or other issueable unit.

### Product version

An immutable publish snapshot containing title, description, value, currency, terms, expiration policy, fulfillment rules, and metadata.

### Asset

A reusable media or downloadable resource. Stage 4 stores metadata and secure storage references, never provider credentials or public filesystem assumptions.

### PPPM issuance template

Maps a published product version into normalized PPPM issuance fields. It does not create an issued unit until a purchase, free grant, API event, contest, game, or other source requests issuance.

## Stage 4A completion gate

- clean migration;
- merchant ownership checks;
- immutable published versions;
- safe asset metadata and storage references;
- product-to-PPPM template mapping;
- archive without destructive history loss;
- no duplication of Stage 3 lifecycle logic;
- all Security Foundation checks green.

## Deferred beyond 4A

- object-storage upload signing;
- image transformations and transcoding;
- storefront rendering;
- checkout integration;
- secure download streaming;
- external provider adapters;
- forecasting dashboards.
