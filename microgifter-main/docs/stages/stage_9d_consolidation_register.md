# Stage 9D Consolidation Register

| Surface | Canonical source | Compatibility source | Decision |
|---|---|---|---|
| Reusable gift definition | `microgift_templates` and immutable versions | none | Canonical |
| Issued gift contract | `microgift_instances` | `gifts` | Keep legacy read compatibility until approved backfill |
| Claim credential | `microgift_credentials` | `gift_claims` code fields | New issuance uses canonical hash-only credentials |
| Claim completion | `microgift_claims` | `gift_claims` | Preserve legacy records; map through review/backfill |
| Redemption | `microgift_redemptions` | legacy gift claim redemption timestamps | Canonical for Stage 9 instances |
| Unit identity and ownership | `pppm_items` | none | Canonical; never replace with Microgift instance |
| Protected digital access | `entitlements` | none | Canonical |
| Paid purchase | commerce order/item/payment records | none | Canonical |
| Internal money | Stage 7 grouped ledger | none | Canonical |
| Product terms/assets | product/version/asset catalog | gift snapshots | Catalog canonical; instance snapshots remain immutable history |
| Locations | canonical merchant/location records | location strings in historical records | IDs/policies canonical; historical display references retained |
| Customer library | PPPM/account views plus `/api/account/microgifts.php` | legacy gift views | Extend; do not replace PPPM account scopes |
| Merchant operations | `/api/merchant/microgifts.php` and daily metrics | legacy merchant gift lists | Canonical for Stage 9 activity |
| Admin review | Microgift review queue and inspection timeline | manual SQL review | Canonical operational path |
| Future Demand input | append-only events and daily source aggregates | ad hoc counts | Canonical source facts; scoring deferred |

## Deletion decision

No legacy gift table or endpoint is deleted in Stage 9D. The repository may contain old UI/API callers and imported historical data. Deletion requires:

1. a complete caller inventory,
2. approved backfill rules,
3. zero unresolved compatibility review items,
4. regression coverage for account, merchant, claim, and redemption flows,
5. a separate compatibility-removal PR.

This is deliberate consolidation, not unfinished cleanup.
