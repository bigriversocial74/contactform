# Product Bundle refunds, disputes, and reversals v10

Phase 10 adds a controlled adjustment ledger for Product Bundle settlements.

## Included

- Refund and partial-refund accounting adjustments.
- Dispute holds against eligible settlements.
- Reversal-request records tied to transfer records.
- Admin review and typed approval.
- Idempotent adjustment creation.
- Immutable settlement-event history.
- Provider-dispatch handoff without claiming provider completion.

## Safety boundary

This phase does not call Stripe transfer-reversal endpoints. Approved reversal requests enter `dispatch_pending` and require the approved deployment adapter or worker. Provider reconciliation and webhook confirmation remain authoritative for submitted and succeeded states.

## SQL

Import `database/20260719_product_bundle_refunds_reversals_v10.sql` after merge and before opening `/admin/bundle-settlement-adjustments.php`.
