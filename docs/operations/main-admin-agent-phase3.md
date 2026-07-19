# Main Admin Agent Phase 3

Phase 3 extends the database-first Main Admin Agent with service topology, dependency-aware health, SLO and error-budget tracking, deterministic incident workspaces, ranked cause candidates, release-readiness gates, scheduled executive brief delivery, and one newly enabled controlled remediation adapter for operations-incident declaration.

## Deployment order

1. Deploy the current `integration-from-repair-20260628` application code.
2. Confirm the Phase 1 and Phase 2 migrations are already imported.
3. Import `database/20260719_main_admin_agent_phase3.sql`.
4. Open `/admin/admin-agent.php`.
5. Run the first full Phase 3 analysis.
6. Replace the Phase 2 scheduled runner with the Phase 3 scheduled runner.

Phase 1 and Phase 2 remain available before the Phase 3 SQL import. Phase 3 controls fail closed until every required Phase 3 table exists.

## Initial analysis

Run from the deployed application root:

```bash
php scripts/run_admin_agent_phase3.php --trigger=manual --environment=production
```

The Phase 3 runner already executes the complete Phase 2 pipeline, which includes the Phase 1 monitor scan. Do not run Phase 1, Phase 2, and Phase 3 runners together.

## Scheduled analysis

Recommended five-minute schedule:

```cron
*/5 * * * * cd /path/to/contactform && php scripts/run_admin_agent_phase3.php --trigger=scheduled --environment=production >> storage/logs/main-admin-agent-phase3.log 2>&1
```

## Service topology

The migration seeds the first operational service registry:

- Identity and access
- Database core
- Commerce and payments
- Claims and redemption
- Notification delivery
- Administrative automation
- Admin operations
- Security observability
- AI accounting
- Campaign delivery
- Storefront experience

Dependencies are durable records. Service health combines current domain findings with the latest SLO snapshot. The dependency graph is advisory and does not change production traffic or configuration.

## SLO and error budgets

Each active service receives a daily availability policy. The first implementation uses deterministic platform evidence:

- Error and critical normalized events in the service domain
- Active critical, high, and medium findings in that domain
- Service tier and objective

The calculated output includes availability, remaining error budget, burn rate, and healthy/warning/critical state. The calculation is an internal operational SLO signal, not a contractual external uptime guarantee.

## Incident workspaces

High and critical cross-system correlations automatically create or refresh an Admin Agent incident workspace. Each workspace stores:

- Correlation and affected service
- Severity and lifecycle state
- Incident commander
- Timeline events and operator notes
- Runbook and recommended action
- Linked operations incident, when one is approved and declared
- Ranked deterministic cause candidates

Workspaces automatically resolve when the source correlation clears. Administrators can assign themselves, add notes, move the workspace through investigation states, and resolve it with a required note.

## Cause analysis

Cause candidates are evidence-ranked hypotheses, not proof. Phase 3 considers:

- Deployments within six hours before incident onset
- Learned anomalies in affected domains
- Durable findings in affected domains
- Degraded upstream service dependencies
- Concentrated administrative change activity

The Admin Agent records confidence, evidence, source, and ranking for each candidate. Human review remains required before treating any candidate as root cause.

## Release readiness

The production release gate evaluates:

- Overall system health
- Critical and warning SLO burn
- Active and critical incident workspaces
- Critical findings first detected after the latest deployment

Gate states are:

- `pass` — no blocking evidence and healthy operating conditions
- `warn` — deploy only with deliberate review
- `block` — critical evidence requires resolution or an explicit operational decision outside this tool

The gate is advisory. It does not deploy, roll back, freeze, or modify production by itself.

## Scheduled briefs

Administrators can configure daily or weekly Notification Center briefs. Delivery is idempotent by administrator, cadence, and period. Briefs use stored database-only executive summaries and consume no AI credits.

## Controlled operations incident declaration

Phase 3 enables the existing `declare_operations_incident` remediation adapter.

The boundary is intentionally narrow:

1. An administrator requests incident declaration from an incident workspace.
2. The request enters the existing action-review queue.
3. A user with the explicit `admin.admin_agent.execute` permission must approve it with a review note.
4. Execution is a separate operation.
5. The approver must type `EXECUTE declare_operations_incident` exactly.
6. The existing operations-incident service creates the incident and notification.
7. The incident workspace is linked to the new operations incident.
8. Review, execution, result, and failure are written to audit and security logs.

The explicit execution permission remains seeded only to `super_admin`. Queue automation, notification retry, financial actions, destructive actions, permission changes, customer-facing mutations, arbitrary callbacks, shell commands, and provider-configuration changes remain disabled.

## Permissions

- `admin.admin_agent.view`
- `admin.admin_agent.chat`
- `admin.admin_agent.manage`
- `admin.admin_agent.actions`
- `admin.admin_agent.escalations`
- `admin.admin_agent.deployments`
- `admin.admin_agent.incidents`
- `admin.admin_agent.releases`
- `admin.admin_agent.briefs`
- `admin.admin_agent.execute` — explicit super-admin execution boundary

## SQL

Required migration:

```text
database/20260719_main_admin_agent_phase3.sql
```

## No AI-credit usage

Topology, SLO calculations, incident synchronization, cause ranking, release gates, brief delivery, and chat reports are database-first and deterministic. No AI credits are consumed by Phase 3 monitoring or reporting.
