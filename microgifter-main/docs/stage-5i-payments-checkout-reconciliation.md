# Stage 5I — Payments, Checkout, Orders, Refunds, Payouts, and Financial Reconciliation

Stage 5I introduces a provider-neutral transaction layer while preserving strict separation between payment transactions, commerce orders, distribution assignments, permanent PPPM items, and claims.

## Included

- Server-priced checkout sessions from published product versions
- Commerce orders and immutable order-line snapshots
- Provider accounts, payment intents, transactions, refunds, disputes, and payouts
- Signed, idempotent webhook ingestion
- Double-entry ledger entries
- Merchant financial dashboard
- Provider-neutral refund operations
- Reconciliation runs and exception records
- Sandbox checkout confirmation for test mode only
- Shared merchant-shell and mobile UI

## Identity boundary

A payment intent proves money movement. An order groups purchased lines. An issuance request creates eligible units. Each unit receives its own permanent PPPM ID. Refunds, disputes, and payouts reference financial records and never replace PPPM identity.

## Provider boundary

The default `sandbox` provider is only available in test mode. Live mode requires a configured provider adapter and signed webhooks. Raw card data is never accepted or stored by Microgifter.
