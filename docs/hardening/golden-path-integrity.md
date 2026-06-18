# PPPM Product Publish and Voucher Lifecycle Hardening Plan

## Product model

Microgifter's Post Purchase Product Management system (PPPM) lets a merchant create a digital voucher product that another user can:

- purchase and keep;
- claim;
- send to a friend or follower;
- present at the merchant location;
- have verified by a server or cashier against that merchant location and claim code;
- continue into tracked post-claim activity, including tips and messages.

The canonical lifecycle language is:

`issued / purchased / picked up -> delivered -> claimed -> verified -> tracked`

There is no separate `user-claimed` concept.

## First hardening target

The first PR will focus on:

`build.php -> publish product -> live digital voucher -> purchase/issuance-ready PPPM definition`

We will not change claim, verification, tipping, or messaging behavior until a merchant can reliably publish the voucher product that starts the lifecycle.

## What is already implemented

The builder currently supports:

- merchant-authenticated draft saving;
- builder templates and product metadata;
- immutable catalog product versions;
- product-version media assets;
- one `catalog_pppm_templates` row per published product version;
- atomic replacement publishing with prior versions retired;
- checkout-time PPPM issuance and Microgift creation;
- demo-like default content in the builder UI.

## Confirmed publish-path problems

### 1. Product category is ignored

The UI lets the merchant select values such as `Prepaid gift` or `Voucher`, but the API derives `catalog_products.product_type` only from the visual builder template. A `simple_product` is currently saved as `other`, even when the merchant selected `Voucher`.

### 2. Visibility is ignored during publish

The UI exposes `Draft`, `Private preview`, and `Published`, but the publish API always creates a published version and sets the product status to `published`.

### 3. Publish-success action targets missing markup

After publishing, JavaScript tries to place an `Add published product to cart` button inside `.mg-builder-canvas-header .mg-builder-preview-toolbar`. That container is not present in `build.php` or the builder header, so the post-publish action is not shown.

### 4. Published PPPM template is not connected to checkout issuance

Publishing creates `catalog_pppm_templates`, but checkout fulfillment does not load that row. Instead, payment fulfillment dynamically creates or finds a separate Microgift template/version from `catalog_product_versions` and then issues the purchased Microgift.

This appears to be unfinished stage-to-stage integration rather than a single isolated defect.

### 5. Demo defaults are not explicitly marked as demo

The builder opens with sample content such as `Coffee for two` and `Local Coffee House`. The publish payload has no explicit demo/test marker. Publishing those defaults can therefore create an ordinary live product unless we add safeguards or deliberately separate demo fixtures.

## Milestone 1 — Define the publish contract

Document and test exactly what `Publish Product` must create.

A successful live voucher publish should produce one connected product definition with:

1. a merchant-owned `catalog_products` row;
2. an immutable published `catalog_product_versions` row;
3. the selected product category mapped to the correct catalog product type;
4. media and terms attached to that version;
5. one active PPPM issuance template linked to the version;
6. a clear voucher/Microgift template definition used later by purchase and issuance;
7. visibility that matches the merchant's selection;
8. an audit record identifying the merchant, product, version, and PPPM template;
9. a stable product/version ID that can be added to a storefront or cart;
10. no issued voucher until a purchase, merchant grant, promotional pickup, game, contest, or API source requests issuance.

## Milestone 2 — Builder publish tests

Add failing tests covering:

- draft save and exact replay;
- stale lock rejection;
- category-to-product-type mapping;
- draft/private/published visibility behavior;
- immutable replacement versions;
- asset ownership and readiness validation;
- exactly one active PPPM template for the published version;
- publish replay/idempotency behavior;
- visible publish success action;
- cart/storefront eligibility only when policy allows it;
- rollback with no partial product, version, asset link, or PPPM template;
- demo fixtures never becoming live products accidentally.

## Milestone 3 — Minimal builder corrections

Make the smallest changes required to satisfy the publish contract:

- map the merchant's selected product category to `catalog_products.product_type`;
- implement or remove the misleading visibility selector;
- add the missing builder preview toolbar/status/action markup;
- return and display a reliable public product/version destination after publish;
- keep published versions immutable;
- preserve live versions while replacement drafts are edited;
- add an explicit demo/test marker or a clear blank-new-product mode;
- prevent accidental live publication of untouched demo defaults.

## Milestone 4 — Connect catalog publishing to PPPM issuance

Choose one canonical connection and test it end to end:

- the published catalog PPPM template must be the issuance definition used by checkout, merchant grants, promotions, and other sources; or
- the published product must create/link the canonical Microgift template/version at publish time, with PPPM issuance referring to that canonical definition.

The final implementation must not maintain two independent voucher definitions that can drift.

The connection must preserve:

- one immutable product-version snapshot per purchase;
- one PPPM unit per purchased voucher quantity;
- one linked Microgift/voucher instance per PPPM unit;
- merchant ownership of the product definition;
- purchaser or recipient ownership of issued units;
- idempotent purchase fulfillment.

## Milestone 5 — Demo voucher handling

Inventory current demo voucher records and classify them as one of:

- local fixture only;
- seeded demonstration product;
- live merchant-owned product;
- obsolete stage artifact.

Then:

- add an explicit `demo`/fixture marker in metadata or seed ownership;
- exclude demo records from live merchant metrics, storefronts, reconciliation, and financial reporting unless deliberately enabled;
- keep demo creation repeatable and removable;
- do not rewrite or delete possible live merchant products based only on sample titles.

## Milestone 6 — Voucher lifecycle integration

After publishing is reliable, validate the complete PPPM voucher path:

1. merchant publishes the voucher product;
2. user purchases or picks it up;
3. PPPM issues one unit per voucher;
4. buyer keeps it or sends it to a friend/follower;
5. delivery updates ownership and recipient access;
6. recipient claims the voucher;
7. server/cashier verifies it at an authorized merchant location using the claim code;
8. verification is tracked against the voucher, merchant, location, cashier/server actor, and claim code event;
9. post-claim tip and message actions become available at the agreed lifecycle point;
10. exact retries do not duplicate units, claims, verification records, tips, messages, or audit events.

## Validation and merge gate

Required before merge:

- builder publish contract tests pass;
- storefront product-management validator passes;
- checkout/fulfillment validator passes;
- published product and PPPM definitions are connected by one canonical path;
- demo voucher handling is documented and tested;
- `composer recovery-baseline` passes on a clean MySQL 8 database;
- Pull Request Validation is green;
- no existing migration SQL is modified;
- no unrelated lifecycle or UI redesign is included.

## Proposed commit sequence

1. `test: define PPPM voucher publish contract`
2. `fix: map builder category and visibility`
3. `fix: restore builder publish success controls`
4. `fix: connect published voucher definition to PPPM issuance`
5. `test: prove published voucher purchase and issuance`
6. `test: classify and isolate demo voucher fixtures`
7. `docs: record voucher publish and rollback procedure`

## Non-goals for this PR

- changing claim/verification semantics before the publish contract is stable;
- redesigning payments or ledger policy;
- redesigning the entire builder UI;
- deleting demo vouchers without identifying their provenance;
- broad naming or formatting refactors;
- changes to unrelated social, subscription, demand, or agent systems.
