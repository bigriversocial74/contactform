# Microgifter Delivery Operations & Capacity Foundation v1

## Purpose

This foundation turns Microgifter's existing notification outbox into a production-oriented delivery operations system without replacing the canonical Microgift, Wallet, Inbox, Action Center, or PPPM authorities.

Current delivery truth remains:

```text
Microgift issued
→ Wallet / Inbox / PPPM projection
→ in-app notification
```

Email, SMS, and push are separate communication jobs. A communication-provider failure never recreates or invalidates the underlying reward.

## Baseline audit

The scoped delivery and Action Center modal code scored **5.8/10** before this repair.

Strengths already present:

- canonical paid-order issuance reconciliation
- durable notifications
- `notification_delivery_jobs`
- notification preferences and quiet hours
- browser push subscriptions and provider bridge
- system health metrics

Gaps repaired:

- no unified CLI worker for all communication jobs
- no durable lease ownership or overlap control
- no commercial batch/runtime limits
- no per-user or per-merchant fairness
- incomplete retry/dead-letter lifecycle
- expired leases did not consume retry budget
- dead-letter requeue did not reset attempt budget
- no global safety pause based on failure rate
- no dedicated operator queue and recovery console
- external channels defaulted on before provider readiness
- Action Center modal styling was loaded through three overlapping sheets

## Architecture

```text
Canonical reward issuance
→ Wallet / Inbox / PPPM
→ notification row
→ deterministic channel jobs
   ├─ in_app: delivered immediately
   ├─ email: disabled until provider adapter is registered
   ├─ sms: disabled until provider adapter is registered
   └─ push: disabled until PWA provider readiness is confirmed
→ leased CLI worker
→ provider evidence
→ delivered / accepted / retry / suppressed / dead letter
→ operator recovery and health monitoring
```

## Worker controls

Defaults:

```text
MG_DELIVERY_WORKER_ENABLED=false
MG_DELIVERY_BATCH_SIZE=50
MG_DELIVERY_MAX_RUNTIME_SECONDS=50
MG_DELIVERY_LEASE_SECONDS=120
MG_DELIVERY_MAX_ATTEMPTS=8
MG_DELIVERY_RETRY_BASE_SECONDS=60
MG_DELIVERY_RETRY_MAX_SECONDS=21600
MG_DELIVERY_MAX_PER_USER_PER_RUN=10
MG_DELIVERY_MAX_PER_MERCHANT_PER_RUN=100
MG_DELIVERY_FAILURE_PAUSE_PERCENT=20
MG_DELIVERY_FAILURE_PAUSE_MIN_ATTEMPTS=10
MG_DELIVERY_EMAIL_ENABLED=false
MG_DELIVERY_SMS_ENABLED=false
MG_DELIVERY_PUSH_ENABLED=false
```

The batch limit is system-wide per run. User and merchant limits are fairness ceilings inside that batch, not lifetime or daily sending limits. Capacity can be raised after live observation without changing the code.

## CLI

Read-only observation:

```bash
php /path/to/microgifter/bin/delivery-worker.php --observe
```

Processing after deployment acceptance:

```bash
php /path/to/microgifter/bin/delivery-worker.php --process
```

Optional bounded run:

```bash
php /path/to/microgifter/bin/delivery-worker.php --process --limit=25
```

The worker is CLI-only. The web admin cannot execute the queue.

## Retry and recovery

- Atomic lease acquisition prevents two workers from owning one job.
- A database advisory lock prevents overlapping worker runs.
- Expired leases consume an attempt and become retry-scheduled or dead-lettered.
- Retry delay uses exponential backoff with bounded jitter.
- Dead-letter recovery resets the attempt budget only after an authorized operator chooses to requeue it.
- Processing jobs cannot be cancelled while a provider attempt may be active.
- The worker automatically pauses when the configured failure threshold is reached.
- Clearing a pause requires zero unresolved dead letters and the exact acknowledgement phrase.

## Provider adapters

Provider adapters register through:

```php
mg_delivery_register_adapter('email', $adapter);
mg_delivery_register_adapter('sms', $adapter);
```

An enabled channel with no provider adapter fails closed into dead letter. It does not silently claim delivery.

## Admin operations

Protected route:

```text
/admin/delivery-operations.php
```

The console shows:

- queue depth and oldest pending age
- current worker status and pause state
- channel-level queued, completed, and failed counts
- commercial capacity and fairness settings
- safe recipient labels without raw destinations
- recent worker-run evidence
- retry, cancel, dead-letter requeue, and guarded pause clearance

## Modal CSS consolidation

Action Center previously loaded:

- `gift-action-center-modal-fix.css`
- `gift-action-center-send-modal.css`
- `gift-action-center-claim-modal.css`

The active Inbox and Claimed pages now load one canonical sheet through the shared Action Center:

```text
/assets/css/gift-action-center-modals.css
```

The old files remain in the repository for compatibility/history but are no longer loaded by these active routes.

## Deployment order

1. Import `database/delivery_operations_capacity_foundation_v1.sql`.
2. Deploy the merged integration branch.
3. Keep the worker and all external channels disabled.
4. Open `/admin/delivery-operations.php` and confirm schema readiness.
5. Run the CLI in `--observe` mode.
6. Confirm Inbox and in-app notification delivery still work.
7. Enable the worker with only in-app jobs present.
8. Configure and validate each external provider separately before enabling its channel.

## Rollback

- Disable `MG_DELIVERY_WORKER_ENABLED`.
- Disable all external channel flags.
- Inbox, Wallet, PPPM, and in-app notification records remain valid.
- Do not delete queue evidence during rollback.

## SQL

SQL is required:

```text
database/delivery_operations_capacity_foundation_v1.sql
```
