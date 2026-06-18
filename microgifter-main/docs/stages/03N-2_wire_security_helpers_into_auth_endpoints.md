# 03N-2 Microgifter Wire Security Helpers Into Auth Endpoints

## Purpose

This pass connects the Stage 1 security helper layer to the active authentication endpoints where the GitHub connector allowed safe targeted edits.

## Completed

Updated active auth endpoints:

- `api/auth/login.php`
- `api/auth/register.php`
- `api/auth/logout.php`
- `api/auth/password/forgot.php`
- `api/auth/password/reset.php`
- `api/auth/email/verify-request.php`
- `api/auth/email/verify.php`

## Security improvements added

### Login

- Requires `api/security.php`.
- Applies rate limits by IP and email.
- Writes structured security logs for invalid input, failed login, blocked inactive-account login, and endpoint failures.
- Records DB-backed user session metadata after successful login.
- Clears login rate-limit records after successful login.

### Registration

- Requires `api/security.php`.
- Applies rate limits by IP and email.
- Writes structured security logs for invalid registration input, duplicate account attempts, and endpoint failures.
- Records DB-backed user session metadata after account creation.
- Clears registration rate-limit records after successful account creation.

### Logout

- Requires `api/security.php`.
- Revokes the current DB-backed session record.
- Corrects the audit/event call shape.
- Clears the PHP session identity and regenerates the session ID.

### Password recovery

- Forgot-password now rate-limits by IP and email.
- Forgot-password now expires existing unused reset tokens before creating a new one.
- Reset-password now rate-limits by IP and token hash.
- Reset-password now marks all active reset tokens used after a successful reset.
- Reset-password revokes all DB-backed user sessions after the password changes.
- Reset-password writes audit/event records.

### Email verification

- Verification request is rate-limited by IP and user.
- Verification request expires existing unused verification tokens before creating a new one.
- Verification endpoint is rate-limited by IP and token hash.
- Verification endpoint writes audit/event records.

## Blocked by connector safety layer

The connector blocked targeted edits to:

- `api/bootstrap.php`
- `api/auth/me.php`
- `api/me/profile.php`

Because of that, security helpers are currently included directly in the auth endpoints patched above. The following should still be completed in a smaller future pass:

1. Include and apply security headers globally from `api/bootstrap.php`.
2. Make `mg_set_session_user()` record DB-backed session metadata globally instead of per-endpoint.
3. Make `mg_refresh_session_user()` validate the DB-backed session record.
4. Remove the broad `admin` permission shortcut in `mg_api_user_has_permission()` so only `super_admin` has global emergency override.
5. Add profile update rate limiting to `api/me/profile.php`.
6. Add `api/auth/me.php` validation against DB-backed sessions.

## Current production-readiness impact

This pass materially improves authentication abuse resistance, recovery-token safety, logout correctness, and password-reset session invalidation. It does not fully complete global session enforcement yet because the API bootstrap patch was blocked.

## Required install order

Make sure these migrations have been imported before testing this pass:

1. `database/stage_1_identity.sql`
2. `database/stage_1_repair_03M.sql`
3. `database/stage_1_security_hardening_03N.sql`

## Next recommended pass

`03N-3_microgifter_global_session_enforcement`

Focus:

- Patch bootstrap in the smallest possible chunks.
- Enforce DB-backed session validation across all protected APIs.
- Patch profile update throttling.
- Add an admin-only session listing/revoke endpoint.
