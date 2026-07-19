# Main Admin Agent Phase 2

Phase 2 extends the existing database-first Main Admin Agent with historical metrics, learned anomaly baselines, cross-system incident correlation, deployment awareness, escalation routing, executive summaries, runbook recommendations, and explicitly approved remediation adapters.

## Deployment order

1. Deploy the current `integration-from-repair-20260628` application code.
2. Import `database/20260718_main_admin_agent_phase2.sql` after the Phase 1 migration.
3. Open `/admin/admin-agent.php`.
4. Run the first full analysis.
5. Record the deployed commit.
6. Replace the Phase 1 scheduled command with the Phase 2 scheduled command.

Phase 1 remains available before the Phase 2 SQL import. Phase 2 features fail closed until every required Phase 2 table exists.

## First analysis

Run from the deployed application root:

```bash
php scripts/run_admin_agent_phase2.php --trigger=manual
```

The response contains the Phase 1 scan result plus metric sampling, anomaly detection, correlation, escalation, and executive-summary results.

## Scheduled analysis

Run every five minutes:

```cron
*/5 * * * * cd /path/to/contactform && php scripts/run_admin_agent_phase2.php --trigger=scheduled >> storage/logs/main-admin-agent-phase2.log 2>&1
```

The Phase 2 runner already runs the Phase 1 monitor pipeline. Do not keep both scheduled runners active after Phase 2 is deployed, because duplicate scans would distort metric frequency and produce unnecessary load.

## Baseline warm-up

Each numeric monitor metric requires at least eight samples before statistical anomaly detection begins. With the recommended five-minute schedule, initial baselines need approximately 40 minutes to warm up.

The baseline uses an online mean and variance calculation, so it does not need to load the complete historical sample table into memory. Anomalies are detected against the previous baseline before the new sample updates that baseline.

## Deployment recording

Record each production deployment after the code is uploaded:

```bash
php scripts/record_admin_agent_deployment.php \
  --commit=FULL_OR_SHORT_COMMIT_SHA \
  --branch=integration-from-repair-20260628 \
  --environment=production \
  --source=cli \
  --label="Production deployment"
```

The deployment recorder reruns correlation analysis so new high or critical conditions can be linked to the release window.

The scheduled runner can also record deployments automatically when these environment variables are available:

```text
MG_DEPLOY_COMMIT_SHA
MG_DEPLOY_BRANCH
MG_DEPLOY_ENV
MG_RELEASE_LABEL
```

`GIT_COMMIT_SHA` is accepted as a fallback for the commit SHA.

## Phase 2 chat reports

The existing `/admin/admin-agent.php` chat supports additional database-only commands:

- `Anomaly report`
- `Cross-system correlations`
- `Deployment impact report`
- `Escalation report`
- `Executive summary`
- `Controlled remediation report`

These reports do not contact an external model and consume no AI credits.

## Correlation rules

Phase 2 includes deterministic rules for:

- Critical conditions spanning multiple system domains
- Notification delivery risk combined with queue or SLA pressure
- Automation degradation combined with queue or SLA pressure
- Security conditions combined with concentrated administrative change activity
- New high or critical conditions following a recorded deployment
- AI accounting incidents combined with AI/provider-related security activity

Correlation records are durable, deduplicated, recurrent, assignable, reviewable, and automatically resolved when the triggering condition clears.

## Escalation delivery

Critical and high findings, anomalies, and correlations are matched to escalation policies. Due escalations are routed into the existing Admin Notification Center when its schema is available.

Escalations are idempotent by source and level. They can be scheduled, sent, acknowledged, suppressed, or resolved. A later analysis automatically resolves escalation records when the source condition is no longer active.

## Executive summaries

Every Phase 2 run refreshes the daily executive summary. A weekly summary is generated on Mondays. Administrators can also generate a manual summary from the chat page.

Summaries are stored in `admin_agent_executive_summaries` and include health, correlations, anomalies, deployments, and event counts.

## Controlled remediation

The execution boundary is deliberately narrow:

- Monitoring and reporting remain read-only by default.
- Action requests enter the existing Admin Agent review queue.
- Approval requires the explicit `admin.admin_agent.execute` permission.
- The migration grants that permission only to `super_admin`.
- Approval requires a review note.
- Execution is a separate operation and requires typing the exact confirmation `EXECUTE <adapter_key>`.
- Execution is idempotent by action-review record.
- Only enabled, in-process, allowlisted adapters can run.
- No shell command, dynamic include, remote code, or unrestricted callback execution is used.
- Every approval, execution, result, and failure is written to audit and security logs.

Enabled Phase 2 adapters:

- Run the full deterministic Admin Agent analysis
- Run the existing AI credit reconciliation service
- Generate a read-only canonical migration plan
- Generate a read-only security investigation package

Reserved but disabled adapters:

- Queue automation
- Failed-notification retry
- Incident declaration

Financial, destructive, customer-facing, provider-configuration, permission-changing, and arbitrary system actions remain disabled.

## Permissions

- `admin.admin_agent.view`
- `admin.admin_agent.chat`
- `admin.admin_agent.manage`
- `admin.admin_agent.actions`
- `admin.admin_agent.escalations`
- `admin.admin_agent.deployments`
- `admin.admin_agent.execute` — explicit super-admin execution boundary

## SQL

Required migration:

```text
database/20260718_main_admin_agent_phase2.sql
```
