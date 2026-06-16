# Stage 7B — Adapted Money Engine Build Outline

## Objective

Implement the missing Stage 7 wallet, grouped ledger, cashout, payout, hold, reversal and reconciliation contracts without duplicating the Stage 5I payment systems. Existing cart, checkout, product, gift, account-menu, inbox, location and mobile behavior must remain unchanged.

## 1. Canonical wallet and ledger schema

Add `wallets`, `ledger_accounts`, `ledger_transaction_groups`, grouped ledger entries, `wallet_balance_snapshots`, `cashout_requests`, `payout_holds`, and `ledger_reversal_links`.

Adapt rather than duplicate `merchant_payouts`, `financial_reconciliation_runs`, `financial_reconciliation_items`, and `payment_webhook_events`.

Required rules:

- one wallet per owner, currency and owner type
- unique ledger account purpose per wallet and currency
- unique idempotency key per transaction group
- integer-cent amounts and one currency per group
- total debits equal total credits
- posted history is append-only
- corrections use linked reversal groups

## 2. WalletService and LedgerService

WalletService should resolve wallets, create required accounts, verify ownership, calculate available/pending/held/paid balances, and optionally write snapshots.

LedgerService should validate accounts, currency, amounts and group balance; post groups atomically; return the existing group for duplicate idempotency keys; emit `ledger.transaction_posted`; and create reversal groups without changing posted history.

## 3. Paid-order integration

Replace new uses of `mg_ledger_pair()` with LedgerService. Preserve order, payment, receipt and PPPM behavior.

Paid-order postings should distinguish processor clearing, merchant pending/payable and platform fee revenue when applicable. The posting key must use immutable order/payment identity so webhook retries cannot double-post.

Existing `financial_ledger_entries` rows must be preserved through migration or a documented legacy compatibility path.

## 4. Wallet read APIs

Implement:

- current-user wallet summary
- owned merchant/creator wallet summary
- paginated wallet ledger

Return currency, available, pending, held and paid totals, plus the calculation time and snapshot time when a cache is used.

## 5. Cashout lifecycle

Add request, own-list, admin-list, approve and cancel operations.

Cashout rules:

- authorized owner or manager
- active wallet and supported currency
- positive amount
- amount cannot exceed available balance
- provider account must be active with payouts enabled before approval
- idempotency key required

A request moves funds from available to cashout-pending. Cancellation releases the reservation. A rejected over-balance request creates no financial posting.

Events: `cashout.requested`, `cashout.approved`, `cashout.cancelled`.

## 6. PayoutService and provider handoff

Adapt `merchant_payouts` as the canonical payout record. One approved cashout creates one provider payout/transfer request, stores the provider reference, and emits `payout.created`.

Live provider behavior stays behind an adapter. Sandbox behavior must be deterministic and marked as test mode.

## 7. Payout webhook handling

Extend the existing signed webhook event store for payout paid, failed and reversed events.

Paid outcomes finalize cashout and payout state and move reserved funds to paid/cashed-out. Failed outcomes follow an explicit release or retry policy. Provider-event uniqueness must make retries safe.

Events: `payout.paid`, `payout.failed`.

## 8. Payout holds

Admin/risk hold operations move available funds to held through the ledger and record actor, reason and optional expiration. Release operations restore funds through a linked posting.

Events: `payout_hold.created`, `payout_hold.released`.

## 9. Refund reversals

Route new refund accounting through LedgerService. Refunds reference the original paid-order group, partial refunds remain supported, and repeated callbacks remain idempotent.

## 10. Reconciliation expansion

Preserve the existing run and item tables. Expand comparison across paid orders, payment transactions, ledger groups, refunds, disputes, cashout reservations, payouts and provider events.

Events: `reconciliation.run_started`, `reconciliation.exception_found`.

Scheduling and advanced monitoring remain Stage 18 work.

## 11. Permissions and audit

Confirm permissions for wallet reads, cashout requests, own cashout history, admin review, payout processing, payout holds, reconciliation and reversal approval. Every high-risk financial action must create an audit record.

## 12. Acceptance tests

Implement official AT-7.1 through AT-7.12:

1. wallet and required accounts created
2. balanced group posts once
3. projected balances match ledger
4. valid cashout reserves funds
5. over-balance request fails without postings
6. approved cashout creates payout
7. paid webhook finalizes payout and ledger
8. failed webhook follows release/retry policy
9. hold reduces available balance
10. release restores balance
11. posted ledger history cannot be changed
12. Stages 1–6 regression suite passes

Additional tests should verify that paid-order retries do not double-post, payment and PPPM identities remain separate, Stage 5I reconciliation data remains readable, existing payout records survive adaptation, and customer cart/checkout behavior is unchanged.

## Implementation order

1. schema compatibility
2. WalletService
3. LedgerService
4. paid-order integration
5. wallet reads
6. cashouts
7. payout service
8. payout webhooks
9. holds
10. refund reversals
11. reconciliation
12. acceptance and regression tests

## Completion threshold

Stage 7 closes only when the ledger is balanced, grouped, idempotent and append-only; balances are ledger-derived; cashouts reserve funds atomically; payout outcomes are webhook-idempotent; holds and reversals use ledger postings; Stage 5I records have a compatibility path; and the consolidated test suite passes.
