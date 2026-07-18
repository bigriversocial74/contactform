# Main Admin Agent Phase 1

The Main Admin Agent is a protected, database-first system observer for Microgifter administration.

## Deployment order

1. Deploy the current application code.
2. Import `database/20260718_main_admin_agent_phase1.sql`.
3. Open `/admin/admin-agent.php` from the Operations Command Center.
4. Run the first system scan.
5. Configure the scheduled CLI monitor.

## Scheduled monitor

Run the hardened monitor every five minutes from the deployed application root:

```cron
*/5 * * * * cd /path/to/contactform && php scripts/run_admin_agent_monitor_runtime.php --trigger=scheduled >> storage/logs/main-admin-agent.log 2>&1
```

Manual verification:

```bash
php scripts/run_admin_agent_monitor_runtime.php --trigger=manual
```

A successful run returns JSON containing the health score, monitor count, normalized event count, created or updated findings, and automatically resolved findings.

## Monitored foundations

Phase 1 normalizes and correlates signals from:

- `security_logs`
- `audit_logs`
- operations incidents
- support queue and SLA records
- admin notification records
- admin queue automation runs
- AI credit reconciliation incidents
- the canonical migration manifest and `schema_migrations`

## Chat reports

The admin chat supports database-only commands including:

- `Overview`
- `What changed?`
- `Active findings`
- `Security report`
- `Operations report`
- `AI credit accounting`
- `Migration report`
- `Recent activity`

No AI credits are consumed. These reports do not contact an external model.

## Live updates

The chat workspace first attempts a bounded server-sent event connection through:

`/api/admin/admin-agent-stream.php`

When SSE is unavailable, the client falls back to bounded 15-second polling. The stream is permission-gated, rate-limited, private, non-cacheable, and releases the PHP session lock before waiting.

## Findings

Findings use deterministic keys so repeated monitor runs update the same condition instead of creating duplicates. Supported lifecycle states are:

- open
- acknowledged
- under review
- resolved
- dismissed

A later monitor scan automatically resolves a finding when its underlying condition is no longer detected. If the condition returns, the same finding is reopened and its recurrence history is preserved.

## Action boundary

The Main Admin Agent is read-only by default. It can prepare review requests for a limited allowlist of operational actions, but it does not execute them.

Financial, destructive, security-sensitive, customer-facing, or provider-facing changes require an explicit administrator review path and a separate controlled execution adapter.

## Permissions

- `admin.admin_agent.view`
- `admin.admin_agent.chat`
- `admin.admin_agent.manage`
- `admin.admin_agent.actions`

Admin and super-admin roles receive these permissions through the migration. Existing operations, health, audit, security, settings, and user-management permissions remain supported through the central permission matrix.
