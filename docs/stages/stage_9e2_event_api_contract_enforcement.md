# Stage 9E-2 — Event Catalog Enforcement and API Contract Validation

## Purpose

Stage 9E-2 converts the Stage 1–9 event and API contract baselines from reference documents into enforced repository contracts before Stage 10 begins.

## Event catalog enforcement

The event catalog is upgraded to version 2 and now declares:

- event category
- owning domain
- producer
- required identifiers
- idempotency source
- privacy classification
- downstream consumers
- strict emitter scope

Literal `mg_event()` names emitted by canonical PPPM and Microgift service files must be registered in the catalog. Dedicated entity lifecycle records may continue through append-only PPPM, entitlement, and Microgift event-table services.

Credential lifecycle events are classified as restricted security events. Their payloads may identify the instance and credential public record, but may not include raw credentials or credential hashes.

## API contract enforcement

The API registry is upgraded to version 2 and now covers the complete Stage 9 operational surface:

- Microgift templates and versions
- issuance and instance reads
- claims and redemption
- payment lifecycle policy
- customer library
- merchant operations
- administrator review, inspection, lifecycle, and replacement
- protected downloads

Enforced write endpoints must include:

- an authentication or permission gate
- CSRF validation
- documented idempotency for financial, ownership, claim, redemption, or lifecycle effects

## Canonical service enforcement

The tests preserve the Stage 9E-1 source-of-truth correction:

- Microgift claims must call `mg_pppm_transfer_owner_canonical()`.
- Microgift redemption must call `mg_pppm_redeem()`.
- Entitlement synchronization remains inside the canonical PPPM ownership orchestration.
- Microgift services may not directly mutate PPPM ownership or redeemed status.

## Legacy event inventory

Events outside the strict canonical emitter scope remain inventory candidates rather than automatic failures in this pass. This prevents an uncontrolled rename of older Stage 1–6 integrations while creating a clear rule for all new canonical events.

A later reconciliation pass may classify remaining legacy events as domain, audit, entity-lifecycle, or analytics-source events and promote them into the canonical catalog.

## Validation

`Stage9E2EventApiContractEnforcementTest` validates:

- required event metadata
- registration of literal canonical event names
- credential payload privacy
- API contract coverage
- authentication and CSRF gates
- canonical PPPM ownership and redemption service use

The test runs through the existing consolidated PHPUnit workflow. No additional GitHub Actions workflow is added.
