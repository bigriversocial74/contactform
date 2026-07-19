# Main Admin Agent Phase 4 — Reliability Governance

Phase 4 extends the database-first Main Admin Agent with maintenance governance, deployment change-risk assessment, historical reliability scorecards, deterministic capacity forecasts, incident-learning drafts, and review-gated prevention follow-ups.

## Prerequisites

Deploy and import the Main Admin Agent migrations in order:

1. `database/20260718_main_admin_agent_phase1.sql`
2. `database/20260718_main_admin_agent_phase2.sql`
3. `database/20260719_main_admin_agent_phase3.sql`
4. `database/20260719_main_admin_agent_phase4.sql`

The Phase 4 API and page fail closed for Phase 4 actions until the Phase 4 schema is present. Phase 1–3 reports remain available through the prior endpoints before import.

## Initial run

From the deployed application root:

```bash
php scripts/run_admin_agent_phase4.php --trigger=manual --environment=production
```

The run performs the complete Phase 1, Phase 2, Phase 3, and Phase 4 pipeline:

- Unified monitor scan and finding lifecycle.
- Metric capture, anomaly baselines, correlation, escalation, and summaries.
- Service topology, SLO/error-budget snapshots, incident workspaces, cause candidates, release gates, and scheduled briefs.
- Maintenance lifecycle reconciliation.
- Deployment change-risk assessment.
- 7-, 30-, and 90-day reliability scorecards.
- Metric-trend capacity forecasts.
- Incident-learning and prevention-follow-up generation.

## Scheduled operation

Replace the Phase 3 scheduled runner with:

```cron
*/5 * * * * cd /path/to/contactform && php scripts/run_admin_agent_phase4.php --trigger=scheduled --environment=production >> storage/logs/main-admin-agent-phase4.log 2>&1
```

Do not run Phase 1, Phase 2, Phase 3, and Phase 4 runners together. Phase 4 already executes the complete earlier pipeline.

## Maintenance windows

Maintenance windows record planned production work against all services or one registered service.

Supported states:

- `scheduled`
- `active`
- `completed`
- `canceled`

Supported context modes:

- `observe_only`
- `suppress_expected`

The second mode marks expected non-security conditions as planned context. It does **not** hide or delete findings. Security findings and critical evidence remain visible and continue to affect health, SLO, incident, and release reporting.

Times are stored in UTC. Confirm server and operator timezone assumptions before creating a window.

## Deployment change risk

The deterministic change-risk score uses available evidence such as:

- Critical or high-tier impacted services.
- Changed-file count supplied with deployment metadata.
- Migration count supplied with deployment metadata.
- Current critical and warning SLO burn.
- Active incident workspaces.
- Deployment concentration during the previous 24 hours.
- Whether an active maintenance window covers the change.

Risk levels are `low`, `medium`, `high`, and `critical`. The assessment is advisory. It does not deploy, freeze, roll back, or approve production changes.

## Reliability scorecards

Scorecards aggregate SLO snapshots and incident history for each registered service over:

- 7 days
- 30 days
- 90 days

The score combines average availability, remaining error budget, warning/critical snapshot frequency, and incident count. It is an internal governance signal, not a public uptime guarantee.

## Capacity forecasts

Capacity forecasts use stored Admin Agent metric samples from the previous fourteen days. At least two samples are required for a series.

The forecast stores:

- Current value.
- Daily trend.
- Seven-day projection.
- Thirty-day projection.
- A deterministic statistical capacity boundary based on observed and learned baseline values.
- Projected utilization and risk level.

Forecasts are trend estimates, not guarantees. They consume no external model and no AI credits.

## Incident learning

Resolved Admin Agent workspaces and linked resolved operations incidents produce deterministic learning drafts containing:

- Timeline count and approximate duration.
- Recorded impact summary.
- Highest-ranked root-cause hypothesis.
- Contributing evidence and confidence.
- Proposed prevention actions.

Root-cause statements are evidence-ranked hypotheses, not proof. An administrator must verify the cause before completing the learning record.

## Controlled prevention follow-up

Phase 4 enables one new remediation adapter:

```text
create_prevention_followup
```

The adapter creates a draft in the existing operations incident-review workflow and links the approved prevention follow-up.

Required control sequence:

1. An administrator requests the action with rationale.
2. A super administrator with `admin.admin_agent.execute` reviews it.
3. Approval requires a review note.
4. Execution requires the exact typed confirmation:

```text
EXECUTE create_prevention_followup
```

5. The adapter verifies that the linked operations incident is resolved.
6. The incident review and follow-up are created through the existing review service.
7. Audit and security records are written for success or failure.

The adapter cannot execute shell commands, financial operations, customer-facing actions, permission changes, provider changes, deployments, rollbacks, queue automation, or notification retries.

## Permissions

Phase 4 adds:

- `admin.admin_agent.maintenance`
- `admin.admin_agent.reliability`
- `admin.admin_agent.learning`
- `admin.admin_agent.forecasts`

Execution continues to require the explicit `admin.admin_agent.execute` permission, which has no fallback alias.

## Workspace

Open:

```text
/admin/admin-agent.php
```

Phase 4 adds maintenance, change-risk, reliability, capacity, incident-learning, and prevention-follow-up rails to the existing protected chat layout.

## Systematic reporting

All Phase 4 reports are database-only:

- No Anthropic request.
- No OpenAI request.
- No external model request.
- No AI-credit preflight.
- No AI-credit debit.

No AI credits are consumed.
