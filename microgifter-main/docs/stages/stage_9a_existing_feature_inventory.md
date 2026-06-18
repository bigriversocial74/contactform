# Stage 9A — Existing Feature and Dependency Inventory

## Purpose

This inventory maps the Microgift Engine to capabilities already delivered through Stages 1–8 so Stage 9 does not create duplicate systems.

## Identity, profiles, roles, and permissions

Already available:

- users, sessions, authentication, verification, and recovery
- public IDs and profile records
- roles and permissions
- audit logs and domain events
- CSRF protection and rate-limit patterns
- merchant, organization, enterprise, creator, and customer concepts

Stage 9 dependency:

Template ownership, issuance, merchant operations, administrative corrections, claims, and redemption must use these identities and permissions.

## Existing gifts and claims

Already available or partially available:

- `gifts`
- `gift_claims`
- sender and recipient relationships
- claim code last-four and failed-attempt tracking
- lock, verification, redemption, and expiration timestamps
- gift status and visibility
- legacy gift-to-PPPM mappings
- customer account sent, received, and redeemed views
- send and claim interface/API foundations

Stage 9 dependency:

These records contain useful production concepts but are not yet proven to be the full canonical template/instance engine. Stage 9 must classify them as adaptable records, migration sources, or compatibility surfaces before adding new gift tables.

## PPPM

Already available:

- sources and source events
- idempotent issuance requests
- permanent unit-level `pppm_items`
- sender, owner, recipient, merchant, and issuer relationships
- immutable title, value, currency, terms, and metadata snapshots
- assignment, delivery, claim, redemption, expiration, and lifecycle events
- merchant item list/detail operations
- account owned, purchased, sent, received, and redeemed views
- legacy gift mapping

Stage 9 dependency:

PPPM remains the canonical issued-unit and ownership identity. A Microgift gift instance may reference or produce PPPM items, but it must not replace them.

## Commerce, orders, and payments

Already available:

- published products and immutable versions
- server-authoritative carts
- frozen checkout drafts
- pending commerce orders
- payment intents and transactions
- receipts
- refunds and disputes
- paid-order PPPM issuance
- grouped financial ledger postings

Stage 9 dependency:

Purchased Microgifts must originate from verified commerce/order records. Gift issuance must not infer payment success from the client or duplicate order/payment records.

## Entitlements and protected assets

Already available:

- canonical entitlements linked to PPPM items and product assets
- refund and dispute access policy
- claim/ownership synchronization service
- protected delivery grants and access history
- merchant entitlement visibility

Stage 9 dependency:

Digital Microgift redemption or ownership transfer may trigger entitlement synchronization. Stage 9 does not create another access-right system.

## Product assets and digital products

Already available:

- products and immutable product versions
- product/version assets
- builder and merchant product management
- media and storage references
- protected access patterns

Stage 9 dependency:

A template may reference eligible published products, versions, offers, or assets. It should snapshot the commercial terms needed for a gift instance instead of copying the full product catalog.

## Profiles and organizations

Already available or partially available:

- user and merchant profiles
- organization/enterprise ownership concepts
- public profile/store direction
- customer and merchant account shells
- profile metrics and Future Demand design concepts

Stage 9 dependency:

Template ownership must support a person, artist, musician, creator, merchant, organization, or enterprise without assuming every owner is a conventional business.

## Locations

Already available or partially available:

- location-management concepts and merchant location records
- local product/merchant discovery foundations
- location-linked merchant activity direction

Stage 9 dependency:

Templates and gift instances may be globally valid, merchant-wide, or restricted to one or more locations. Stage 9 should reference canonical location IDs and must not duplicate address/location data.

## Agents and automation

Already available or partially available:

- saved agents
- agent skills/tools selections
- archive/delete lifecycle
- inbox, sent, claimed, and activity concepts
- scheduled and recurring gifting direction
- workplace and enterprise reward concepts

Stage 9 dependency:

Agents may later create or schedule Microgift instances through authorized APIs. Stage 9 should expose idempotent service contracts and events but should not implement a second agent execution system.

## Future Demand and analytics

Already available or partially available:

- engagement, distribution, demand, and snapshot foundations
- profile-level Future Demand concepts
- local/enterprise investment and committed-demand direction
- entitlement and access events
- commerce and financial event history

Stage 9 dependency:

Template creation, issuance, delivery, claim, redemption, expiration, replacement, and location usage should emit reliable source events. Stage 9 must not calculate predictive Future Demand scores; later stages consume the events.

## Main conclusion

The repository already contains approximately two-thirds of the supporting Microgift lifecycle across separate systems. Stage 9 should unify those systems through one template/instance contract, secure redeem-code rules, explicit orchestration, compatibility mapping, and policy tests rather than replacing the existing foundations.
