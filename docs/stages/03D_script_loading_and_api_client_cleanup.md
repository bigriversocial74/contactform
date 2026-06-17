# 03D Script Loading and API Client Cleanup

## Purpose

Normalize the JavaScript loading order and harden the shared API client before adding deeper onboarding and auth behavior.

## Completed

- Reworked `assets/js/api-client.js` into a shared fetch wrapper with:
  - same-origin credentials
  - CSRF header support
  - JSON and text response parsing
  - 401 and 403 custom events
  - reusable `Microgifter.get`, `Microgifter.post`, and `Microgifter.submitForm` helpers
- Updated `includes/footer.php` to load scripts through a central registry.
- Moved page-specific scripts into `$page_scripts`.
- Updated `build.php` to register `/assets/js/builder.js` through the footer registry.
- Updated `agent.php` to register `/assets/js/agent.js` through the footer registry.

## Current Script Order

The footer now loads:

1. `/assets/js/microgifter.js`
2. `/assets/js/api-client.js`
3. `/assets/js/auth.js`
4. `/assets/js/onboarding.js`
5. Page-specific scripts from `$page_scripts`

This ensures page-specific scripts execute after shared helpers are available.

## Security Notes

- Session/auth security remains server-side.
- JavaScript should never store session tokens, passwords, role grants, or privileged data.
- Client-side UI gating is for user experience only. Protected API endpoints must still enforce permissions.

## Next Recommended Pass

`03E_microgifter_auth_endpoint_alignment`

Recommended focus:

1. Verify Stage 1 auth endpoints are committed and aligned with PHP pages.
2. Confirm sign in, sign up, forgot/reset password, and email verification forms post to real endpoints.
3. Add consistent redirect handling after login/signup.
4. Add smoke-test checklist updates.
