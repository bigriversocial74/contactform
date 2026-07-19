# Product Bundle transfer orchestration v9

Phase 9 creates the guarded handoff between release-ready bundle settlements and the deployment's Stripe Connect transfer adapter.

## Controls

- Admin commerce access is required.
- Settlement must remain eligible.
- A prior `admin_review_mark_release_ready` event is required.
- Merchant Stripe account must be active and payout-enabled.
- `MG_BUNDLE_TRANSFER_EXECUTION_ENABLED=true` is required.
- The operator must type `RELEASE`.
- One durable transfer record is allowed per settlement.
- Idempotency keys prevent duplicate requests.

## Provider boundary

This phase persists a validated transfer request and returns `provider_dispatch_required=true`. It does not call Stripe directly. Production deployment must connect the transfer record to the approved provider-dispatch worker. The worker must write the provider transfer reference, final status, response snapshot, and settlement release event.

## SQL

Import `database/20260719_product_bundle_stripe_transfers_v9.sql` after merge and before opening `/admin/bundle-settlement-transfers.php`.

## Deferred

- Direct provider dispatch worker.
- Transfer webhooks and reconciliation.
- Refund reversals and dispute recovery.
- Automated payout batches.
