# 03N Microgifter Security/Stability Hardening

## Purpose

This pass strengthens the Stage 1 foundation before Stage 2 by adding defense-in-depth pieces that are required for a secure, stable, higher-volume PHP/MySQL platform.

No system is unhackable. The production goal is layered security: small attack surface, server-side authorization, rate limiting, token hashing, session revocation, operational logging, and repeatable database migrations.

## Completed in this pass

### Database migration

Added:

- `database/stage_1_security_hardening_03N.sql`

This migration creates:

- `schema_migrations`
- `rate_limits`
- `security_logs`

It also adds security permissions:

- `admin.health.view`
- `security.logs.view`
- `security.rate_limits.manage`
- `security.sessions.manage`

### Security helper layer

Added:

- `api/security.php`

This helper provides:

- API configuration access helpers
- request ID generation
- proxy-aware client IP helper
- expanded API security headers
- structured security logging to `security_logs` with server `error_log` fallback
- database-backed rate-limit helper
- database-backed session record/revoke helpers

### Public/private health split

Updated:

- `api/health.php`

Public health now returns a shallow status only and no longer exposes PHP version or detailed database internals.

Added:

- `api/admin/health.php`

The admin health endpoint is permission protected with `admin.health.view` and can expose deeper install/runtime checks for authorized users.

### Config hardening

Updated:

- `api/config.php`
- `.env.example`

The config now safely avoids duplicate helper declarations and includes tunable rate-limit values.

## Important connector limitation during this pass

The GitHub connector blocked updates to several existing auth endpoint files. Because of that, the helper layer and migration are present, but not every existing endpoint is fully wired to the new helper layer yet.

Blocked active-file patches included:

- `api/bootstrap.php`
- `api/auth/login.php`
- `api/auth/logout.php`

The most important unresolved issue is that `api/auth/logout.php` still needs to be patched because it calls `mg_load_user_auth()` incorrectly without a user ID in the current active file.

## Required follow-up before Stage 2

Create a focused repair pass:

`03N-2_microgifter_wire_security_helpers_into_auth_endpoints`

Scope:

1. Wire `api/security.php` into `api/bootstrap.php` or each protected endpoint.
2. Add rate limiting to login, register, password reset, email verification, and profile update.
3. Fix `api/auth/logout.php` active endpoint.
4. Record DB-backed sessions on login/register.
5. Revoke all user sessions after password reset.
6. Use `mg_security_log()` in exception handlers where appropriate.
7. Verify the full auth flow on the real database after running the 03N migration.

## Install order

Run SQL in this order:

1. `database/stage_1_identity.sql`
2. `database/stage_1_repair_03M.sql`
3. `database/stage_1_security_hardening_03N.sql`

## Status

Stage 1 is stronger after this pass, but this is not yet the final hardening state. The 03N migration and helper layer are in place. The next pass must wire those helpers into all active auth endpoints.
