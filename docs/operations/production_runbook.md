# Microgifter Production Runbook

## Deployment sequence

1. Build the ordered upgrade artifact with `php scripts/build_full_upgrade_sql.php`.
2. Verify the manifest and artifact checksums.
3. Confirm a current restorable database backup.
4. Run the complete CI validation workflow.
5. Create a deployment release record with the exact commit SHA and rollback plan.
6. Record every release gate as passed.
7. Approve the release.
8. Deploy the exact approved artifact.
9. Run `php scripts/validate_launch_readiness.php` against the deployed environment.
10. Record the deployed artifact manifest and deployment actor.

## Rollback

Rollback is mandatory when deployment validation fails, canonical migrations cannot complete safely, payment or ledger health is degraded, or a SEV1 incident is opened during deployment.

The release record must identify the rollback artifact, database recovery procedure, responsible operator, and post-rollback validation commands. After rollback, run health, readiness, security, and transaction smoke checks before reopening traffic.

## Incident response

- **SEV1:** platform-wide outage, financial integrity risk, unauthorized access, or widespread claim/redemption failure.
- **SEV2:** major feature outage or material payment/delivery degradation.
- **SEV3:** localized feature failure with a workaround.
- **SEV4:** minor operational defect with limited impact.

For SEV1 and SEV2 incidents, assign a commander, record impact, stop unsafe deployments, preserve logs and evidence, document mitigation, and keep the incident open until verification is complete.

## Scheduled operations

Run retention in a controlled maintenance window:

```bash
php scripts/run_retention.php
```

Run readiness after deployments and at least daily:

```bash
php scripts/validate_launch_readiness.php
```

Monitor failed payment webhooks, stale agent runs, unresolved critical incidents, migration drift, and release-gate failures.

## Business authority safeguards

Operational responders must not repair incidents by directly rewriting canonical ledger, wallet, Microgift ownership, claim, redemption, entitlement, tip, or subscription state. Use the owning service, reconciliation process, linked reversal, or documented recovery workflow.
