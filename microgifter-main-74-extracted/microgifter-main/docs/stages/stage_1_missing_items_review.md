# Stage 1 Missing Items Review

This review identifies what is complete enough to proceed to installation testing and what should be verified before Stage 2.

## Ready for installation testing

The repository now includes the core Stage 1 foundation:

- PHP root pages for landing, builder, agent workspace, account, and auth flows
- Shared PHP includes for layout, CSRF, auth, permissions, header, and footer
- Shared CSS design system plus section stylesheets
- Shared JavaScript modules split by responsibility
- API config/bootstrap/db/response foundation
- Registration, login, logout, current-user, password reset, and email verification endpoints
- Roles, permissions, admin users, and audit log endpoints
- Stage 1 identity SQL schema
- Server install, first-run admin, preflight, and security docs
- Smoke checklist and cURL smoke examples

## Must verify on the target server

These items depend on the real hosting environment and database credentials:

1. `api/config.php` receives correct `MG_` environment values.
2. `database/stage_1_identity.sql` imports without error.
3. PHP sessions work with secure cookie settings on HTTPS.
4. `signup.php` can create a user.
5. `signin.php` can authenticate that user.
6. `account.php` displays the signed-in account.
7. `api/auth/me.php` returns the refreshed user payload with roles and permissions.
8. `api/auth/logout.php` clears the session.
9. Admin promotion SQL works for the trusted owner account.
10. Admin-only endpoints return 403 for normal users and 200 for authorized admins.
11. `.htaccess` blocks direct web access to `docs/`, `database/`, `tests/`, `includes/`, `.env`, and `.sql` paths on Apache/cPanel hosting.

## Known follow-up items before Stage 2

These are not blockers for Stage 1 installation testing, but they should be addressed before or during early Stage 2 work:

1. Add automated tests once the target runtime is confirmed.
2. Add a safer migration strategy before schema changes become frequent.
3. Add rate limiting for login, register, password reset, and verification endpoints.
4. Add email delivery integration for password reset and email verification tokens.
5. Add production logging/error reporting strategy that does not expose sensitive details.
6. Decide whether the old `index.html`, `build.html`, and `agent.html` should remain as references or move fully under `docs/reference/` once PHP pages are accepted.
7. Confirm whether `commerce.js` / `programs.js` naming should remain or be renamed later to `ecommerce.js` / `pppm.js` if connector restrictions are no longer an issue.
8. Review Content Security Policy once external scripts, fonts, and payment integrations are known.
9. Confirm production web server routing rules for `.php` canonical pages.

## Stage 2 readiness recommendation

Do not start Stage 2 feature implementation until the Stage 1 smoke checklist passes on the actual target server/database.

If the smoke checklist passes, Stage 2 can begin from a clean identity and onboarding foundation.
