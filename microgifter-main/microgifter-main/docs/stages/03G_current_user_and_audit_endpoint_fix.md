# 03G Current User and Audit Endpoint Fix

## Scope

This pass completed the two endpoint fixes left over from the 03F auth permissions hardening pass.

## Updated files

- `api/auth/me.php`
- `api/admin/audit-logs.php`

## Current user endpoint

`api/auth/me.php` now calls `mg_refresh_session_user()` instead of returning only the stored session payload. This keeps the current-user response aligned with the latest database-backed roles and permissions.

## Audit logs endpoint

`api/admin/audit-logs.php` now requires the `admin.audit.view` permission and reads recent rows from the `audit_logs` table. It supports a bounded `limit` query parameter with a maximum of 100 records.

## Security notes

- The audit endpoint is no longer a placeholder.
- Audit log access is server-side permission protected.
- The current user payload is refreshed from the database before returning roles/permissions.

## Next recommended pass

`03H_microgifter_logout_account_header_flow`

Focus:

1. Add consistent account/logout UI to the shared header.
2. Make `account.php` display current user roles/permissions.
3. Add logout button behavior using `/api/auth/logout.php`.
4. Verify guest/authenticated nav state on `index.php`, `build.php`, and `agent.php`.
