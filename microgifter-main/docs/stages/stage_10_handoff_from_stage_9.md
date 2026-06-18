# Stage 10 Handoff from Stage 9

## Opening rule

Begin Stage 10 with an official-plan review and repository inventory before adding schema or APIs.

Stage 10 must preserve these completed Stage 9 contracts:

- Microgift templates are reusable owner-scoped definitions.
- Published template versions are immutable.
- Microgift instances are gift-contract identities with frozen terms.
- Claim credentials are hash-only after issuance.
- Claims and redemptions are idempotent and transaction-safe.
- PPPM remains the issued-unit and ownership source.
- Entitlements remain protected digital-access rights.
- Commerce and the Stage 7 ledger remain the purchase and money sources.
- Locations remain canonical location references.
- Operational events and daily metrics are source facts, not predictive scores.

## Stage 9 features to carry forward

- customer owned/sent/received/claimed/redeemed Microgift scopes
- merchant lifecycle and redemption operations
- administrator inspection and review queues
- non-destructive legacy compatibility reconciliation
- template, instance, claim, redemption, and lifecycle event history
- daily issuance, claim, redemption, value, recipient, and location aggregates

## Stage 10 boundaries

Stage 10 must not:

- replace Microgift instances with orders, PPPM items, or claims
- store or expose raw claim/redeem credentials
- bypass object ownership, permission, CSRF, idempotency, or row-lock rules
- mutate wallet or ledger balances outside the Stage 7 money engine
- calculate Future Demand scores from incomplete or private data
- delete legacy gift paths before approved compatibility backfill and caller verification

## Recommended opening task

Create **Stage 10A — Official Plan Review, Existing Feature Inventory, and Dependency Reconciliation**.

Classify every Stage 10 requirement as complete, implemented early, partial, misaligned, missing, or deferred, and explicitly account for Microgift templates, instances, claims, redemptions, operational metrics, PPPM ownership, entitlements, profiles, locations, agents, enterprise programs, and Future Demand source data.
