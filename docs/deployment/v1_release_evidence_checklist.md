# V1 Release Evidence Checklist

Use one copy of this checklist per candidate commit. Attach the generated `v1-release-evidence.json` and the referenced workflow artifacts.

## Candidate identity

- [ ] Release version recorded
- [ ] Candidate Git commit SHA recorded
- [ ] Candidate artifact filename recorded
- [ ] Candidate artifact SHA-256 verified
- [ ] Candidate `RELEASE.json` matches the intended commit
- [ ] Rollback Git commit SHA recorded
- [ ] Rollback artifact SHA-256 verified

## Automated repository evidence

- [ ] Pull Request Validation / Recovery Baseline passed
- [ ] Browser Validation passed
- [ ] Golden Path Integrity Validation passed
- [ ] Commerce Operations Validation passed
- [ ] Admin Account Management Validation passed
- [ ] Release Package Validation passed
- [ ] Candidate artifact was reproduced twice with the same checksum
- [ ] Candidate artifact excludes secrets and development-only paths
- [ ] Candidate artifact contains production Composer dependencies
- [ ] Isolated database backup and restore passed
- [ ] Restored canary record was verified
- [ ] Restored canonical migration manifest passed
- [ ] Candidate and rollback package PHP syntax passed
- [ ] Combined release evidence JSON retained

## Target staging backup evidence

- [ ] Staging database backup created immediately before deployment
- [ ] Staging database backup checksum verified
- [ ] Staging database backup restored into an isolated database
- [ ] Restored staging canary and migration manifest verified
- [ ] Persistent media backup created or documented as empty
- [ ] Persistent media backup checksum verified
- [ ] Server-only environment configuration preserved outside the archive

## Staging deployment evidence

- [ ] Candidate uploaded without extracting over the active release
- [ ] Uploaded `RELEASE.json` matches candidate commit
- [ ] PHP runtime and required extensions verified
- [ ] Canonical migrations completed
- [ ] Persistent media storage check passed
- [ ] Feed media migration dry run reviewed
- [ ] Staging web root switched using the recorded method
- [ ] `/api/health.php` is healthy
- [ ] `php scripts/validate_launch_readiness.php` passed
- [ ] No unresolved SEV1 or SEV2 incident exists

## Stripe and payment evidence

- [ ] Real Stripe test-provider workflow passed with the stub disabled
- [ ] Test connected account has charges and payouts enabled
- [ ] Staging webhook endpoint is configured in Stripe test mode
- [ ] Real hosted Checkout completed
- [ ] Signed webhook was accepted
- [ ] Exact webhook replay remained idempotent
- [ ] Conflicting replay was rejected
- [ ] Order became paid once
- [ ] Ledger remained balanced
- [ ] Platform share matched the configured snapshot
- [ ] Receipt, PPPM, Microgift, Action Center, and notifications were created once

## Ownership and redemption evidence

- [ ] Purchased gift appeared in the purchaser Action Center
- [ ] Send or regift completed
- [ ] Non-owner transfer was rejected
- [ ] Most recent sender Follow Up completed
- [ ] Earlier sender could not access later conversation
- [ ] Authorized merchant-location redemption completed
- [ ] Invalid location or claim code was rejected without state change
- [ ] Recipient Claimed projection is correct
- [ ] Historical sender Sent projection is marked Redeemed
- [ ] Redeemed PPPM and Microgift cannot transfer again

## Rollback evidence

- [ ] Rollback artifact is available on the target host
- [ ] Rollback procedure and operator are recorded
- [ ] Target rollback drill restored the predeployment database backup
- [ ] Target rollback drill restored media when applicable
- [ ] Target rollback drill restored the prior code artifact
- [ ] Migration manifest, media check, launch readiness, and health passed after rollback

## Approval

- [ ] Every required production gate is passed
- [ ] No production gate is waived
- [ ] Release approver recorded
- [ ] Deployment window recorded

Until every required target and live check is complete, the release status remains **blocked** even when all repository automation is green.
