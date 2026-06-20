# V1 Staging Release Runbook

This runbook separates work that can be completed in GitHub from checks that require the uploaded staging application, public webhook URL, and server credentials.

## Release state before upload

The `Release Package Validation` workflow produces three downloadable artifacts for an exact Git commit:

1. `microgifter-candidate-release-<commit>` — candidate application archive, SHA-256 checksum, and manifest.
2. `microgifter-rollback-release-<commit>` — verified previous-commit archive, checksum, and manifest.
3. `microgifter-release-evidence-<commit>` — reproducibility, database restore, rollback-package, and combined release evidence.

The candidate archive:

- includes production Composer dependencies;
- includes `RELEASE.json` with the source commit and migration-manifest checksum;
- excludes `.env`, `api/config.local.php`, tests, documentation, GitHub workflows, Node dependencies, and the nested archived repository copy;
- is built twice in CI and must produce the same SHA-256 checksum;
- is not production approval by itself.

## Evidence that can be completed before upload

The workflow must pass all of these:

- Composer definition validation;
- release-script shell and PHP syntax validation;
- canonical clean-database migrations;
- consistent database backup creation;
- isolated database restore;
- restored canary verification;
- restored table and migration-count comparison;
- canonical migration-manifest validation against the restored database;
- deterministic candidate artifact comparison;
- current and rollback artifact checksum verification;
- safe archive path validation;
- required runtime-file validation;
- secret and development-path exclusion;
- complete PHP syntax validation inside both candidate and rollback packages.

The combined `v1-release-evidence.json` intentionally leaves live-environment gates marked `deferred` and release approval marked `blocked`.

## Required staging environment values

Use server environment configuration or the server-only `api/config.local.php`. Never add real values to the release archive.

At minimum, staging requires:

```text
MG_APP_ENV=staging
MG_DEBUG=false
MG_APP_URL=https://STAGING_HOST
MG_BASE_URL=https://STAGING_HOST
MG_RUNTIME_PROFILE=hostgator

MG_DB_HOST=...
MG_DB_NAME=...
MG_DB_USER=...
MG_DB_PASS=...
MG_DB_CHARSET=utf8mb4

MG_PAYMENT_PROVIDER=stripe
MG_PAYMENT_MODE=test
MG_STRIPE_PUBLISHABLE_KEY_TEST=...
MG_STRIPE_SECRET_KEY_TEST=...
MG_STRIPE_WEBHOOK_SECRET_TEST=...
MG_PLATFORM_FEE_BPS=1500
MG_PLATFORM_FIXED_FEE_CENTS=0

MG_ENABLE_POLLING_NOTIFICATIONS=true
MG_ENABLE_DB_OUTBOX=true
MG_ENABLE_QUEUE_WORKER=false
MG_ENABLE_REDIS=false
MG_ENABLE_WEBSOCKETS=false
MG_ENABLE_SSE=false

MG_MEDIA_STORAGE_DRIVER=persistent_local
MG_MEDIA_STORAGE_ROOT=/home/CPANEL_USER/microgifter-storage
MG_MEDIA_PUBLIC_ENDPOINT=/api/public/media.php
MG_REQUIRE_PERSISTENT_MEDIA_STORAGE=true
```

Also configure all existing application signing, claim-code, distribution, invitation, and reporting secrets required by the current environment.

## Predeployment staging procedure

### 1. Record the intended release

Record:

- release version;
- candidate Git commit SHA;
- artifact filename;
- artifact SHA-256;
- rollback Git commit SHA;
- rollback artifact filename and SHA-256;
- operator and timestamp.

Confirm the commit values match the candidate and rollback `RELEASE.json` files.

### 2. Verify downloaded artifacts

From the download directory:

```bash
sha256sum --check microgifter-CANDIDATE.tar.gz.sha256
sha256sum --check microgifter-ROLLBACK.tar.gz.sha256
```

Do not deploy an archive whose checksum fails.

### 3. Create and validate the target database backup

Extract the candidate into a temporary non-public directory and run its backup validator against the staging database:

```bash
bash scripts/validate_database_backup_restore.sh \
  --keep-backup \
  --output-dir=/home/CPANEL_USER/release-evidence/RELEASE_VERSION
```

This creates a compressed SQL backup, validates an isolated restore, verifies a canary record and the canonical migration manifest, and retains the backup and checksum.

A CI backup-restore pass does not replace this target-environment backup.

### 4. Back up persistent media

The media directory is outside the application release and must remain outside it.

Example:

```bash
tar --create --gzip \
  --file=/home/CPANEL_USER/release-evidence/RELEASE_VERSION/microgifter-media-predeploy.tar.gz \
  --directory=/home/CPANEL_USER \
  microgifter-storage
sha256sum /home/CPANEL_USER/release-evidence/RELEASE_VERSION/microgifter-media-predeploy.tar.gz \
  > /home/CPANEL_USER/release-evidence/RELEASE_VERSION/microgifter-media-predeploy.tar.gz.sha256
```

If no media has ever been created, record that fact rather than silently omitting the gate.

### 5. Preserve server-only configuration

The candidate intentionally excludes:

```text
.env
api/config.local.php
```

Keep the existing server configuration outside the archive or copy it into the extracted candidate only on the server. Confirm permissions prevent public download.

### 6. Extract the candidate into a new release directory

Do not extract over the active release.

Example layout:

```text
/home/CPANEL_USER/releases/RELEASE_VERSION
/home/CPANEL_USER/releases/ROLLBACK_VERSION
/home/CPANEL_USER/public_html
/home/CPANEL_USER/microgifter-storage
/home/CPANEL_USER/release-evidence/RELEASE_VERSION
```

After extraction, compare `RELEASE.json` with the recorded candidate SHA.

### 7. Validate the candidate before switching traffic

From the candidate directory:

```bash
php -v
php scripts/validate_migration_manifest.php
php scripts/check_media_storage.php
```

Confirm PHP is supported and the persistent media directory is writable, protected, and outside the candidate release.

### 8. Apply canonical migrations

Follow the existing deployment order:

```bash
php scripts/run_migrations.php
php scripts/check_media_storage.php --initialize
php scripts/migrate_feed_media_storage.php --dry-run
```

Run the actual media migration only when the dry run reports pending legacy files:

```bash
php scripts/migrate_feed_media_storage.php
```

Do not manually import individual migration files after the canonical runner succeeds.

### 9. Switch the staging web root

Use the HostGator/cPanel method already approved for the account: document-root change, atomic directory rename, or controlled file replacement. The switch must preserve server-only configuration and must not move or delete the persistent media directory.

Record the exact method used.

### 10. Run deployed readiness checks

From the active staging release:

```bash
php scripts/validate_launch_readiness.php
php scripts/check_media_storage.php
```

Then verify:

```text
GET /api/health.php
```

The release remains blocked if launch readiness fails, the health endpoint is unhealthy, a published merchant lacks a ready Stripe Connect account, or a SEV1/SEV2 incident is open.

## Live staging verification

These checks cannot be completed until the candidate files are uploaded and staging is publicly reachable.

### Stripe provider boundary

Run the protected `Stripe Test Integration` GitHub workflow with the staging test credentials and ready test connected account. Retain its JSON artifact.

### Hosted Checkout and signed webhook

Complete one real Stripe test-mode purchase through the staging UI and verify:

1. Published product loads safely.
2. Immutable product version enters the cart.
3. Checkout draft and pending order are created once.
4. Stripe Hosted Checkout opens over HTTPS.
5. Stripe delivers a signed event to the staging webhook URL.
6. The event is stored once and replay remains idempotent.
7. The order becomes paid once.
8. Ledger entries balance and retain the configured platform share.
9. Receipt, PPPM item, Microgift instance, Action Center projection, and confirmations are each created once.
10. Merchant and purchaser records use the immutable order and product snapshots.

### Transfer and redemption

Using the purchased gift:

1. Send or regift it to a registered recipient.
2. Confirm only the current owner can transfer it.
3. Confirm the most recent sender can Follow Up.
4. Confirm earlier senders cannot access later transfer conversations.
5. Redeem it through an authorized merchant location and private claim code.
6. Confirm the recipient sees Claimed and the historical sender sees Redeemed in Sent.
7. Confirm the PPPM and Microgift both remain terminal and cannot transfer again.

## Rollback procedure

Rollback means restoring the matching predeployment state. Do not attempt reverse migrations.

1. Stop or restrict staging traffic using the available hosting controls.
2. Record the failed release SHA and reason.
3. Restore the retained predeployment database backup.
4. Restore the retained media backup if the deployment changed media files or relationships.
5. Replace the candidate code with the verified rollback artifact.
6. Restore the same server-only environment configuration.
7. Run:

```bash
php scripts/validate_migration_manifest.php
php scripts/check_media_storage.php
php scripts/validate_launch_readiness.php
```

8. Verify `/api/health.php`.
9. Reopen staging traffic only after the rollback state is healthy.
10. Record an operational incident if the failed deployment affected users, financial truth, ownership, or redemption.

## Release approval

Production approval requires all automated and live staging evidence. The following cannot be waived for production:

- clean migrations;
- security and PHPUnit suites;
- browser smoke;
- target database backup verification;
- target rollback verification;
- launch readiness;
- Stripe provider readiness;
- hosted Checkout and signed-webhook fulfillment;
- end-to-end transfer and merchant redemption;
- no unresolved SEV1 or SEV2 incident.
