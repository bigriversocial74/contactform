# Microgifter Security/Stability Improvement Recommendations

## Current principle

Microgifter should be built as a zero-trust PHP/MySQL platform. No frontend control is security. Every endpoint must authenticate, authorize, validate, rate-limit where appropriate, audit sensitive actions, and fail safely.

## Immediate must-fix items before Stage 2

1. **Patch active logout endpoint**
   - Current active `api/auth/logout.php` must be corrected before server testing.
   - It should use `mg_current_user()`, revoke the current DB-backed session, clear `$_SESSION['mg_user']`, regenerate the session ID, and return the redirect.

2. **Wire rate limits into active endpoints**
   - Login: IP + email composite key.
   - Registration: IP + email composite key.
   - Password forgot/reset: IP + email/token-based key.
   - Email verification: IP + token hash key.
   - Profile update: user ID + IP key.

3. **Make DB-backed sessions active**
   - Write a `user_sessions` record after login/register.
   - Touch `last_seen_at` on authenticated requests.
   - Reject revoked/expired DB sessions.
   - Revoke current session on logout.
   - Revoke all sessions after password reset.

4. **Remove broad admin bypass**
   - Keep `super_admin` as the only emergency global bypass.
   - Make `admin` rely on explicit role permissions.

5. **Split public and admin health checks**
   - Public health should only return shallow OK/unavailable.
   - Detailed health belongs behind `admin.health.view`.

6. **Add operational logging**
   - Keep writing to `security_logs`.
   - Also keep server `error_log` fallback.
   - Add request IDs to logs and API responses.

## High-volume foundation recommendations

1. **Outbox/event queue**
   - Add an outbox table for email, webhooks, and internal events.
   - Do not send email synchronously from user-facing requests.

2. **Structured migrations**
   - Continue using `schema_migrations`.
   - Every new stage should add a migration key.
   - Migrations should be idempotent where possible.

3. **Database indexing discipline**
   - Review every new lookup path before Stage 2.
   - Add indexes for foreign keys, status fields, user ownership fields, and created timestamps.

4. **Automated endpoint tests**
   - Every protected endpoint needs tests for guest denied, normal user denied, authorized user allowed, CSRF missing denied, invalid method denied, bad input denied, and audit/security log behavior.

5. **Production web server hardening**
   - Keep `.htaccess` for Apache/cPanel.
   - Add Nginx equivalent in deployment docs if the server stack changes.
   - Keep `docs`, `database`, `tests`, `includes`, `.env`, and `.sql` blocked from direct browser access.

6. **Secrets and environment control**
   - Production secrets must never live in GitHub.
   - Use environment variables or server-local ignored files.
   - Rotate credentials before launch.

7. **Admin protection**
   - Add MFA before production admin launch.
   - Add admin session age checks before high-risk actions.
   - Add forced re-auth for role changes, payout/billing changes, and destructive actions.

8. **Abuse monitoring**
   - Build dashboards or reports for login failures, reset requests, denied permissions, locked rate-limit identifiers, and suspicious IPs.

## Suggested next pass

`03N-2_microgifter_wire_security_helpers_into_auth_endpoints`

After that, run:

`03O_microgifter_high_volume_foundation`

The 03O pass should introduce queue/outbox architecture, request tracing, index review, migration runner documentation, and load-ready deployment notes.
