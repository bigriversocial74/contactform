# Upgrade, Rollback, and Restore Runbook

## Purpose

Provide a repeatable operational process for deploying database and application changes without treating a successful clean install as proof that an existing environment can be upgraded safely.

## Required inputs

- Exact application commit SHA.
- Exact database backup identifier and timestamp.
- Current migration inventory.
- Target migration inventory.
- Maintenance-window owner.
- Rollback decision owner.
- Verification checklist owner.

## Pre-deployment checks

1. Confirm PR Validation and Browser Validation are green for the exact commit.
2. Run `composer validate-foundation` or the equivalent complete validation sequence.
3. Generate and inspect the full upgrade SQL artifact.
4. Run the upgrade validator against a recent sanitized copy of the hosted database.
5. Verify disk space, database connection limits, queue state, and scheduled jobs.
6. Capture current application and migration versions.
7. Create and verify a database backup before applying changes.
8. Record active workers and scheduled processes that may write during the upgrade.

## Deployment sequence

1. Put write-sensitive workers into a controlled paused state when required.
2. Deploy the exact reviewed application commit.
3. Apply migrations through the canonical migration runner.
4. Run upgrade validation and runtime smoke checks.
5. Start the application test server or production health probe.
6. Verify authentication, cart, checkout boundaries, PPPM ownership reads, Action Center reads, and merchant claim health.
7. Resume workers and scheduled jobs in a controlled order.
8. Monitor application, database, queue, and payment-provider errors.

## Rollback triggers

Rollback immediately when any of the following occurs:

- Migration failure leaves the schema in an unknown state.
- Authentication or authorization boundaries fail.
- Ownership or entitlement reads are inconsistent.
- Claim or redemption can bypass canonical Stage 10 validation.
- Payment or ledger writes become duplicated or unbalanced.
- Health checks pass but critical user workflows return server errors.
- Queue replay produces repeated side effects.

## Application rollback

1. Stop new deployments and pause affected workers.
2. Restore the previously approved application commit.
3. Do not reverse database migrations automatically unless a tested down-migration exists.
4. If the new schema is backward compatible, run the prior application against the upgraded schema only when explicitly validated.
5. Otherwise proceed to database restore.

## Database restore

1. Put the application into maintenance mode or block writes.
2. Stop workers and scheduled jobs.
3. Preserve the failed database for incident analysis.
4. Restore the verified pre-deployment backup into a clean target.
5. Verify row counts for users, orders, PPPM units, entitlements, gift instances, claims, ledger entries, and Action Center projections.
6. Point the application to the restored database.
7. Run health, security, migration-state, and workflow smoke tests.
8. Resume traffic only after the rollback owner signs off.

## Post-restore verification

- Users can sign in.
- Orders and payments match the pre-deployment snapshot.
- PPPM current ownership is unchanged.
- Sent history does not grant redemption authority.
- Entitlements match purchased access.
- Merchant claim history is intact.
- Ledger totals reconcile.
- Action Center projection counts are consistent with source records.
- No duplicate webhooks, notifications, transfers, claims, or payouts were emitted.

## Rehearsal requirement

Before production launch, perform at least one complete rehearsal using a realistic database copy:

1. Backup.
2. Upgrade.
3. Validate.
4. Introduce a controlled failure.
5. Roll back application.
6. Restore database.
7. Re-run verification.

Record duration, failures, manual steps, and corrections. A runbook that has not been rehearsed is documentation, not recovery capability.
