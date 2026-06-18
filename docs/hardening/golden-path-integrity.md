# PPPM Product Publish and Distribution Hardening Plan

## Product model

Microgifter's Post Purchase Product Management system (PPPM) lets a merchant create a digital voucher product that another user can purchase, claim, keep, or send to a friend or follower. The recipient visits the merchant, presents the voucher, and a server or cashier verifies it against the merchant location and claim code. The verified voucher then supports tracked post-claim activity such as tips and messages.

The canonical lifecycle language is:

`issued / purchased / picked up -> delivered -> claimed -> verified -> tracked`

There is no separate `user-claimed` concept.

## First hardening target

The first PR will focus on:

`build.php -> publish product -> merchant store + feed + location discovery -> purchase/issuance-ready PPPM definition`

Publishing is a merchant distribution action. It must never prompt the merchant to purchase or add their own product to a cart.

## Correct post-publish behavior

A successful public publish must:

1. create or replace the immutable catalog product version;
2. create/link the canonical PPPM voucher issuance definition;
3. add the product to the merchant's public store;
4. create or update a product-linked feed post for the merchant;
5. expose the product through general location/category search at the selected active merchant locations;
6. return public destinations for `View product`, `View store`, and `View feed post`;
7. make customer purchase actions available on public product/store/feed/search surfaces;
8. never add the product to the publishing merchant's cart;
9. never issue an individual voucher until a purchase, pickup, grant, promotion, contest, game, or API source requests issuance.

## Current implementation findings

### 1. Merchant self-cart behavior is wrong

After publish, builder JavaScript attempts to create an `Add published product to cart` button. This is not part of the merchant publish workflow and must be removed. It appears to be a stage-era cart integration shortcut rather than intended product behavior.

### 2. Product category is ignored

The UI lets the merchant select values such as `Prepaid gift` or `Voucher`, but the API derives `catalog_products.product_type` only from the visual builder template. A `simple_product` is currently saved as `other`, even when the merchant selected `Voucher`.

### 3. Visibility is ignored

The UI exposes `Draft`, `Private preview`, and `Published`, but the publish API always creates a published version and sets the product status to `published`.

### 4. Store distribution is incomplete

A published product becomes available to the storefront-management system, but it is not automatically added to the merchant's active public storefront revision. Publishing therefore does not guarantee that the product appears in the merchant's store.

### 5. Feed distribution is not connected

The social publishing system supports product-linked feed posts through `feed_posts.catalog_product_id`, but the builder publish transaction does not create or update a feed post.

### 6. Location discovery is profile-level, not product-level

Current discovery can rank/filter merchant profiles by profile location and published-product category. It does not publish a specific voucher as a location-search result. The builder currently sends a free-text location label rather than canonical `merchant_locations` IDs.

### 7. Published PPPM definition is not connected to checkout issuance

Publishing creates `catalog_pppm_templates`, but checkout fulfillment does not consume that row. Payment fulfillment independently creates or finds a Microgift template/version from the catalog version. These parallel definitions can drift.

### 8. Demo defaults are not explicitly marked as demo

The builder opens with sample content such as `Coffee for two` and `Local Coffee House`. Publishing untouched defaults can create an ordinary live product because there is no explicit demo/fixture marker.

## Milestone 1 — Define and test the publish contract

Add failing tests proving that public publication atomically creates one connected merchant voucher definition and distribution result:

- merchant-owned catalog product;
- immutable published version;
- correct voucher/category mapping;
- media, terms, value, currency, and expiration snapshot;
- one canonical active PPPM issuance definition;
- public product page destination;
- product included in the merchant's active store;
- one idempotent product-linked merchant feed post;
- product indexed against selected active merchant locations;
- no merchant cart mutation;
- no issued voucher before an issuance source acts;
- exact replay does not duplicate versions, templates, store entries, feed posts, or search entries;
- failure rolls back every publish/distribution write.

## Milestone 2 — Correct the builder UI contract

- remove `Add published product to cart` and all related builder event handling;
- replace it with `View product`, `View store`, and `View feed post` actions;
- map selected product category to the canonical catalog type;
- implement the visibility choices accurately;
- replace or augment the free-text location field with selected active merchant-location IDs;
- default new voucher products to the merchant's primary active location, with an explicit all-locations option;
- keep public purchase controls on customer-facing pages, not inside the merchant builder;
- prevent accidental live publication of untouched demo defaults.

## Milestone 3 — Store distribution

When a product becomes public:

- ensure the merchant has a public storefront;
- add the product to the storefront's visible product set exactly once;
- preserve existing product order and featured choices;
- create a replacement immutable storefront revision rather than mutating published history in place;
- publish the replacement revision atomically with the product distribution operation;
- remove or hide the product through a corresponding revision when the product is archived or made private.

## Milestone 4 — Feed distribution

- create one merchant-authored product post linked through `feed_posts.catalog_product_id`;
- derive the post presentation from the published immutable product version;
- use the merchant's intended visibility;
- make republishing update or supersede the product post without duplicates;
- keep the feed post as presentation only—the catalog/PPPM definition remains authoritative;
- expose customer purchase/send actions from the linked product attachment.

## Milestone 5 — Location search distribution

- associate the published product with one or more canonical active `merchant_locations` records;
- index product title, category, merchant, city, region, postal code, and location identity;
- return product-level results, not only merchant-profile results;
- exclude inactive/archived locations, private products, drafts, and demo fixtures;
- ensure verification remains restricted to authorized merchant locations even when a product is discoverable at multiple locations.

A schema addition may be required for an explicit product-version-to-location association. Any migration must be additive and entered through the canonical migration manifest.

## Milestone 6 — Connect catalog publishing to PPPM issuance

Choose and enforce one canonical voucher definition:

- either the published `catalog_pppm_templates` record becomes the issuance definition consumed by checkout, grants, promotions, and pickups; or
- publish creates/links the canonical Microgift template/version once, and PPPM issuance references that canonical definition.

The implementation must not retain two independent voucher definitions that can drift.

The connection must preserve:

- one immutable product-version snapshot per purchase;
- one PPPM unit per voucher quantity;
- one linked voucher/Microgift instance per PPPM unit;
- merchant ownership of the product definition;
- purchaser or recipient ownership of issued units;
- idempotent fulfillment.

## Milestone 7 — Demo voucher handling

Inventory current demo voucher records and classify each as:

- local fixture only;
- seeded demonstration product;
- live merchant-owned product;
- obsolete stage artifact.

Then:

- add an explicit demo/fixture marker in metadata or seed ownership;
- exclude demo records from public store, feed, location discovery, merchant metrics, reconciliation, and financial reporting unless deliberately enabled;
- keep demo creation repeatable and removable;
- do not rewrite or delete possible live merchant products based only on sample titles.

## Validation and merge gate

Required before merge:

- builder publish contract tests pass;
- no builder self-cart behavior remains;
- published voucher appears in store, feed, and location search exactly once;
- public customer surfaces retain purchase actions;
- storefront product-management validator passes;
- social feed publishing validator passes;
- profile/location discovery validators pass;
- checkout/fulfillment validator passes;
- published product and PPPM issuance use one canonical definition;
- demo voucher handling is documented and tested;
- `composer recovery-baseline` passes on a clean MySQL 8 database;
- Pull Request Validation is green;
- no existing migration SQL is modified;
- no unrelated lifecycle or UI redesign is included.

## Proposed commit sequence

1. `test: define PPPM publish and distribution contract`
2. `fix: remove merchant self-cart publish action`
3. `fix: map builder category visibility and locations`
4. `feat: distribute published voucher to merchant store`
5. `feat: publish product-linked merchant feed post`
6. `feat: index voucher by canonical merchant locations`
7. `fix: connect published voucher definition to PPPM issuance`
8. `test: prove public distribution and customer purchase path`
9. `test: classify and isolate demo voucher fixtures`
10. `docs: record publish rollback and unpublish procedure`

## Non-goals for this PR

- changing claim/verification semantics before publishing is stable;
- redesigning payments or ledger policy;
- redesigning the entire builder UI;
- deleting demo vouchers without identifying their provenance;
- broad naming or formatting refactors;
- changes to unrelated subscription, demand, or agent systems.
