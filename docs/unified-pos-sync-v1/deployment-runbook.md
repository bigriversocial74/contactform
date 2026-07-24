# Unified POS Customer & Purchase Sync v1 — Deployment Runbook

## 1. Status model

Track these independently:

```text
Code merged
Code uploaded/deployed
SQL imported
Environment secrets configured
OAuth callback configured
Provider webhook subscriptions configured
One-minute cron installed
Sandbox validation completed
Production pilot enabled
Production transaction verified
Reconciliation verified
```

Never report the feature as deployed merely because a PR merged.

## 2. Merge/deploy order

```text
1. Provider-neutral POS foundation
2. Square OAuth and connection management
3. Square customer sync
4. Square purchase/refund ingestion
5. CRM/LTV projection
6. Merchant operations/reconciliation
7. Canonical event bridge
8. Future provider adapters
```

Each phase starts from the latest integration head and is deployed only after its prerequisite phase is merged and verified.

## 3. Pre-deployment reconciliation

Before opening the first implementation PR:

1. Verify newest `integration-from-repair-20260628` head.
2. Inspect current webhook receipt, encryption, OAuth, worker, CRM, identity, privacy, and audit implementations.
3. Compare the blueprint with live schema changes merged after this document was created.
4. Update the blueprint or implementation when canonical architecture differs.
5. Create a fresh Phase 1 branch from the newest integration head.

## 4. Required SQL

Planned migration:

```text
database/20260724_unified_pos_customer_purchase_sync_v1_single_install.sql
```

Expected contents:

- POS connections and locations.
- External customer mappings and match review.
- Sync runs.
- Webhook jobs.
- Canonical transactions and line items.
- Transaction effects.
- CRM POS rollups.
- Event outbox.
- Safe extensions to the existing provider webhook receipt ledger when required.
- Permissions and feature/entitlement registration according to repository conventions.

### SQL import procedure

1. Back up database/schema.
2. Review migration for environment-specific assumptions.
3. Import once through the approved deployment process.
4. Re-run the migration in a safe test environment to verify idempotency.
5. Verify all expected tables, columns, constraints, indexes, and permissions.
6. Run schema validator.
7. Record import date/environment and operator.

### SQL rollback

Do not automatically drop canonical transaction/history tables after production activity begins.

Rollback should normally:

- Disable the feature.
- Stop cron.
- Disable/revoke provider webhooks/connections.
- Roll back application code where safe.
- Preserve imported canonical history for investigation/recovery.

Destructive schema rollback requires a separately reviewed data migration.

## 5. Environment configuration

Exact names should follow the repository's environment conventions, but the implementation must account for:

```text
POS_SYNC_FEATURE_STATE
POS_SYNC_SELECTED_MERCHANTS
POS_SYNC_CREDENTIAL_KEY
POS_SYNC_CREDENTIAL_KEY_VERSION
POS_SYNC_WORKER_MAX_ATTEMPTS
POS_SYNC_WORKER_LOCK_TIMEOUT_SECONDS
POS_SYNC_RAW_PAYLOAD_RETENTION_DAYS
POS_SYNC_MAX_WEBHOOK_BODY_BYTES

SQUARE_APPLICATION_ID
SQUARE_APPLICATION_SECRET
SQUARE_ENVIRONMENT
SQUARE_OAUTH_REDIRECT_URI
SQUARE_WEBHOOK_NOTIFICATION_URL
SQUARE_WEBHOOK_SIGNATURE_KEY
SQUARE_API_VERSION
```

Security requirements:

- Secrets never committed to repository.
- Production and Sandbox credentials remain separate.
- Encryption key rotation is documented.
- Webhook notification URL must exactly match the URL used during Square signature generation.
- Configuration validation must fail closed.

## 6. OAuth configuration

### Square developer configuration

Configure:

- Application ID/secret.
- Redirect URI matching the deployed callback exactly.
- Sandbox and production settings separately.
- Minimum scopes required by the currently enabled v1 capabilities.

Read-only v1 should not request customer write scope.

### OAuth smoke test

1. Start from authenticated selected merchant.
2. Confirm authorization URL uses correct environment/client ID/redirect URI.
3. Complete Sandbox authorization.
4. Verify one active connection and discovered locations.
5. Verify encrypted credential fields are populated but never exposed.
6. Verify scopes.
7. Refresh token if applicable.
8. Disconnect and verify credentials removed while history remains.

## 7. Square webhook configuration

Subscribe to the provider events implemented at that phase.

Customer phase:

```text
customer.created
customer.updated
customer.deleted
customer custom-attribute visible events when enabled
```

Purchase/refund phase:

```text
payment.updated
refund.updated
```

Configuration requirements:

- HTTPS endpoint.
- Exact deployed notification URL.
- Correct environment and signature key.
- Correct Square API version.
- Event subscriptions verified in Sandbox before production.

### Webhook smoke test

1. Send provider test event.
2. Verify signature accepted.
3. Verify durable receipt and queue job.
4. Verify HTTP response is prompt and `2xx`.
5. Verify worker processes event.
6. Send identical duplicate and confirm no duplicate business effect.
7. Send invalid signature and confirm quarantine/rejection.

## 8. One-minute cron

Production support for a one-minute cron is confirmed.

Recommended entry:

```cron
* * * * * cd /path/to/microgifter && /usr/bin/php scripts/run_pos_sync_worker.php --limit=100 --max-seconds=50 >> /path/to/logs/pos-sync-worker.log 2>&1
```

Use actual deployed paths and PHP binary.

### Cron requirements

- Prevent unsafe duplicate work through database job claiming, not only process-level locks.
- Worker exits before the next minute when possible.
- Overlap remains safe if one process runs long.
- Logs exclude secrets/raw sensitive payloads.
- Monitor last worker heartbeat and queue depth.
- Provide a separate retention/reconciliation schedule if not included safely in the main worker.

### Cron verification

1. Queue one trusted Sandbox event.
2. Observe worker process within two minutes.
3. Verify heartbeat/last processed timestamp.
4. Verify no duplicate processing across two overlapping runs.
5. Temporarily force retryable failure and confirm backoff.

## 9. Feature rollout

States:

```text
disabled
admin_only
selected_merchants
enabled
```

Recommended rollout:

### Step 1 — Disabled

- SQL may be installed.
- Runtime entry points hidden/rejected.
- Validate schema/configuration.

### Step 2 — Admin only

- Internal connection and provider fixture testing.
- No normal merchant access.

### Step 3 — Selected merchants

- One Square Sandbox pilot.
- One production merchant/location pilot.
- Watch queue, duplicate rate, identity matches, refunds, and reconciliation.

### Step 4 — Enabled

Only after pilot approval and zero unexplained reconciliation drift.

## 10. Initial customer import deployment test

For pilot merchant:

1. Connect Square.
2. Select locations.
3. Choose custom-attribute allowlist.
4. Start initial customer import.
5. Observe sync-run progress.
6. Confirm counts for created, updated, matched, ambiguous, and failed.
7. Review ambiguous identities.
8. Confirm imported customers did not become login accounts.
9. Confirm unsubscribe states preserved.
10. Run customer reconciliation dry-run.

## 11. Purchase/refund production smoke test

Use a controlled low-value test transaction where permitted.

1. Complete Square POS purchase with known customer.
2. Verify webhook receipt and queue.
3. Verify canonical transaction and items.
4. Verify one completion effect.
5. Verify CRM event/rollup.
6. Deliver/replay duplicate and verify no second completion.
7. Run anonymous test purchase.
8. Confirm merchant aggregate but no customer LTV.
9. Complete partial refund.
10. Verify one refund effect and corrected rollups.
11. Run reconciliation dry-run.

Do not use live card/payment credentials in logs or screenshots.

## 12. Monitoring

Operational metrics:

```text
connections_active
connections_action_required
webhooks_received
webhooks_invalid_signature
webhooks_conflicting_replay
queue_depth
queue_oldest_age_seconds
jobs_retryable
jobs_dead_letter
worker_last_heartbeat
provider_api_rate_limits
customer_sync_failures
ambiguous_identity_count
anonymous_transaction_count
crm_rollup_drift_count
outbox_pending_count
```

Alert candidates:

- Worker heartbeat missing for more than three minutes.
- Queue oldest age above five minutes.
- Sudden invalid-signature spike.
- Action-required connection.
- Repeated provider authentication failure.
- Dead-letter event.
- Reconciliation drift.
- Payload-retention purge failure.

## 13. Backup and recovery

Before production pilot:

- Confirm database backup.
- Confirm restore process.
- Export/record Square connection identifiers and webhook configuration without secrets.
- Confirm canonical transaction/effect tables are included in backup.
- Confirm credential encryption key is backed up in secure operational storage.

Recovery priorities:

1. Preserve receipt/transaction/effect evidence.
2. Stop further processing if integrity is uncertain.
3. Disable feature or connection.
4. Restore credentials/configuration securely.
5. Run reconciliation dry-run.
6. Apply deterministic repair only after review.

## 14. Rollback scenarios

### Application defect

- Set feature disabled.
- Stop worker cron.
- Leave webhooks returning a controlled retryable response only when a corrected deployment is imminent; otherwise disable provider subscription to avoid retry storms.
- Roll back code.
- Preserve queue and canonical history.
- Reconcile after fix.

### Signature/configuration defect

- Disable affected subscription/connection.
- Correct exact notification URL/signature key/environment.
- Re-enable and use provider test event.

### Token/OAuth defect

- Mark connection action required.
- Prevent provider API calls.
- Ask merchant to reconnect.
- Preserve queued trusted receipts and retry after authorization when safe.

### CRM projection defect

- Stop projector/event bridge while preserving canonical ledger.
- Fix code.
- Recompute CRM POS rollups from canonical transactions/effects.

### Duplicate financial effects

- Disable feature immediately.
- Do not delete evidence manually.
- Reconcile effect keys, ledger, CRM rollups, and outbox.
- Apply reviewed corrective migration/repair receipts.

## 15. Privacy and retention deployment checks

- Confirm raw/redacted payload retention defaults to 90 days.
- Confirm recursive secret/token redaction.
- Confirm purge worker/command.
- Confirm imported customer consent is not treated as Microgifter consent.
- Confirm privacy erasure anonymizes identity but preserves required commerce totals.
- Confirm disconnect clears credentials.

## 16. Release checklist

```text
[ ] Latest integration head verified
[ ] Scoped PR checks passed
[ ] SQL reviewed
[ ] Backup completed
[ ] SQL imported and verified
[ ] Environment variables configured
[ ] Encryption key secured
[ ] Square Sandbox OAuth verified
[ ] Square production OAuth configured
[ ] Square webhook URL/signature configured
[ ] One-minute cron installed
[ ] Worker heartbeat verified
[ ] Initial customer import verified
[ ] Completed purchase verified
[ ] Duplicate delivery verified
[ ] Anonymous purchase verified
[ ] Partial refund verified
[ ] CRM rollups verified
[ ] Reconciliation dry-run clean
[ ] Selected merchant rollout enabled
[ ] Production deployment confirmed by David
```

## 17. Required status report format

```text
PR: #...
Merge: confirmed / not confirmed
Integration head: <sha>
SQL migration: <path>
SQL imported: yes / no / not confirmed
Environment configured: yes / no / partial
Square OAuth configured: Sandbox / Production / no
Square webhooks configured: yes / no / partial
One-minute cron installed: yes / no / not confirmed
Code uploaded: yes / no / not confirmed
Production pilot enabled: yes / no
Production transaction verified: yes / no
Reconciliation: clean / drift found / not run
```

## 18. Current deployment status

```text
Implementation PR: none
SQL file: not created
SQL imported: no
Environment secrets configured: no
Square OAuth configured: no
Square webhooks configured: no
POS worker cron installed: no
Code deployed: no
Production pilot enabled: no
Production verification: no
```

The production host's ability to run a one-minute cron is confirmed; the feature-specific cron remains uninstalled until implementation/deployment.