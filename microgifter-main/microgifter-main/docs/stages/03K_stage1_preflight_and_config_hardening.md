# 03K Microgifter Stage 1 Preflight and Config Hardening

## Completed

- Updated `.env.example` to use the canonical `MG_` environment variable names.
- Hardened `api/config.php` with typed environment helpers.
- Added root `.htaccess` protections for Apache/cPanel-style hosting.
- Added a Stage 1 server preflight checklist.
- Added a Stage 1 server security checklist.
- Added a sensitive file access policy with Apache and Nginx guidance.

## Files changed

- `.env.example`
- `.htaccess`
- `api/config.php`
- `docs/installation/stage_1_server_preflight_checklist.md`
- `docs/security/stage_1_server_security_checklist.md`
- `docs/security/sensitive_file_access_policy.md`

## Security notes

- Real secrets must be managed through server environment variables or host secret tooling.
- `MG_DEBUG` must be `false` outside local development.
- `docs/`, `database/`, `tests/`, and `includes/` should not be publicly browseable.
- The `.htaccess` file helps on Apache, but Nginx deployments need equivalent server config.

## Next recommended pass

`03L_microgifter_stage1_final_review_and_zip_manifest`

Focus:

1. Review Stage 1 file structure.
2. Add a build manifest.
3. Identify any missing Stage 1 files before moving toward Stage 2.
4. Prepare a clean numbered ZIP/package list if needed.
