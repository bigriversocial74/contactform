# Creator Campaign Phase 7 — Budgets and Commitments

Phase 7 adds merchant-controlled campaign budgets on top of the Phase 6 append-only earnings ledger.

## Delivered

- One currency-specific budget per Creator Campaign.
- Immutable available, reserved, and committed bucket events.
- Atomic earning reservations with one reservation per earning event.
- Hard-cap enforcement with optional controlled overage.
- Reservation commit, release, and committed-balance restore actions.
- Idempotent budget events and reservation actions.
- Budget-limit adjustments that cannot create invalid bucket balances.
- Merchant budget dashboard and reservation operations.

## Boundaries

Phase 7 does not execute Creator payouts, provider transfers, disputes, tax reporting, or MCP actions.

## SQL

`database/20260722_creator_campaign_budget_controls_v7_single_install.sql`
