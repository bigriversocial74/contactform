# Main Admin Agent Phase 6 — Final Readiness

Phase 6 completes the core Main Admin Agent program with a browser-driven readiness workflow. Routine use does not require SSH, Bash, or terminal access.

## New capabilities

- Five-state readiness bar: Configured, Evidence Current, Drill Verified, Alerting Active, and Production Ready.
- One-click complete system analysis.
- Browser upload of the approved validation JSON report.
- Automatic scheduler-health detection.
- Continuity alerts in the Admin Notification Center.
- Recovery-drill calendar and reminders.
- Evidence attestations.
- Daily, weekly, and manual continuity briefs.
- Downloadable readiness JSON packages.
- Safe retention previews with no automatic deletion.
- Phase 1–5 compatibility and fallback.
- Database-only reporting with no AI-credit use.

## SQL

Import after Phase 5:

```text
database/20260719_main_admin_agent_phase6.sql
```

## Browser activation

After deployment and SQL import:

1. Sign in as an administrator.
2. Open `/admin/admin-agent.php`.
3. Click **Run final readiness check**.
4. Review the five readiness states.
5. Review remaining objectives, plans, alerts, and drill schedules.
6. Upload the approved validator JSON when the hosting or deployment team provides it.
7. Generate the readiness export when the required checks are complete.

No terminal command is required for these steps.

## Validation JSON handoff

The protected hosting or deployment process produces:

```text
build/release-evidence/backup-restore-validation.json
```

The administrator only needs to select that JSON file on the Admin Agent page and click **Upload validator JSON**.

The upload records verification metadata such as the run identifier, status, checksum, counts, canary result, migration-manifest result, migration-state result, timestamps, and report name. It does not store database contents or credentials.

A simple request to hosting support is:

> Please run the approved Microgifter database validation procedure and send me only the generated `backup-restore-validation.json` report. Do not send database data or credentials.

## Manual mode

The Admin Agent works in manual mode without an automatic scheduler. Click **Run final readiness check** whenever a current system analysis is needed.

A manual run refreshes system health, findings, events, anomaly and correlation results, reliability status, continuity scorecards, alerts, schedules, and final readiness checks.

## Automatic monitoring

Automatic monitoring requires one scheduled task in the hosting control panel. The Admin Agent page displays the exact setup line and a **Copy setup line** button.

Typical hosting-panel steps:

1. Open the hosting control panel.
2. Open **Cron Jobs** or **Scheduled Tasks**.
3. Choose **Every 5 Minutes**.
4. Paste the line shown on `/admin/admin-agent.php`.
5. Replace the example project path with the real website project path.
6. Save the scheduled task.
7. Return to the Admin Agent page within ten minutes.
8. Confirm the scheduler state changes to **Healthy**.

When the hosting panel is unfamiliar, send the displayed line to hosting support and ask them to add it as an every-five-minute PHP scheduled task.

Only the Phase 6 runner should be scheduled. Phase 6 includes the complete Phase 1–5 monitoring pipeline.

## Continuity alerts

Phase 6 can notify administrators about:

- Critical or recurring readiness gaps.
- Stale or unsuccessful validation evidence.
- Recovery-objective breaches.
- Due or overdue drills.
- Critical continuity scorecards.
- A stale automatic scheduler.

Alerts support acknowledgement, escalation, resolution, dismissal, ownership, deadlines, recurrence, and evidence history.

## Briefs and exports

Daily and weekly briefs can be enabled from **Phase 6 settings**. A manual brief can be sent from **Send continuity brief**.

**Generate readiness export** downloads a JSON package containing the readiness score, scheduler status, scorecards, alerts, drill calendar, objectives, plans, recent evidence, attestations, and retention preview.

## Retention safety

Phase 6 only previews records that match configured retention windows. It does not delete historical records automatically.

## Security boundary

Phase 6 does not expose credentials, store database contents, perform infrastructure operations from the browser, delete historical evidence automatically, perform financial or customer-facing mutations, use an external language model, or weaken the explicit execution permission boundary.

## Completion criteria

The core Main Admin Agent program is complete when:

1. Phase 6 code is deployed.
2. Phase 6 SQL is imported.
3. The browser readiness check completes.
4. Objectives and plans are reviewed.
5. Validation JSON is uploaded when supplied.
6. Critical alerts are addressed.
7. Critical and high-tier drills are verified.
8. Automatic scheduling is healthy, or manual-mode ownership is documented.
9. A readiness export is generated.

After successful production activation, GitHub issue #1201 can be closed. Future enhancements should use separate scoped issues.
