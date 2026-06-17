# 03N-3 Global Session Enforcement and Security Tests

## Purpose

This pass completes the six follow-up security items requested after 03N-2:

1. Patch global DB-backed session validation.
2. Patch profile/current-user validation and throttling where possible.
3. Remove broad admin bypass and keep only super_admin emergency override.
4. Add user/admin session management endpoints.
5. Add automated security test plan placeholders.
6. Verify the carry-forward hardening path before Stage 2.

## Files changed

- `api/bootstrap.php`
- `api/auth/me.php`
- `api/me/sessions.php`
- `api/admin/sessions.php`
- `database/stage_1_security_hardening_03N_3.sql`
- `tests/stage_1_security_endpoint_test_plan.md`

## Security changes

### Global DB-backed session validation

`api/bootstrap.php` now requires `api/security.php`, applies the central API security headers, records DB-backed session metadata in `mg_set_session_user()`, and checks `mg_session_is_active()` during `mg_refresh_session_user()`.

If a session is revoked or expired in the database, the PHP session user is cleared and the request becomes unauthenticated.

### Admin override tightened

`mg_api_user_has_permission()` no longer grants blanket access to normal `admin` role. Only `super_admin` keeps emergency/global override. Regular admins must now have explicit permission rows.

### Current user endpoint

`api/auth/me.php` now relies on `mg_refresh_session_user()`, so revoked/expired DB sessions resolve to a guest state.

### Session management endpoints

`api/me/sessions.php` allows authenticated users to list their own sessions and manage own session state.

`api/admin/sessions.php` allows permissioned admins to list and manage user sessions.

### Permission migration

`database/stage_1_security_hardening_03N_3.sql` adds explicit session-management permissions and assigns them to roles.

## Partial item

`api/me/profile.php` still needs a targeted patch to add the profile update rate limit. The GitHub connector blocked two full-file replacement attempts for that endpoint. This should be retried as a smaller patch or manually edited before final Stage 1 sign-off.

## New security test plan

`tests/stage_1_security_endpoint_test_plan.md` now defines tests for:

- Auth brute force and throttling
- CSRF enforcement
- DB-backed sessions
- Permissions
- Session management endpoints
- Security logging

## Stage 2 gate

Do not begin Stage 2 production work until:

1. `database/stage_1_security_hardening_03N.sql` is imported.
2. `database/stage_1_security_hardening_03N_3.sql` is imported.
3. Login creates a DB-backed `user_sessions` row.
4. Revoking that row invalidates `/api/auth/me.php`.
5. Normal admin users require explicit permissions.
6. Session management endpoints pass manual smoke testing.
7. Profile update throttling is patched or formally deferred.
