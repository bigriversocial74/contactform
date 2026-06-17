# Stage 7A — Money Engine Reconciliation and Alignment

## Purpose

Stage 7A compares the official Stage 7 plan — Wallet + Double-Entry Ledger + Cashouts — against the current codebase before any new financial implementation begins.

The objective is to preserve useful Stage 5I payment and reconciliation work while preventing duplicate or conflicting wallet, ledger, payout, refund, webhook, and reconciliation systems.

Stage 7A is documentation and architecture reconciliation only. It introduces no financial schema, APIs, UI, or provider activation.

## Official Stage 7 contract

The official plan requires:

- wallets
- ledger accounts
- balanced transaction groups
- immutable ledger entries
- ledger-derived balance projections
- cashout requests
- payout records
- payout holds
- reconciliation runs and exception items
- reversal links
- idempotent payout webhooks
- audit logs and financial events

Core rules:

1. The ledger is the source of truth.
2. Money is stored as integer cents with explicit currency.
3. Every posted transaction group balances debits and credits.
4. Ledger entries cannot be edited or deleted.
5. Corrections use reversal entries.
6. Cashouts require verified available balance.
7. Payouts require an enabled provider account.
8. Every money-impacting action creates audit and event records.

## Current implementation inherited from Stage 5I

Stage 5I already provides:

- `commerce_orders`
- `commerce_order_items`
- `payment_provider_accounts`
- `checkout_sessions`
- `payment_intents`
- `payment_transactions`
- `payment_refunds`
- `payment_disputes`
- `merchant_payouts`
- `financial_ledger_entries`
- `payment_webhook_events`
- `financial_reconciliation_runs`
- `financial_reconciliation_items`
- merchant financial dashboard
- merchant refund endpoint
- merchant reconciliation endpoint
- signed and idempotent payment webhook storage
- paid-order ledger pair posting
- paid-order receipt finalization
- payment-to-PPPM issuance integration

These records must be adapted into Stage 7 rather than duplicated.

## Requirement matrix

| Official Stage 7 requirement | Current status | Current implementation | Stage 7B action |
|---|---|---|---|
| Wallet container per owner/currency | Missing | No `wallets` table or wallet API | Add canonical wallets table and owner/currency uniqueness |
| Ledger accounts | Missing | `financial_ledger_entries.account_code` stores strings only | Add canonical `ledger_accounts`; map account codes during migration/adaptation |
| Balanced transaction groups | Missing | `mg_ledger_pair()` inserts two rows without a group record | Add `ledger_transaction_groups` with currency and idempotency constraints |
| Immutable ledger entries | Partial / unsafe | `financial_ledger_entries` exists, but no database immutability guard or transaction-group FK | Evolve or replace through controlled migration; block update/delete and require posted group |
| Idempotent ledger posting | Missing | Payment/order idempotency reduces duplicates indirectly, but ledger postings have no unique posting key | Add LedgerService and group-level idempotency |
| Balance projection | Missing | Merchant dashboard groups raw entries by account code | Add ledger-derived available/pending/held/paid calculation service and endpoints |
| Wallet snapshots | Missing | None | Add optional snapshots as read optimization only |
| Cashout requests | Missing | None | Add request lifecycle and available-balance reserve posting |
| Cashout over-balance prevention | Missing | None | Enforce transactional balance check and no-entry failure path |
| Payout record | Partial / implemented early | `merchant_payouts` tracks provider payout state | Adapt into canonical payout record model; avoid creating a parallel payout table unless migration requires it |
| Provider payout eligibility | Partial | `payment_provider_accounts.payouts_enabled` exists | Require active account and payouts enabled before cashout approval |
| Payout worker/handoff | Missing | No cashout approval or payout worker path | Add provider adapter-ready payout service and worker command |
| Payout paid/failed webhook | Missing | Payment webhook handles payment success/failure only | Add dedicated payout-event handling using existing webhook-event store |
| Payout holds | Missing | None | Add hold/release model, ledger reserve movements, admin permission, events and audit |
| Refund/reversal hooks | Partial | Refund records and paired refund ledger rows exist | Replace direct pair logic with reversal-aware LedgerService posting |
| Reversal links | Missing | None | Add explicit original-to-reversal group links |
| Reconciliation run header | Implemented early | `financial_reconciliation_runs` | Preserve and adapt |
| Reconciliation exception items | Implemented early / limited | `financial_reconciliation_items` and sandbox period-total comparison | Preserve; expand later to order/payment/payout/ledger item reconciliation |
| Financial events | Partial | Payment/order audits and notifications exist | Add Stage 7 event catalog consistently |
| Audit logging | Partial | Refund and reconciliation paths audit; ledger pair helper does not | Require audit on wallet, ledger, cashout, payout, hold, reversal and reconciliation actions |
| Wallet/cashout APIs | Missing | Merchant dashboard only | Add official customer/owner/admin endpoints |
| Immutable correction policy | Missing | No reversal service; rows are directly insertable | Add reversal-only correction service and database protections |

## Critical findings

### 1. `financial_ledger_entries` is not yet the Stage 7 ledger

The table records debit and credit rows and uses integer cents, but it lacks:

- transaction-group identity
- group balance validation
- posting status
- group-level idempotency
- ledger account identity
- reversal links
- database immutability protections

It is useful historical groundwork, but it cannot be treated as a complete double-entry ledger.

### 2. `mg_ledger_pair()` must not become the final posting service

The helper inserts two rows with matching amounts. It does not prove a broader transaction balances, does not use an idempotency key, does not create a transaction group, and does not protect against a partial or repeated posting.

Stage 7B should replace new uses with a transactional LedgerService. Existing rows should be preserved and mapped through a controlled migration or compatibility adapter.

### 3. `merchant_payouts` should be adapted, not duplicated

The existing table already stores provider, currency, gross, fee, adjustment, net, status, arrival date and provider reference. Stage 7B should evaluate renaming or extending this table into the canonical payout record. Creating a second unrelated payout table would produce conflicting sources of truth.

### 4. Reconciliation exists but is intentionally shallow

The current merchant reconciliation endpoint compares paid order totals with a sandbox provider total and records one period-level exception. This is a valid skeleton, but it does not reconcile individual payment transactions, ledger groups, refunds, disputes, cashouts or payouts.

### 5. Payment webhook storage is reusable

`payment_webhook_events` already provides provider-event uniqueness, signature status, payload hash, processing status and failure recording. Stage 7B should extend its processing to payout events or build a shared webhook dispatcher around it rather than creating another webhook inbox.

## Duplicate-system prohibitions

Stage 7B must not create parallel versions of:

- commerce orders
- payment intents
- payment transactions
- payment refunds
- payment disputes
- provider accounts
- webhook event storage
- merchant payouts
- reconciliation run headers
- reconciliation items

Any required replacement must include an explicit migration, compatibility period and retirement plan.

## Security review

Existing strengths:

- integer-cent money fields
- server-controlled order totals
- signed webhook validation
- provider-event uniqueness
- refund idempotency keys
- merchant-scoped refund and dashboard reads
- payout-enabled capability field
- no raw card storage

Stage 7 gaps:

- no wallet ownership policy
- no cashout authorization policy
- no atomic balance reservation
- no ledger-level idempotency
- no immutable ledger protection
- no payout approval separation
- no payout hold policy
- no reversal authorization policy
- no payout webhook event processor
- no reconciliation admin permission boundary matching the official contract

## Stage 7A score

Current readiness against the official Stage 7 plan:

- Payment/provider foundation: 8.5/10
- Refund/dispute foundation: 7.5/10
- Reconciliation skeleton: 6.5/10
- Canonical wallet system: 0/10
- Canonical double-entry ledger: 3.5/10
- Cashout lifecycle: 0/10
- Payout lifecycle: 3/10
- Holds and reversals: 0/10
- Overall Stage 7 readiness: 4.4/10

This score does not mean the existing payment code is poor. It means most Stage 7-specific money-engine requirements have not yet been implemented, while several adjacent payment components were completed early.

## Decision

Proceed to Stage 7B as an additive/adaptive money-engine build.

Preserve the Stage 5I payment records and customer purchase flow. Add the missing wallet, grouped ledger, cashout, hold, reversal and payout-event contracts around them.
