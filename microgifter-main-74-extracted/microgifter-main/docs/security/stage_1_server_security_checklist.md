# Microgifter Stage 1 Server Security Checklist

This checklist applies before any staging or public deployment.

## Secrets and configuration

- Do not commit real `.env` values.
- Use environment variables or a host-managed secret system.
- Keep `MG_DEBUG=false` in staging and production.
- Use a dedicated MySQL user for Microgifter.
- Rotate credentials if they were ever pasted into a public or shared location.

## Sessions and auth

- Serve the app over HTTPS.
- Confirm session cookies are `HttpOnly` and `SameSite` in the PHP/session configuration.
- Do not store auth tokens in `localStorage`.
- Use CSRF tokens for state-changing form/API requests.
- Keep admin creation manual for Stage 1; do not expose a public admin setup route.

## Web server protections

- Disable directory listing.
- Block direct browser access to `/docs`, `/database`, `/tests`, and `/includes`.
- Block `.env`, SQL dumps, dependency lock files, and server-local config files.
- Keep backups outside the web root.

## API protections

- Every protected endpoint must call an auth/permission helper.
- Admin endpoints must require explicit admin permissions.
- Never trust role or permission values from the browser.
- Do not return password hashes, token hashes, or internal secrets in JSON payloads.

## Logging and auditing

- Auth and admin actions should write to `audit_logs` and/or `events`.
- Avoid logging passwords, reset tokens, verification tokens, or raw session identifiers.
- Review failed-login and password-reset behavior before launch.

## Deployment review

- Run the Stage 1 smoke checklist.
- Test as guest, customer, and admin.
- Verify `/api/auth/me.php` reflects current roles/permissions.
- Verify `/api/admin/audit-logs.php` is permission protected.
