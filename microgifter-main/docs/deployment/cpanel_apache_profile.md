# Microgifter Deployment Profile: cPanel / Apache

## Purpose

This profile documents the recommended baseline for deploying Microgifter on a cPanel-style Apache host.

## Required runtime

- PHP 8.1 or newer, PHP 8.2+ preferred.
- MySQL 8 / MariaDB 10.6+ preferred.
- PDO MySQL extension enabled.
- HTTPS enabled before real users.
- `display_errors=Off` in production.
- Error logging enabled outside the public web root.

## Document root

Point the domain or subdomain to the repo root only if `.htaccess` is active and tested. The current Stage 1 `.htaccess` blocks direct browser access to sensitive paths:

- `.env` files
- `.sql` files
- `docs/`
- `database/`
- `tests/`
- `includes/`
- `scripts/` should also be treated as non-public operational code.

## Environment configuration

Set the `MG_` environment variables in the hosting panel, Apache include, or a server-local config layer. Do not commit real secrets.

Minimum required values:

```text
MG_APP_ENV=production
MG_DEBUG=false
MG_BASE_URL=https://example.com
MG_DB_HOST=localhost
MG_DB_NAME=...
MG_DB_USER=...
MG_DB_PASS=...
MG_TRUST_PROXY=false
```

## Migration workflow

Preferred Stage 1 flow:

```bash
php scripts/run_migrations.php
```

If CLI is not available, manually import the SQL files in the exact order documented in the install guide.

## Apache security checks

After upload, verify these return `403` or `404` from the browser:

```text
/database/stage_1_identity.sql
/docs/stages/stage_1_build_manifest.md
/tests/stage_1_auth_smoke_checklist.md
/includes/app.php
/scripts/run_migrations.php
/.env
```

## Production hardening checklist

- Enforce HTTPS.
- Confirm HSTS is active only after HTTPS is stable.
- Disable directory listing.
- Confirm `.htaccess` is honored by the host.
- Run the Stage 1 smoke checklist.
- Promote the first admin manually using the first-run admin guide.
- Confirm public `/api/health.php` is shallow.
- Confirm `/api/admin/health.php` requires permission.
