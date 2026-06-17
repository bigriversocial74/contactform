# Stage 8B Adapted Entitlement, Library, and Download Build Plan

## Objective

Add the missing entitlement and protected-access layer around the existing product assets, commerce orders, PPPM items, gifts, claims, customer account views, and Stage 7 financial policies.

Stage 8B must not create a second purchase, ownership, product asset, receipt, or wallet system.

## Phase 1 — Canonical entitlement schema

Add one entitlement model with relationships to:

- PPPM item
- commerce order item when applicable
- product and immutable product version
- protected product asset
- entitled user
- source event or policy reason

Required properties:

- public ID
- entitlement type
- status
- source type and source reference
- idempotency key
- starts and expires timestamps
- revoked/suspended timestamps and reasons
- created/updated timestamps

Recommended uniqueness:

- one active entitlement per PPPM item, entitled user, asset, and entitlement type
- unique source idempotency key

## Phase 2 — Entitlement service

Responsibilities:

- create grants from verified PPPM ownership
- return existing grants for duplicate source keys
- verify current owner/recipient access
- suspend, revoke, restore, or expire through explicit transitions
- record audit and domain events
- never infer ownership solely from client input

## Phase 3 — Purchase issuance integration

Extend paid-order PPPM issuance so eligible digital product assets receive entitlement grants.

Rules:

- payment capture remains the financial trigger
- PPPM issuance remains the permanent unit trigger
- entitlement creation occurs from the issued PPPM item and immutable product version
- repeated capture or fulfillment calls cannot duplicate grants

## Phase 4 — Gift, transfer, and claim policy

Define one policy for when access belongs to:

- original purchaser
- sender
- named recipient
- claimant
- final owner

Recommended rule:

- an unclaimed gift may preserve sender management rights without granting duplicate content access
- entitlement moves or is reissued only through an explicit PPPM ownership/claim event
- historical grants remain recorded but inactive after transfer

## Phase 5 — Refund and dispute policy

Define explicit behavior:

- full refund: revoke affected entitlements
- partial refund: revoke only refunded units/assets where determinable; otherwise create a review item
- dispute opened: suspend access when policy requires
- dispute resolved for merchant: restore access if otherwise valid
- dispute resolved for customer: revoke access

Financial records remain unchanged by Stage 8. Entitlement transitions observe payment/refund/dispute state and emit their own events.

## Phase 6 — Protected asset authorization

Add a service and endpoint that:

1. authenticates the requester
2. resolves the public asset ID
3. resolves a valid entitlement
4. checks entitlement, PPPM item, product, and asset status
5. applies expiration and optional use limits
6. issues a controlled delivery response
7. records the access event

Raw filesystem or storage paths must never be returned.

## Phase 7 — Access and download history

Add append-only records for:

- entitlement granted
- entitlement suspended
- entitlement restored
- entitlement revoked
- access authorized
- access denied
- download started
- download completed when measurable

Include user, entitlement, PPPM item, asset, request context, and timestamp without storing unnecessary sensitive request data.

## Phase 8 — Customer library integration

Extend existing account APIs and pages rather than replacing them.

For each owned item show:

- PPPM item identity and lifecycle
- purchase/gift source
- immutable product snapshot
- entitlement status
- available assets
- download/access eligibility
- expiration or revocation reason where user-visible
- order/receipt link when authorized

Keep existing owned, purchased, sent, received, and redeemed scopes.

## Phase 9 — Merchant and administrator operations

Merchant views may show aggregate entitlement/access status for their products and PPPM items while preserving customer privacy.

Administrative operations require dedicated permissions for:

- entitlement inspection
- suspension
- revocation
- restoration
- policy exception review

Every operation must be audited.

## Phase 10 — Tests and migration

Required tests:

1. paid issued PPPM item creates one entitlement
2. retries do not duplicate grants
3. non-owner cannot access a protected asset
4. owner with active entitlement can access
5. revoked or expired entitlement is denied
6. transfer/claim changes access according to policy
7. full refund revokes affected access
8. dispute suspension and restoration are idempotent
9. raw storage paths are not exposed
10. access events are recorded
11. existing account item scopes remain functional
12. Stages 1–7 regression suite passes

## Completion threshold

Stage 8 should close only when:

- PPPM remains the owned-unit source
- entitlements are idempotent and server-authoritative
- protected delivery checks access on every request
- refund/dispute/transfer/claim policies are explicit
- customer library views use the existing account foundation
- no duplicate commerce, asset, ownership, receipt, or wallet system exists
- migrations, smoke checks, security tests, and regressions are green
