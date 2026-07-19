# Main Admin Agent Phase 5 — Recovery Assurance

Phase 5 extends the Main Admin Agent with business-continuity governance. It records and evaluates recovery evidence; it does not perform production recovery operations.

## Scope

- Per-service recovery objectives with RTO, RPO, backup-evidence age, drill cadence, criticality, owner, and review state.
- Backup and isolated restore-validation evidence from the existing `scripts/validate_database_backup_restore.sh` procedure.
- Planned and externally executed restore-drill records.
- Dependency-aware service recovery plans with recovery order, prerequisites, validation steps, owner, and runbook path.
- Continuity scorecards and durable recovery gaps.
- Review-gated approval of a completed external drill record.
- Database-only reports with no AI-credit consumption.

## Safety boundary

The Admin Agent does not:

- Run the database backup-and-restore validator.
- Restore production data.
- Execute failover, rollback, database-import, shell, or infrastructure commands.
- Read or store database passwords, provider secrets, private keys, or backup contents.
- Approve an external drill without passing evidence and exact typed confirmation.

The only Phase 5 adapter changes the state of an already completed drill record after review. The required confirmation is:

```text
EXECUTE approve_recovery_drill_record
```

## SQL

Import after Phase 4:

```text
database/20260719_main_admin_agent_phase5.sql
```

## Initial deployment

1. Deploy the current `integration-from-repair-20260628` code.
2. Import the Phase 5 SQL after the Phase 4 SQL.
3. Run the first continuity analysis:

```bash
php scripts/run_admin_agent_phase5.php --trigger=manual --environment=production
```

4. Open:

```text
/admin/admin-agent.php
```

5. Review seeded recovery objectives and recovery plans before marking them active/ready.

## Scheduled operation

Replace the Phase 4 runner with:

```cron
*/5 * * * * cd /path/to/contactform && php scripts/run_admin_agent_phase5.php --trigger=scheduled --environment=production >> storage/logs/main-admin-agent-phase5.log 2>&1
```

Do not run Phase 1, Phase 2, Phase 3, Phase 4, and Phase 5 runners together. Phase 5 includes the complete earlier pipeline.

## Record database recovery evidence

Run the existing isolated validator using the documented deployment account and environment variables:

```bash
bash scripts/validate_database_backup_restore.sh
```

The validator creates a consistent backup, verifies its checksum, restores into a separate temporary database, checks the canary, table count, migration count, canonical migration manifest, and restored migration state, then removes the temporary database.

Import only the JSON evidence report:

```bash
php scripts/record_admin_agent_recovery_evidence.php \
  --file=build/release-evidence/backup-restore-validation.json \
  --environment=production \
  --scope=database
```

The importer does not ingest backup contents or credentials. It records the run ID, status, checksum metadata, counts, canary result, manifest result, migration-state result, timestamps, and report path.

Run Phase 5 again after importing evidence:

```bash
php scripts/run_admin_agent_phase5.php --trigger=manual --environment=production
```

## Restore drill lifecycle

1. Create a planned drill record in `/admin/admin-agent.php`.
2. Execute the approved drill outside the Admin Agent using the operator runbook.
3. Import the validator evidence.
4. Record observed RTO, RPO, and a result summary against the planned drill.
5. Failed or incomplete evidence marks the drill failed.
6. Passing evidence moves the record to `review_ready`.
7. Submit `approve_recovery_drill_record` to the existing review queue.
8. A super-admin approves with a review note.
9. Execute with exact typed confirmation.
10. The adapter marks only the record as passed and records any RTO/RPO miss.

## Recovery gaps

The deterministic evaluator records gaps for:

- Unreviewed objectives.
- Missing, failed, incomplete, or stale evidence.
- Missing or overdue drills.
- RTO or RPO misses.
- Missing or unready recovery plans.

Gaps support acknowledgement, review, resolution, dismissal, ownership, recurrence, and evidence history. Resolved gaps reopen when the condition returns.

## Continuity scores

Each active service receives a continuity score from 0 to 100 using current objective, evidence, drill, plan, and gap state. The score is an internal governance signal, not an uptime guarantee or proof that recovery will succeed under every condition.

## Existing operator runbooks

Phase 5 links recovery plans to:

```text
docs/operations/UPGRADE_ROLLBACK_RESTORE_RUNBOOK.md
```

Actual operator actions remain governed by that runbook and the existing production deployment procedures.

## Validation

The Phase 5 workflow validates on PHP 8.2 and PHP 8.3:

- PHP and JavaScript syntax.
- Schema and migration ordering.
- Objective, evidence, drill, plan, scorecard, and gap contracts.
- Permission and fail-closed execution boundaries.
- Chat and workspace compatibility with Phases 1–4.
- Bounded SSE and polling fallback.
- CLI runner and evidence importer.
- Focused deploy artifact.

No AI credits are consumed by Phase 5 monitoring, evaluation, or reporting.
