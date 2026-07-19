# Product Bundle settlement review controls v8

Phase 8 adds an admin-controlled review gate between settlement eligibility and any future payout execution.

## Controls

- Admin queue for eligible, held, and blocked settlements.
- Approve, hold, block, reopen, and mark-release-ready actions.
- Hold and block actions require reasons.
- Every action creates an immutable `gift_bundle_settlement_events` record with idempotency protection.
- Only eligible or held settlements may return to release-ready eligibility.

## Safety boundary

`transfer_execution_enabled` remains `false`. This phase does not create Stripe transfers, release merchant payouts, or reverse provider transfers.

## SQL

No SQL required. Phase 8 reuses the Phase 7 settlement ledger and settlement event tables.
