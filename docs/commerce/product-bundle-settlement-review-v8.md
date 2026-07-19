# Product Bundle settlement review controls v8

Phase 8 adds an admin-controlled review gate between settlement eligibility and any future payout execution.

## Controls

- Admin queue for unreviewed, held, blocked, approved, and release-ready settlements.
- Approve, hold, block, reopen, and mark-release-ready actions.
- Hold and block actions require reasons.
- Every action creates an immutable review record with idempotency protection.
- Only eligible settlements may become release-ready.

## Safety boundary

`transfer_execution_enabled` remains `false`. This phase does not create Stripe transfers, release merchant payouts, or reverse provider transfers.

## SQL

Import `database/20260719_product_bundle_settlement_review_controls_v8.sql` after merge and before using `/admin/bundle-settlement-reviews.php`.
