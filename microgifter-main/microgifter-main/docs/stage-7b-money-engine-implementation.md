# Stage 7B — Adapted Money Engine Implementation

## Purpose

Stage 7B adds the missing wallet, grouped double-entry ledger, cashout, payout, hold, reversal and reconciliation contracts around the payment infrastructure completed in Stage 5I.

The implementation preserves commerce orders, payment intents, payment transactions, refunds, disputes, provider accounts, webhook storage, merchant payout records and reconciliation records. It does not create parallel payment systems.

## Canonical financial chain

`Paid Order -> Grouped Ledger Posting -> Merchant Wallet -> Cashout Reservation -> Payout Record -> Signed Payout Event -> Paid or Released Balance`

## Schema

Added:

- `wallets`
- `ledger_accounts`
- `ledger_transaction_groups`
- `ledger_entries`
- `ledger_reversal_links`
- `wallet_balance_snapshots`
- `cashout_requests`
- `cashout_payout_links`
- `payout_holds`

The existing `merchant_payouts`, `payment_webhook_events`, `financial_reconciliation_runs` and `financial_reconciliation_items` remain in place and are adapted through relationships and services.

## Ledger rules

- amounts use integer cents
- every transaction group has one currency
- every group requires an idempotency key
- total debits must equal total credits
- entries require positive amounts
- entries are inserted atomically with their group
- application services are append-only and do not expose update or delete paths for entries
- corrections use linked reversal groups
- wallet balances are calculated from ledger entries

The migration intentionally avoids trigger DDL so it can run in hosted MySQL environments with binary logging enabled and without `SUPER` privileges. Ledger immutability is enforced by service design, no entry update/delete code paths, reversal-only corrections, constraints, and tests.

## Wallet accounts

Each wallet receives these accounts:

- available
- pending
- held
- cashout pending
- paid

Platform-level accounts currently include processor clearing. Additional platform fee and settlement accounts can be added without changing the wallet contract.

## Paid-order integration

Successful payment capture now calls the Stage 7 grouped posting adapter. The posting is idempotent by immutable commerce order ID and credits the merchant wallet while debiting processor clearing.

The existing order status history, receipt finalization, notifications and PPPM issuance behavior remains unchanged.

## Refund integration

Successful refunds use the grouped ledger. The refund posting reduces merchant available balance and processor clearing using the immutable refund public ID as the ledger idempotency source.

## Cashouts

Customer/owner APIs support:

- wallet summary
- ledger history
- cashout request
- own cashout history

Admin APIs support:

- cashout listing
- cashout approval
- cashout cancellation before processing
- payout hold creation and release

A cashout request verifies available balance and atomically moves value from available to cashout pending. An over-balance request fails without a ledger posting.

## Payouts

Approval adapts the existing `merchant_payouts` record and links it to the cashout through `cashout_payout_links`.

Signed payout webhook events support paid and failed outcomes. Provider event uniqueness prevents duplicate processing.

- paid: cashout pending moves to paid
- failed: cashout pending returns to available

## Holds

Authorized admin/risk actions can move available balance to held and release it through a second linked ledger posting. Historical entries are not changed.

## Reversals

Authorized financial administrators can reverse a posted transaction group. The service creates opposite entries, records a reversal link and marks the original group reversed.

## Reconciliation

Stage 7B adds an administrator reconciliation endpoint that compares paid-order totals with wallet availability postings for a period and records mismatches in the existing reconciliation item table.

More advanced provider settlement, dispute and item-level scheduling remains later operations/reliability work.

## APIs

- `GET /api/wallet/summary.php`
- `GET /api/wallet/ledger.php`
- `GET|POST /api/wallet/cashouts.php`
- `GET|POST /api/admin/cashouts.php`
- `POST /api/admin/payout-holds.php`
- `POST /api/admin/ledger-reversal.php`
- `GET|POST /api/admin/financial-reconciliation.php`
- `POST /api/payments/payout-webhook.php`

## Events

- `wallet.created`
- `ledger.transaction_posted`
- `cashout.requested`
- `cashout.approved`
- `cashout.cancelled`
- `payout.created`
- `payout.paid`
- `payout.failed`
- `payout_hold.created`
- `payout_hold.released`
- `reconciliation.run_started`
- `reconciliation.exception_found`

## Deferred

- live payment and payout provider activation
- automated payout worker scheduling
- multi-party fee splitting
- tax settlement accounts
- automated snapshot jobs
- advanced dispute reserve policy
- full provider settlement-file reconciliation
- financial admin UI

These are intentionally deferred; Stage 7B provides the secure backend contracts and testable lifecycle foundation.
