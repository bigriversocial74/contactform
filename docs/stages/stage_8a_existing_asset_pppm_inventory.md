# Stage 8A Existing Asset and PPPM Inventory

## Purpose

Identify what already exists before Stage 8 adds entitlements or downloads. Stage 8 should adapt existing ownership and asset records instead of duplicating them.

## Product and asset foundation

Existing work already includes products, immutable product versions, product media, published snapshots used by checkout, merchant product management, and PPPM-scoped media delivery.

Stage 8 should reuse these asset records and add access relationships where needed. It should not create a second asset catalog.

## Permanent purchase chain

The existing chain is:

`Published Product Version -> Cart -> Checkout Draft -> Commerce Order Item -> Paid Order -> PPPM Issuance Request -> PPPM Item`

Important records include:

- commerce orders and order items
- receipts
- PPPM sources and source events
- PPPM issuance requests
- PPPM items

The commerce order item is the purchased line identity. The PPPM item is the issued unit identity. Future entitlements should reference these permanent records, not cart rows or payment transaction IDs.

## Customer library implemented early

Stage 6B added customer item scopes for:

- owned
- purchased
- sent
- received
- redeemed

The account API reads PPPM items, issuance requests, order items, and orders. This is an early owned-library read model that Stage 8 should preserve and extend.

## Gift and claim foundation

Existing flows include gifts, gift claims, PPPM legacy gift mapping, sender and recipient views, claim verification, redemption, and PPPM item lifecycle events.

Stage 8 must define access behavior for sent, received, claimed, transferred, redeemed, expired, refunded, and disputed items.

## PPPM foundation

PPPM already provides source ingestion, idempotent issuance, unit-level items, owner and recipient fields, immutable item snapshots, lifecycle timestamps, merchant operations, customer views, event recording, and media relationships.

This means Stage 8 should not create a replacement owned-item table. The likely missing layer is an entitlement and access-grant model linked to PPPM items and product assets.

## Stage 7 inputs

Stage 7 provides paid orders, refunds, disputes, and immutable internal financial records. Stage 8 must not calculate wallet balances. Refund and dispute state should influence entitlement access through explicit policy and events.

## Security patterns to inherit

- authenticated API access
- object ownership checks
- administrative permissions
- CSRF protection for writes
- idempotent state creation
- public IDs at API boundaries
- hidden storage paths
- controlled asset delivery
- audit records and domain events
- server-authoritative transitions

## Inventory conclusion

- Product assets: implemented early
- Permanent purchase identity: implemented early
- PPPM unit identity: implemented early
- Customer owned-item list: implemented early
- Gift and claim lifecycle: partially implemented
- Protected asset entitlement: missing
- Entitlement revocation policy: missing
- Controlled download authorization and history: missing
- Unified library details combining PPPM, entitlement, and assets: partial

Stage 8 should be an integration and access-control stage, not a commerce or ownership rebuild.
