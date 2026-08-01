# Microgifter Production Launch Certification v1

## Purpose

This package turns the existing Microgifter quality, migration, security, commerce, gifting, recovery, release, rollback, and browser checks into one exact-head launch certification.

Accessibility Center and WCAG controls are intentionally outside this package and remain tracked separately.

## Automated launch gate

The automated score is 100 points:

| Gate | Points |
| --- | ---: |
| Launch contract on PHP 8.2 | 5 |
| Launch contract on PHP 8.3 | 5 |
| Repository production-quality gate | 10 |
| Canonical migration manifest and full-upgrade build | 10 |
| MySQL 8 migration, security, commerce, gifting, and recovery | 15 |
| MariaDB 10.11 migration, security, commerce, gifting, and recovery | 15 |
| Database backup and isolated restore | 10 |
| Reproducible candidate release package | 10 |
| Candidate and rollback package validation | 10 |
| Chromium production golden-path browser smoke test | 10 |

All required evidence must report `passed` for the exact candidate head. A partial score is not launch certification.

## What the database jobs prove

Each supported database job must:

1. Validate the canonical migration manifest.
2. Build the full upgrade SQL artifact.
3. Apply all migrations to a clean database.
4. Re-run migrations without creating a second effect.
5. Validate database migration status.
6. Run session/security foundation tests.
7. Run money and ledger behavior checks.
8. Run Microgift and PPPM behavior checks.
9. Run checkout, capture, issuance, notification, receipt, idempotency, and rollback behavior.
10. Run lifecycle completion and active recovery checks.

The MySQL 8 job also creates a consistent compressed backup, verifies its checksum, restores it into an isolated database, verifies table and migration counts, verifies a canary record, and validates restored-database migration readiness.

## Release and rollback proof

The release job must:

1. Build the candidate artifact twice from the exact head.
2. Confirm both artifacts have the same SHA-256 digest.
3. Build the rollback artifact from the PR base commit.
4. Verify checksums and safe archive paths.
5. Verify required runtime files and exclusion of secrets/development files.
6. Lint PHP in both artifacts.
7. Record candidate and rollback release metadata.
8. Require database rollback through restoration of the matching predeployment database backup rather than reverse migrations.

## Browser proof

The browser job builds a clean MySQL database, starts the application with persistent local test media storage, and runs the existing V1 Playwright golden path in Chromium.

Browser certification is a smoke gate. Final pre-launch testing should still include current Chrome, Edge, Firefox, Safari, iPhone, Android, keyboard-only use, slow-network behavior, interrupted requests, session expiry, and browser back/forward behavior.

## Manual production sign-off

Automated certification is necessary but not sufficient. Copy `docs/production-launch-manual-evidence-template-v1.json` outside the repository, fill it with controlled production evidence, and provide it to the evidence aggregator.

Required approvals:

- production environment and secret/configuration readiness
- live payment provider and webhook behavior
- SPF, DKIM, DMARC, transactional delivery, bounce handling, and critical-message delivery
- qualified legal review
- launch/beta/pilot/disabled feature-scope decision
- database and media backup retention, encryption, access, and off-host storage
- real production-like restore drill with recovery time
- support, account recovery, refunds, incidents, escalation, and first-week monitoring
- exact release, snapshot, deployment, and rollback approval

Do not commit live credentials, personal data, backup archives, or private production evidence to the repository.

## Local contract validation

```bash
composer install --no-interaction --prefer-dist --no-progress
composer validate-launch-contract
```

## Assemble an automated evidence report

```bash
php scripts/build_production_launch_evidence_v1.php \
  --evidence-dir=build/production-launch-evidence \
  --expected-head="$(git rev-parse HEAD)" \
  --output=build/production-launch-evidence/production-launch-certification-v1.json \
  --gate
```

## Add manual evidence for a final launch decision

```bash
php scripts/build_production_launch_evidence_v1.php \
  --evidence-dir=build/production-launch-evidence \
  --expected-head="$(git rev-parse HEAD)" \
  --manual-evidence=/secure/path/production-launch-manual-evidence-v1.json \
  --output=build/production-launch-evidence/production-launch-certification-v1.json \
  --gate
```

The `--gate` option fails only when automated evidence is missing, failed, or does not belong to the expected head. Manual sign-off remains visibly pending until every required approval is complete.

## Deployment sequence

1. Freeze the exact certified head.
2. Create and verify the candidate release artifact and checksum.
3. Create and verify the rollback artifact and checksum.
4. Create a predeployment database backup and persistent-media snapshot.
5. Record the current production commit, schema state, and health status.
6. Put state-changing jobs into the approved deployment posture.
7. Deploy the verified candidate artifact while preserving server-local configuration and persistent runtime data.
8. Apply the canonical migrations once.
9. Verify migration status, media storage, health, readiness, login, checkout, gifting, claim, redemption, refund, email, and webhook behavior.
10. Record release evidence and begin first-week monitoring.

## Rollback sequence

1. Stop or contain state-changing traffic.
2. Restore the matching predeployment database backup.
3. Restore the matching persistent-media snapshot if media changed.
4. Replace candidate code with the verified rollback artifact.
5. Run media-storage, health, readiness, authentication, checkout, and gifting checks.
6. Record the incident, rollback decision, recovery time, and follow-up repair work.

Never attempt production database rollback by guessing or running unverified reverse migrations.
