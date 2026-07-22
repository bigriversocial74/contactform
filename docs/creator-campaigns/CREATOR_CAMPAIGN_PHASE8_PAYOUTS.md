# Creator Campaign Phase 8 — Payouts and Disputes

Phase 8 adds payout eligibility, payout records, payout item claims, immutable payout events, reversals, and disputes on top of committed Phase 7 budget reservations.

## Delivered

- Currency-specific Creator payout eligibility profiles.
- Payout records assembled only from eligible committed reservations.
- One active or paid payout item per budget reservation.
- Campaign-scoped payout idempotency.
- Explicit draft, approval, processing, paid, failed, cancelled, and reversed transitions.
- Provider references recorded only after an external process confirms the result.
- Append-only payout event history.
- Creator- and merchant-opened disputes for payouts, reservations, or earning events.
- One active dispute per source and append-only dispute events.
- Active disputes block payout approval, processing, and payment.
- Merchant payout/dispute workspace and Creator ownership-scoped payout workspace.

## Boundaries

Phase 8 records payout outcomes but does not call a payment provider, execute transfers, store bank secrets, file taxes, or expose MCP execution.

## SQL

`database/20260722_creator_campaign_payouts_disputes_v8_single_install.sql`
