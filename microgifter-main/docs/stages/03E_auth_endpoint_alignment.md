# 03E Microgifter Auth Endpoint Alignment

## Completed

This pass aligned the Stage 1 auth UI with actual PHP API endpoints in the GitHub repo.

## Added API foundation

- `api/config.php`
- `api/bootstrap.php`
- `api/db.php`
- `api/response.php`

## Added auth endpoints

- `POST /api/auth/register.php`
- `POST /api/auth/login.php`
- `POST /api/auth/logout.php`
- `GET /api/auth/me.php`
- `POST /api/auth/password/forgot.php`
- `POST /api/auth/password/reset.php`
- `POST /api/auth/email/verify-request.php`
- `POST /api/auth/email/verify.php`

## Added role/permission/admin endpoints

- `GET /api/roles/index.php`
- `GET /api/permissions/index.php`
- `GET /api/admin/users.php`
- `GET /api/admin/audit-logs.php`

## Added database baseline

- `database/stage_1_identity.sql`

## Updated UI behavior

- `signin.php` now posts to `/api/auth/login.php` and has a live status region.
- `signup.php` now posts to `/api/auth/register.php` and has a live status region.
- `assets/js/auth.js` now follows API-provided redirects and supports logout buttons.

## Security notes

- CSRF is required for all write requests.
- Sessions use HttpOnly cookies through the shared PHP bootstrap.
- Passwords are hashed using PHP `password_hash()`.
- Reset and verification tokens are stored as SHA-256 hashes.
- Admin endpoints are placeholders with authentication checks; permission-specific enforcement must be hardened once role assignment flows are finalized.

## Known follow-up

The GitHub connector blocked several smaller UI page updates for password/reset status regions. The endpoints are present and functional, but the next UI polish pass should add visible `data-auth-status` blocks to:

- `forgot-password.php`
- `reset-password.php`
- `verify-email.php`

## Next pass

Recommended next build:

`03F_microgifter_auth_permissions_hardening`

Focus:

1. Add user role assignment during registration.
2. Add permission resolver against `roles`, `user_roles`, `permissions`, and `role_permissions`.
3. Harden admin endpoints with permission checks.
4. Add audit/event tables and logging helpers.
5. Add complete auth smoke-test checklist.
