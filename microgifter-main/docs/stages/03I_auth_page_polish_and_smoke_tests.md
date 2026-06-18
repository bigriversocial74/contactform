# 03I Microgifter Auth Page Polish and Smoke Tests

## Purpose

Polish the remaining Stage 1 auth utility pages and expand the smoke-test checklist so the current identity foundation can be tested consistently before moving into the next stage.

## Pages polished

- `forgot-password.php`
- `reset-password.php`
- `verify-email.php`

## Improvements

- Added clearer security copy for account recovery and verification flows.
- Added live status regions using `data-auth-status` for shared `auth.js` handling.
- Added missing-token warnings for reset and verification pages.
- Disabled reset/verify submit buttons when a required token is missing.
- Kept all write operations routed through the existing API endpoints.
- Preserved CSRF fields on all forms.

## Smoke checklist updates

Expanded `tests/stage_1_auth_smoke_checklist.md` to cover:

- Guest page loading.
- Signup/signin/account redirect behavior.
- Current-user roles and permissions.
- Logout flow.
- Password recovery edge cases.
- Email verification edge cases.
- Admin/audit endpoint permission checks.
- Server-side security expectations.

## Security notes

The recovery page continues to use generic success messages so the UI does not reveal whether an email address is registered. Reset and verification token checks remain server-side in the API.

Client-side disabled buttons are convenience only. The API remains the real enforcement layer for token, CSRF, auth, and permission checks.

## Next recommended pass

`03J_microgifter_stage1_installation_and_local_test_guide`

Focus:

1. Add a clear local/server install guide.
2. Add exact SQL import and environment configuration steps.
3. Add a first-run admin creation strategy.
4. Add manual cURL/API smoke examples.
