# Golden Path Integrity Hardening Plan

## Goal

Prove and harden one authoritative end-to-end customer path:

`checkout draft -> order -> payment capture -> ledger posting -> PPPM issuance -> entitlement grant -> Microgift issuance -> Action Center -> transfer -> claim -> merchant redemption -> final Action Center state`

The work must preserve the existing ownership boundaries:

- Commerce owns order and payment truth.
- The financial ledger owns money posting truth.
- PPPM owns issued-unit identity and ownership.
- Entitlements own protected digital access.
- Microgift instances own gift lifecycle state.
- Merchant-location redemption owns final redemption authority.
- Action Center remains a derived read model only.

## Confirmed integration defects

### 1. Recipient folder classification

The current Action Center classifier places `claimed` and `redeemable` gifts in the `claimed` folder. Product rules require those gifts to remain in `inbox`; only successfully merchant-redeemed gifts belong in `claimed`.

### 2. Reconciliation state comparison

The reconciliation service compares Action Center `state` against the raw Microgift instance status. The projection uses normalized read-model states such as `claimable`, `redeemable`, and `redeemed`, so the comparison can report false drift and perform unnecessary repairs.

### 3. Missing purchased-gift lifecycle coverage

Checkout validation proves payment, ledger, PPPM, entitlement, Microgift issuance, and initial Action Center projection. Microgift lifecycle validation proves send, claim, redemption, messaging, and final projection for a separately issued merchant-funded gift. No single validator currently proves the purchased gift across the complete lifecycle.

## Milestone 1 — Contract tests first

Add failing tests that assert:

1. A purchased gift begins in the buyer's `inbox` with state `claimable`.
2. Transfer creates `sent` for the sender and `inbox` for the new owner.
3. Claim or `redeemable` state remains in the recipient's `inbox`.
4. A claimed gift cannot be transferred when lifecycle policy forbids it.
5. Merchant redemption moves only the recipient row to `claimed` with state `redeemed`.
6. The sender row remains `sent` and becomes state `redeemed`.
7. Tipping is disabled before redemption and enabled only after successful redemption.
8. Exact retries do not duplicate payment, ledger, PPPM, entitlements, Microgifts, Action Center rows, claims, redemptions, notifications, or outbox events.
9. Conflicting retries fail without partial state.
10. Reconciliation reports no drift for a correct projection at every lifecycle state.

## Milestone 2 — Minimal projection corrections

Make the smallest behavioral changes required to satisfy the contracts:

- Change recipient folder classification so only `redeemed` maps to `claimed`.
- Keep `issued`, `delivered`, `claim_pending`, `claimed`, and `redeemable` in `inbox` for the current owner.
- Preserve sender history in `sent`.
- Keep archived/terminal handling within the originating folder unless redemption completed.
- Compare reconciliation rows to `mg_action_center_state()` rather than raw instance status.
- Preserve all existing transaction and ownership boundaries.

## Milestone 3 — Purchased gift end-to-end validator

Extend or add a database-backed validator that performs the complete purchased-gift path inside rollback-safe fixtures:

1. Create merchant, buyer, and recipient fixtures.
2. Publish a catalog product and checkout draft.
3. Create the order and payment intent.
4. Capture payment.
5. Verify ledger balance, merchant balance, receipt, PPPM, entitlements, Microgift, initial Action Center rows, audit events, and notifications.
6. Transfer one purchased gift to the recipient.
7. Verify PPPM and Microgift ownership alignment.
8. Claim the gift and verify it remains in `inbox`.
9. Redeem through an authorized merchant location.
10. Verify final PPPM, Microgift, redemption, Action Center, outbox, notification, audit, and tipping state.
11. Replay each idempotent operation and verify no duplication.
12. Roll back all fixtures and verify cleanup.

## Milestone 4 — Reconciliation and recovery

- Add audit-mode coverage for correct, missing, mismatched, and orphaned Action Center rows.
- Verify repair mode updates derived rows only and never changes PPPM ownership, Microgift lifecycle, payments, ledger entries, claims, or redemptions.
- Add a dry-run command or documented operator command for production audit before any repair run.
- Record counts only; do not expose gift codes, payment data, or private message content.

## Milestone 5 — Validation and release gate

Required before merge:

- Targeted checkout-capture validator passes.
- Targeted Microgift lifecycle validator passes.
- New purchased-gift lifecycle validator passes.
- Action Center reconciliation tests pass.
- `composer recovery-baseline` passes on a clean MySQL 8 database.
- Pull Request Validation is green.
- No existing migration SQL is modified.
- No new ownership, payment, claim, redemption, or financial authority is introduced.
- No broad refactor or UI redesign is included.

## Proposed commit sequence

1. `test: define purchased gift lifecycle and Action Center contracts`
2. `fix: correct Action Center recipient folder classification`
3. `fix: reconcile normalized Action Center states`
4. `test: validate purchased gift through merchant redemption`
5. `test: cover Action Center audit and repair modes`
6. `docs: record operator audit and rollback procedure`

## Non-goals

- Payment-provider redesign
- Ledger-policy changes
- Pricing or fee changes
- New database ownership models
- Action Center visual redesign
- Broad naming or formatting refactors
- Changes to unrelated social, subscription, demand, or agent features
