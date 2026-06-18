# Microgifter Stage 1 Server Preflight Checklist

Use this before installing or testing the Stage 1 identity foundation on a local, staging, or production server.

## Runtime

- PHP 8.1+ is available.
- MySQL or MariaDB is available.
- PHP PDO MySQL extension is enabled.
- PHP sessions are enabled.
- Apache `.htaccess` rules are honored, or equivalent Nginx rules are configured.
- HTTPS is enabled for production/staging domains.

## Database

- Database exists.
- Dedicated database user exists.
- Database user has only the privileges needed by the app.
- `database/stage_1_identity.sql` has been imported.
- Tables exist: `users`, `user_profiles`, `roles`, `permissions`, `role_permissions`, `user_roles`, `user_sessions`, `password_reset_tokens`, `email_verification_tokens`, `audit_logs`, `events`.

## Environment

- Real credentials are not committed to GitHub.
- Environment variables use the `MG_` prefix from `.env.example`.
- `MG_DEBUG=false` outside local development.
- `MG_BASE_URL` matches the real domain/root path.
- `MG_DB_PASS` is set and is not blank outside local development.

## Web access checks

These paths should not be publicly browsable on a deployed server:

- `/docs/`
- `/database/`
- `/tests/`
- `/includes/`
- `/.env`

If any of those paths render in the browser, fix web server rules before launch.

## First browser checks

- `/index.php` loads.
- `/signup.php` loads.
- `/signin.php` loads.
- `/forgot-password.php` loads.
- `/build.php` loads.
- `/agent.php` loads.

## First security checks

- Registration creates a user with the default `customer` role.
- Login returns a session without exposing passwords or token hashes.
- `/api/admin/users.php` is blocked for non-admin users.
- `/api/admin/audit-logs.php` is blocked for non-admin users.
- Logout clears the session.
