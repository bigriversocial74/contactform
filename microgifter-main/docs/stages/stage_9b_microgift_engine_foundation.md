# Stage 9B — Microgift Engine Foundation

## Purpose

Stage 9B establishes the canonical reusable Microgift template, immutable template version, gift instance, secure credential, and lifecycle-event foundation without replacing commerce, PPPM, entitlements, products, profiles, locations, agents, or Future Demand systems.

## Canonical relationship

`Microgift Template -> Template Version -> Gift Instance -> Claim/Redemption -> PPPM Item -> Entitlement`

Stage 9B implements the first three identities and a credential foundation. Claim, ownership synchronization, redemption, cancellation, replacement, and full location policy enforcement continue in Stage 9C.

## Added schema

- `microgift_templates`
- `microgift_template_versions`
- `microgift_instances`
- `microgift_credentials`
- `microgift_events`

## Compatibility rules

- Existing `gifts` records may be linked through `microgift_instances.legacy_gift_id`.
- Existing PPPM items may be linked through `microgift_instances.pppm_item_id`.
- Existing paid commerce order items may fund issuance through `commerce_order_item_id`.
- Stage 8 entitlements remain the only digital-access authority.
- Existing Stage 3 gift claim APIs remain operational until Stage 9C completes the canonical claim transition.

No existing gift, claim, PPPM, entitlement, commerce, payment, receipt, or financial record is deleted or replaced.

## Template and version behavior

Templates support these owner types:

- person/user
- creator, artist, or musician
- merchant
- organization
- enterprise

Template versions are drafts until published. A published version becomes the template's active immutable version. Issued instances snapshot the published title, description, currency, value, product references, recipient policy, claim policy, redemption policy, location policy, expiration policy, and terms.

## Issuance behavior

Every issuance request requires:

- a published template version
- a recognized source type
- a source reference
- an idempotency key
- an authorized issuer

Commerce-backed issuance requires a verified paid commerce order item. Repeated issuance using the same idempotency key returns the existing instance.

## Credential security

Credentials use:

- cryptographically secure random generation
- a restricted human-safe alphabet
- normalization before hashing
- HMAC-SHA-256 with `MG_CLAIM_CODE_PEPPER`
- hash-only persistence
- a non-sensitive prefix and last-four for lookup/support
- attempt and lock fields
- one-time raw-code return at issuance

Raw codes are not written to schema fields, audit records, domain events, or security logs.

## APIs

- `GET|POST /api/microgifts/templates.php`
- `POST /api/microgifts/versions.php`
- `POST /api/microgifts/issue.php`
- `GET /api/microgifts/instances.php`

All writes require permission checks and CSRF validation. Instance reads are scoped to the owner, recipient, or issuer.

## Permissions

- `microgift.templates.manage`
- `microgift.instances.issue`
- `microgift.instances.view`

The initial role assignment is limited to merchant, administrator, and super-administrator roles. Customer-facing claim and redemption permissions remain under the existing gift flow until Stage 9C.

## Validation

Stage 9B adds:

- `scripts/stage9b.php`
- `scripts/stage9b_smoke.php`
- `tests/phpunit/Stage9BMicrogiftEngineFoundationTest.php`
- Stage 9B commands inside the consolidated PR Validation workflow

No additional GitHub Actions workflow was created.

## Deferred to Stage 9C

- canonical credential verification endpoint
- claim-to-PPPM owner synchronization
- transaction-safe redemption records
- location eligibility enforcement
- cancellation, revocation, expiration, and replacement services
- refund and dispute lifecycle integration
- compatibility migration/backfill for existing `gifts` and `gift_claims`

## Deferred to Stage 9D

- polished customer library integration
- complete merchant and administrator operations
- aggregate Microgift lifecycle reporting
- complete Future Demand event coverage
- duplicate-path deletion after compatibility verification
- Stage 9 closeout and Stage 10 handoff
