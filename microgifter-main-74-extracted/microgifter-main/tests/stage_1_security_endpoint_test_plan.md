# Stage 1 Security Endpoint Test Plan

Status: Required before Stage 2 server sign-off.

## Test groups

### Auth brute-force and throttling
- POST `/api/auth/login.php` with repeated bad credentials until 429.
- POST `/api/auth/register.php` repeatedly from the same client until 429.
- POST `/api/auth/password/forgot.php` repeatedly until 429.
- POST `/api/auth/email/verify-request.php` repeatedly until 429.

### CSRF enforcement
- POST login/register/logout without CSRF when required by endpoint design.
- PATCH `/api/me/profile.php` without CSRF: expect 419.
- DELETE `/api/me/sessions.php` without CSRF: expect 419.
- DELETE `/api/admin/sessions.php` without CSRF: expect 419.

### DB-backed sessions
- Login and confirm a row exists in `user_sessions`.
- Call `/api/auth/me.php`: expect authenticated user.
- Revoke current session in DB, then call `/api/auth/me.php`: expect guest/unauthenticated state.
- Reset password and verify old sessions are revoked.
- Logout and verify current session is marked revoked.

### Permissions
- Normal user requests `/api/admin/audit-logs.php`: expect 403.
- Normal user requests `/api/admin/health.php`: expect 403.
- Normal user requests `/api/admin/sessions.php`: expect 403.
- Admin with explicit permissions can access admin endpoints.
- Admin without explicit session permission cannot access session endpoints.
- Super admin bypass is still allowed for emergency access.

### Session management endpoints
- GET `/api/me/sessions.php`: expect own sessions only.
- DELETE `/api/me/sessions.php` with `mode=all_except_current`: expect other sessions revoked.
- DELETE `/api/me/sessions.php` with `mode=all`: expect all user sessions revoked and redirect to sign in.
- GET `/api/admin/sessions.php`: expect protected session list.
- DELETE `/api/admin/sessions.php` by `session_id`: expect target session revoked.
- DELETE `/api/admin/sessions.php` by `user_id`: expect all target user sessions revoked.

### Security logging
- Failed login creates a `security_logs` record.
- Rate-limit block creates a `security_logs` record.
- Permission denied creates audit and security records.
- Revoked session access creates a security record.

## Stage gate

Stage 2 should not begin until these tests are either automated or manually executed against the target server/database and documented in the smoke checklist.
