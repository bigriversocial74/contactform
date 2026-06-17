# Stage 7A — Current Financial Inventory

## Purpose

This inventory identifies the current financial records, services, APIs and tests that Stage 7 must preserve, adapt or retire deliberately.

## Canonical payment and commerce records already present

### Commerce

- `commerce_orders`
- `commerce_order_items`
- receipts and order history from the Stage 5 foundation

### Provider and checkout

- `payment_provider_accounts`
- `checkout_sessions`
- `payment_intents`
- `payment_transactions`
- `payment_webhook_events`

### Exceptions and adjustments

- `payment_refunds`
- `payment_disputes`

### Payout and accounting groundwork

- `merchant_payouts`
- `financial_ledger_entries`
- `financial_reconciliation_runs`
- `financial_reconciliation_items`

## Current services and helpers

### `mg_finance_record_paid_order()`

Responsibilities:

- locks the order
- records the provider intent reference
- marks the intent succeeded
- marks the order paid
- inserts a sale transaction
- posts a basic processor-clearing / merchant-payable pair
- records order payment history and audit events
- finalizes the receipt
- triggers PPPM issuance
- creates buyer and merchant notifications

Stage 7 treatment:

Preserve the order/payment/receipt/PPPM orchestration. Replace the direct ledger-pair call with an idempotent transaction-group posting service.

### `mg_ledger_pair()`

Current behavior:

- inserts one debit and one credit row
- skips amounts below one cent
- uses account-code strings

Limitations:

- no transaction group
- no idempotency key
- no wallet relationship
- no ledger-account relationship
- no posting status
- no reversal support
- no audit/event emission
- no database immutability guard

Stage 7 treatment:

Compatibility-only. Do not expand it into the final ledger. New financial posting must use the canonical LedgerService.

### Merchant reconciliation

Current behavior:

- merchant-scoped
- validates a date range
- totals paid/refunded commerce orders
- creates a reconciliation run
- records one period-total exception when provider and expected totals differ
- writes an audit record

Stage 7 treatment:

Preserve as the initial reconciliation skeleton. Move calculation and exception creation into a shared reconciliation service and add admin run management later.

## Current APIs

### Payment APIs

- checkout session creation
- session read
- sandbox payment confirmation
- signed payment webhook

### Merchant financial APIs

- merchant financial dashboard
- merchant refund request
- merchant reconciliation request

### Stage 7 official APIs not yet present

- wallet summary for current user
- wallet summary for owned merchant/creator
- wallet ledger pagination
- cashout request
- own cashout list
- admin cashout list
- cashout approve
- cashout cancel
- payout hold create
- payout hold release
- admin reconciliation run list
- admin reconciliation run create
- payout-event webhook processing

## Current event and audit coverage

Implemented or adjacent:

- payment captured order audit
- order status history
- payment success/failure notifications
- refund and reconciliation audits
- webhook receipt status

Missing Stage 7 catalog events:

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

## Existing test coverage

Stage 5I currently checks:

- schema presence for payment and finance groundwork
- published server-side pricing
- payment-to-PPPM issuance separation
- webhook signature and provider-event handling
- merchant-scoped, idempotent refunds
- merchant-scoped dashboard and reconciliation
- payment/PPPM identity separation
- merchant workspace integration

Stage 7 must add explicit tests for:

- wallet/account creation
- group balance enforcement
- group idempotency
- ledger immutability
- ledger-derived balance projections
- cashout reservation
- over-balance denial with no side effects
- payout creation
- payout paid/failed handling
- payout holds and releases
- reversals
- item-level reconciliation exceptions

## Source-of-truth decisions

- Orders remain the commerce obligation source.
- Provider records remain the external payment source.
- The new Stage 7 ledger becomes the internal balance source.
- Wallet snapshots are caches only.
- `merchant_payouts` should be adapted into the canonical payout record unless migration analysis proves replacement safer.
- `financial_ledger_entries` must not remain the long-term ledger source without transaction groups, ledger accounts, idempotency and immutability.
