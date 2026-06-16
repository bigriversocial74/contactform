# Production Public-Root Deployment Profiles

## Required security outcome

Before launch, the browser document root must expose only approved public entrypoints and public assets. Internal folders must not be directly reachable.

## cPanel / Apache profile

Preferred:

1. Point the domain document root to `public/` if hosting allows it.
2. Keep `.env`, `includes/`, `database/`, `docs/`, `tests/`, and `scripts/` one level above the document root.
3. Keep the root `.htaccess` guard during transition.
4. Confirm direct browser requests to internal folders return 403 or 404.

Fallback for hosts that cannot use `public/` as document root:

1. Keep the root `.htaccess` guard active.
2. Confirm `Options -Indexes` is active.
3. Confirm direct access to `.env`, `.sql`, `database/`, `docs/`, `tests/`, `scripts/`, and `includes/` is blocked.
4. Treat this as acceptable for private beta only, not ideal public launch architecture.

## VPS / Nginx profile

Preferred:

1. Set `root /path/to/microgifter/public;`.
2. Route PHP through PHP-FPM.
3. Deny hidden files and sensitive extensions.
4. Add explicit deny rules for internal directories if they are ever exposed by mistake.
5. Serve static assets with long cache headers only after build/versioning is in place.

## Verification checklist

- `/index.php` loads.
- `/signin.php` loads.
- `/api/health.php` returns shallow health only.
- `/database/stage_1_identity.sql` is blocked.
- `/includes/app.php` is blocked.
- `/scripts/run_migrations.php` is blocked.
- `/docs/stages/stage_1_build_manifest.md` is blocked.
- Security headers are present.
- PHP errors are not displayed to the browser.
