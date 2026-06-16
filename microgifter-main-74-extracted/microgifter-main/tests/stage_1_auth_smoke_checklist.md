# Stage 1 Auth Smoke Checklist

## Database

- Import `database/stage_1_identity.sql`.
- If the baseline SQL was already imported before 03M, also import `database/stage_1_repair_03M.sql`.
- Confirm the Stage 1 identity tables exist.
- Confirm seeded roles and permissions exist.
- Confirm `users`, `user_profiles`, `user_sessions`, `user_roles`, `roles`, `permissions`, `role_permissions`, `password_reset_tokens`, `email_verification_tokens`, `audit_logs`, `events`, `system_actors`, and `platform_fee_settings` exist.
- Confirm the repair permissions exist: `user.profile.update`, `admin.users.manage`, `admin.roles.manage`, and `system.health.view`.

## Environment

Configure the database connection with server environment variables before testing.

Required names:

```text
MG_DB_HOST
MG_DB_NAME
MG_DB_USER
MG_DB_PASS
MG_BASE_URL
MG_DEBUG
```

## Public page loading

- Open `/index.php` as a guest.
- Confirm the guest header shows Sign in and Create account.
- Open `/build.php` as a guest.
- Confirm the builder page loads shared styles and builder script behavior.
- Open `/agent.php` as a guest.
- Confirm the guest view does not expose inbox/admin data.
- Open `/api/health.php` and confirm JSON returns a service status and database status.

## Auth pages

- Open `/signup.php`.
- Create a test account with a password that meets the page requirement.
- Confirm redirect to `/account.php`.
- Confirm the account page shows user email, roles, and permissions.
- Confirm a default `customer` role is attached.
- Open `/signin.php`.
- Sign in with the same account.
- Confirm redirect to `/account.php`.
- Open `/api/auth/me.php` while signed in and confirm `authenticated` is true.
- Confirm `/api/auth/me.php` includes normalized `roles` and `permissions` arrays.

## Profile endpoint

- While signed in, request `/api/me/profile.php` with `GET` and confirm profile data returns.
- Submit a `PATCH` request to `/api/me/profile.php` with a valid CSRF token and safe profile fields.
- Confirm `display_name`, `headline`, `bio`, and `avatar_url` validation works.
- Confirm profile updates write an audit log and event record.

## Logout flow

- While signed in, use the shared header Sign out control.
- Confirm `/api/auth/logout.php` clears the session and redirects to `/signin.php`.
- Confirm `/api/auth/me.php` returns unauthenticated after logout.
- Confirm the header returns to guest state after logout.

## Recovery and verification flows

- Open `/forgot-password.php` and submit the account email.
- Confirm the UI displays a generic success message.
- Confirm the API returns a generic success message without revealing whether the email exists.
- Confirm a reset token hash is stored for existing users only.
- Open `/reset-password.php` without a token.
- Confirm the reset button is disabled and the page shows a missing-token warning.
- Open `/reset-password.php?token=INVALID`.
- Submit matching 12+ character passwords and confirm the API returns an invalid/expired token error.
- Open `/verify-email.php` without a token.
- Confirm the verify button is disabled and the page shows a missing-token warning.
- Open `/verify-email.php?token=INVALID`.
- Confirm the API returns an invalid/expired token error.
- Email delivery is intentionally left for a later pass.

## Admin and audit checks

- As a normal customer, request `/api/admin/users.php` and confirm 403.
- As a normal customer, request `/api/admin/audit-logs.php` and confirm 403.
- With an admin/super_admin role, request `/api/admin/users.php` and confirm users return.
- With an admin/super_admin role, request `/api/admin/audit-logs.php?limit=25` and confirm newest logs return.
- Confirm `/api/admin/audit-logs.php` returns decoded `metadata` and does not reference missing SQL columns.

## Security checks

- Write requests without CSRF should fail.
- Login with invalid credentials should fail.
- Duplicate registration should fail.
- Passwords must be hashed.
- Session cookies should be HttpOnly.
- CSRF tokens must not be accepted from cross-origin requests.
- Auth/permission state must come from PHP/API, not localStorage.
- Guest pages must not receive protected inbox/admin data in markup or API responses.
- Confirm direct browser access to `docs/`, `database/`, `tests/`, and `.env` files is blocked on Apache/cPanel deployments.

## Known deferrals after 03M

- Public email delivery remains deferred.
- Automated PHPUnit/browser tests remain deferred.
- DB-backed session persistence table exists, but PHP session behavior remains the active Stage 1 runtime mechanism until deeper session management is built.
- Admin user-detail, status-update, and role assignment endpoints remain Stage 1 follow-up candidates unless promoted into the next repair pass.
