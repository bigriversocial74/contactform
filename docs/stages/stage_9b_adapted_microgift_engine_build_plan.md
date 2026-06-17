# Stage 9B — Adapted Microgift Engine Build Plan

## Objective

Build the canonical reusable Microgift template, immutable version, gift-instance, secure redeem-code, claim, and redemption orchestration layer around the systems already completed through Stage 8.

The Stage 9 engine must adapt existing gifts and claims without creating parallel commerce, PPPM, entitlement, asset, profile, location, agent, analytics, wallet, or ledger systems.

## Canonical model

`Microgift Template -> Template Version -> Gift Instance -> Claim/Redemption -> PPPM Item -> Entitlement`

Funding and issuance sources may include:

- verified commerce order items
- authorized merchant or administrator issuance
- enterprise/workplace programs
- scheduled or agent-requested issuance in later integrations

## Phase 1 — Compatibility and source-of-truth lock

Before new schema:

1. Inventory every active caller of `gifts` and `gift_claims`.
2. Identify which existing records are customer-facing production records, prototypes, or compatibility data.
3. Lock PPPM as the permanent issued-unit/ownership source.
4. Lock entitlements as digital access rights.
5. Lock commerce orders as paid purchase obligations.
6. Decide whether existing `gifts` becomes the canonical instance table through additive migration or is mapped to a new `microgift_instances` table.
7. Document zero-data clean-install assumptions and any legacy compatibility path.

Preferred direction: adapt `gifts` where structurally safe; add a new canonical table only if existing constraints cannot support templates, version snapshots, and idempotent issuance without ambiguity.

## Phase 2 — Microgift templates and immutable versions

Add one template model supporting owner types:

- user/person
- creator/artist/musician
- merchant
- organization
- enterprise

Template fields should include:

- public ID
- owner type and owner user/organization reference
- name, description, status, visibility
- gift type
- default currency and value rules
- recipient policy
- claim/redeem policy
- location policy
- expiration policy
- product/offer references where applicable
- active version ID
- created/updated timestamps

Template versions must be immutable after publication and snapshot:

- terms
- value/discount rules
- product/version references
- eligible locations/categories
- recipient restrictions
- claim/redeem requirements
- expiration calculations
- metadata needed by Future Demand events

## Phase 3 — Canonical gift-instance service

The instance service should:

- accept only authorized issuance sources
- require a source type, source reference, and idempotency key
- resolve a published template version
- validate owner, product, value, location, recipient, and funding policy
- create an immutable instance snapshot
- generate a secure redeem/claim credential when required
- create or link the corresponding PPPM issuance request/item
- record audit and domain events
- return the existing instance on retries

Instance identity must remain separate from:

- order ID
- payment ID
- PPPM item ID
- claim ID
- entitlement ID
- redeem credential

## Phase 4 — Secure redeem-code service

Requirements:

- cryptographically secure random code generation
- minimum entropy appropriate for online redemption
- raw code returned only once at issuance or approved reissue
- only a slow password-style hash or keyed cryptographic hash stored
- safe searchable prefix or last-four stored separately
- raw code excluded from logs, audits, events, analytics, and metadata
- constant-time verification
- attempt counters and lock timestamps
- IP/user/instance rate limiting
- generic failure responses that do not reveal instance existence
- explicit code rotation/replacement with old-code invalidation

## Phase 5 — Claim and recipient assignment

Support recipient policies:

- owner/purchaser retained
- named registered user
- external recipient awaiting account association
- open claim by code
- enterprise/workplace-assigned recipient

Claim service rules:

- lock the instance and claim row
- verify status, expiration, recipient policy, attempts, and code
- enforce one successful owner transition
- update canonical PPPM ownership through the established ownership service
- call Stage 8 entitlement owner synchronization when digital access exists
- create append-only claim/ownership events
- make retries idempotent

## Phase 6 — Redemption

Redemption must be server-authoritative and transactional.

Checks:

- authenticated or policy-authorized claimant
- instance exists and is redeemable
- claim/ownership requirements satisfied
- not expired, cancelled, revoked, replaced, or already redeemed
- merchant/location/category eligibility
- redemption amount/quantity within snapshot rules
- idempotency key not previously used

On success:

- create one redemption record
- transition instance and PPPM states through approved services
- record merchant/location/redemption context
- update entitlement state only through Stage 8 services where needed
- emit audit and domain events
- preserve immutable history

## Phase 7 — Cancellation, revocation, expiration, and replacement

Define explicit policy:

- unclaimed instance cancellation may return to a cancellable state according to funding policy
- claimed instance cancellation requires administrator/policy review
- revocation never deletes history
- expiration prevents new claim/redemption but preserves records
- replacement creates a linked new instance and invalidates the prior credential
- refunded/disputed commerce-backed gifts observe existing payment and entitlement policy
- no Stage 9 service directly mutates wallet or ledger balances

## Phase 8 — Location and eligibility

Reuse canonical locations.

Support:

- unrestricted
- merchant-wide
- allow-listed locations
- excluded locations
- category or region policy where present

Redemption checks must use location public IDs and server-side merchant ownership. Do not copy addresses into policy tables except immutable display snapshots where legally or operationally required.

## Phase 9 — Customer, merchant, and administrator APIs

Customer/account views should extend existing sent, received, claimed, redeemed, owned, and purchased scopes.

Merchant APIs:

- template list/detail
- template version history
- instance list/detail
- redemption history
- location/category eligibility summaries
- aggregate lifecycle metrics

Administrator APIs:

- inspect template/instance/claim/redemption history
- lock/unlock credentials under permission
- cancel, revoke, replace, or correct through explicit events
- review ambiguous migration or policy cases

All writes require authentication, object authorization, permission checks where applicable, CSRF protection, rate limits, and idempotency.

## Phase 10 — Agent, workplace, and enterprise integration boundaries

Stage 9 should expose service contracts for later automation:

- request issuance by template/version
- assign recipient
- schedule delivery reference
- cancel before claim where policy allows
- inspect lifecycle status

Agents and workplace programs may call these services only with explicit owner permissions and source idempotency keys. Stage 9 does not create another scheduler or agent runtime.

## Phase 11 — Future Demand event catalog

Emit source events for:

- template created/published/retired
- instance issued/delivered/viewed
- claim attempted/locked/verified/completed
- redemption authorized/denied/completed
- expired/cancelled/revoked/replaced
- location/category usage

Events should contain stable IDs and non-sensitive snapshots. They must never include raw redeem codes or unnecessary recipient private data. Predictive scoring remains deferred.

## Phase 12 — Migration, smoke checks, and tests

Required tests:

1. Template versions are immutable after publication.
2. Duplicate issuance source keys return one instance.
3. Raw redeem codes are not persisted or logged.
4. Incorrect code attempts increment and eventually lock.
5. Correct code verification is transaction-safe.
6. Concurrent claims produce one owner transition.
7. Claim updates PPPM ownership and entitlement access once.
8. Duplicate redemption keys produce one redemption.
9. Expired, cancelled, revoked, replaced, or redeemed instances cannot redeem.
10. Location restrictions are enforced server-side.
11. Paid-order issuance requires a verified paid order item.
12. Unauthorized merchant/customer/admin access is denied.
13. Existing account scopes remain functional.
14. Stages 1–8 regression tests remain green.

## Recommended Stage 9 delivery sequence

- **Stage 9B:** compatibility lock, template/version schema, instance service, redeem-code service
- **Stage 9C:** claim, ownership, redemption, location, refund/dispute integrations
- **Stage 9D:** customer/merchant/admin read models, event coverage, consolidation, and closeout

## Completion threshold

Stage 9 closes only when:

- one canonical template/version contract exists
- one canonical gift-instance identity exists
- existing gifts/claims are adapted or explicitly mapped
- raw redeem codes are never stored
- issuance, claim, and redemption are idempotent and transaction-safe
- PPPM, entitlements, commerce, locations, and profiles remain canonical dependencies
- customer and merchant views use the consolidated model
- audit, events, migrations, security tests, and regressions are green
