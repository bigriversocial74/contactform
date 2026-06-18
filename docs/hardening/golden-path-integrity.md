# Golden Path Integrity Hardening Plan

## Canonical product lifecycle

Microgifter uses this lifecycle:

`issued / purchased / picked up -> delivered -> claimed -> verified -> tracked`

There is no separate `user-claimed` concept. That wording was introduced during recovery analysis and is not part of the product model.

Before changing behavior, the existing database statuses, API responses, Action Center folders, audit events, and UI labels must be mapped to these canonical product stages.

## Goal

Prove and harden one authoritative end-to-end customer path:

`checkout draft -> order -> payment capture -> ledger posting -> PPPM issuance -> entitlement grant -> Microgift issuance -> delivery -> claim -> verification -> tracking`

The work must preserve the existing ownership boundaries:

- Commerce owns order and payment truth.
- The financial ledger owns money posting truth.
- PPPM owns issued-unit identity and ownership.
- Entitlements own protected digital access.
- Microgift instances own lifecycle state.
- Merchant-location verification owns verification authority.
- Action Center remains a derived read model only.

## Milestone 1 — Lifecycle contract audit

Document the exact relationship between the product lifecycle and the current technical vocabulary before changing code.

Audit these current technical statuses and concepts:

- `issued`
- `delivered`
- `claim_pending`
- `claimed`
- `redeemable`
- `redeemed`
- `expired`
- `revoked`
- `replaced`
- Action Center folders: `inbox`, `sent`, `claimed`
- merchant claim-code verification
- operational tracking and audit events

For every technical status, record:

1. Its canonical product stage.
2. Who is allowed to cause the transition.
3. Whether ownership changes.
4. Which Action Center folder is shown.
5. Whether the gift can still be sent, claimed, verified, or tracked.
6. Which timestamp and audit event prove the transition.
7. Whether the transition is reversible.

No lifecycle behavior will be changed until this mapping is explicit.

## Milestone 2 — Contract tests first

Add tests for the agreed lifecycle mapping. At minimum, prove:

1. Issued, purchased, and picked-up acquisition paths enter the correct initial stage.
2. Delivery produces the correct owner and Action Center state.
3. Claim moves the gift into the correct claimed state and folder.
4. Verification can only occur through the authorized merchant/location flow.
5. Tracking begins only after the required verification event.
6. Sender history remains accurate after transfer.
7. Exact retries do not duplicate payment, ledger, PPPM, entitlements, Microgifts, Action Center rows, claims, verification records, notifications, or outbox events.
8. Conflicting retries fail without partial state.
9. Reconciliation reports no drift for a correct projection at every agreed lifecycle stage.

## Milestone 3 — Purchased gift end-to-end validator

Add a database-backed validator that performs the complete purchased-gift path inside rollback-safe fixtures:

1. Create merchant, buyer, and recipient fixtures.
2. Publish a catalog product and checkout draft.
3. Create the order and payment intent.
4. Capture payment.
5. Verify ledger balance, merchant balance, receipt, PPPM, entitlements, Microgift, initial Action Center rows, audit events, and notifications.
6. Deliver or transfer one purchased gift to the recipient.
7. Verify PPPM and Microgift ownership alignment.
8. Claim the gift and verify the agreed claimed state and folder.
9. Verify it through an authorized merchant location.
10. Verify the tracking records and final Action Center state.
11. Replay each idempotent operation and verify no duplication.
12. Roll back all fixtures and verify cleanup.

## Milestone 4 — Minimal behavior corrections

After the lifecycle contract tests exist, make only the smallest changes required to align implementation with the agreed model.

Potential areas include:

- lifecycle status translation;
- Action Center folder and state projection;
- reconciliation comparisons;
- claim-code verification transitions;
- post-verification tracking records;
- idempotency and recovery behavior.

Do not introduce another ownership, payment, claim, verification, or financial authority.

## Milestone 5 — Reconciliation and recovery

- Add audit-mode coverage for correct, missing, mismatched, and orphaned Action Center rows.
- Verify repair mode updates derived rows only and never changes PPPM ownership, lifecycle truth, payments, ledger entries, claims, or verification records.
- Add a dry-run command or documented operator command for production audit before any repair run.
- Record counts only; do not expose gift codes, payment data, or private message content.

## Validation and release gate

Required before merge:

- Canonical lifecycle mapping is documented and approved.
- Targeted checkout-capture validator passes.
- Targeted Microgift lifecycle validator passes.
- New purchased-gift lifecycle validator passes.
- Action Center reconciliation tests pass.
- `composer recovery-baseline` passes on a clean MySQL 8 database.
- Pull Request Validation is green.
- No existing migration SQL is modified.
- No broad refactor or UI redesign is included.

## Proposed commit sequence

1. `docs: define canonical Microgifter lifecycle mapping`
2. `test: define purchased gift lifecycle contracts`
3. `fix: align lifecycle projection with canonical stages`
4. `fix: align reconciliation with canonical projection states`
5. `test: validate purchased gift through verification and tracking`
6. `test: cover Action Center audit and repair modes`
7. `docs: record operator audit and rollback procedure`

## Non-goals

- Payment-provider redesign
- Ledger-policy changes
- Pricing or fee changes
- New database ownership models
- Action Center visual redesign
- Broad naming or formatting refactors
- Changes to unrelated social, subscription, demand, or agent features
