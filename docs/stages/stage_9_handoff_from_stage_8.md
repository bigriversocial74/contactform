# Stage 9 Handoff from Stage 8

## Opening rule

Begin Stage 9 with an official-plan review and repository inventory before adding schema or workflows.

Stage 9 must preserve the permanent contracts completed through Stage 8:

- commerce order items are purchased-line identities
- PPPM items are issued-unit and ownership identities
- entitlements are protected access rights
- product assets remain the content/file source
- the grouped ledger remains the internal balance source
- access, lifecycle, and policy events remain append-only historical records

## New Stage 8 features to carry forward

- idempotent grants from paid PPPM issuance
- owner/claim transfer synchronization
- full-refund revocation and partial-refund review
- dispute suspension, restoration, and revocation
- protected asset removal/restoration policy
- signed delivery adapter contract
- customer entitlement library APIs
- merchant-scoped entitlement visibility
- entitlement transfer and policy-action records

## Boundaries for Stage 9

Stage 9 must not:

- replace PPPM ownership
- create a second entitlement system
- expose storage keys
- derive ownership from payment IDs
- mutate wallet balances outside the Stage 7 money engine
- bypass refund, dispute, claim, or asset lifecycle policies

## Recommended opening task

Create **Stage 9A — Official Plan Review, Existing Feature Inventory, and Dependency Reconciliation**.

Classify every Stage 9 requirement as:

- complete
- implemented early
- partial
- misaligned
- missing
- intentionally deferred

The Stage 9 plan should explicitly account for the new entitlement, access, ownership-transfer, dispute, asset-removal, and delivery contracts before implementation begins.
