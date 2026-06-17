# Stage 5I Acceptance Checklist

- Checkout prices are resolved from published server-side product versions.
- Orders and payment intents use idempotency keys.
- One checkout order is restricted to one merchant and one currency.
- Webhooks require a provider-specific HMAC signature and provider-event uniqueness.
- Refunds cannot exceed the remaining captured amount.
- Ledger entries are balanced debit/credit pairs.
- Payouts, disputes, and reconciliation are merchant-scoped.
- Sandbox confirmation is disabled in live mode.
- Raw card numbers, bank credentials, and plaintext provider secrets are never stored.
- Payment, order, issuance, PPPM, and claim identifiers remain separate.
- Pages reuse the existing merchant shell on desktop and mobile.
