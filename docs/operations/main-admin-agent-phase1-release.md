# Main Admin Agent Phase 1 release checklist

- Deploy the merged integration branch.
- Import `database/20260718_main_admin_agent_phase1.sql`.
- Open `/admin/operations-command.php` and choose **Open Main Admin Agent**.
- Confirm `/admin/admin-agent.php` loads the protected chat workspace.
- Run the first system scan.
- Confirm normalized events, monitor status, findings, and health score load.
- Send `Overview`, `What changed?`, `Security report`, and `Migration report` through chat.
- Confirm each response is marked database-first and no AI credits are consumed.
- Confirm an action request enters the review queue without executing.
- Configure the five-minute scheduled runner from `docs/operations/main-admin-agent.md`.
