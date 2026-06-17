# 03H Microgifter Logout Account Header Flow

## Completed

This pass connected the Stage 1 auth state to the shared account/header experience.

### Files updated

- `includes/header.php`
- `account.php`
- `assets/css/microgifter.css`
- `api/auth/logout.php`

## Header behavior

The shared header now renders two states:

### Guest

- Sign in button
- Create account button

### Authenticated user

- Account trigger with display name
- Primary role label
- Account dashboard link
- Continue building link
- Open workspace link
- Conditional admin access link
- Sign out button

## Account dashboard

`account.php` now displays the current session identity, roles, permissions, workspace links, and conditional admin API links when the user has matching permissions.

## Logout flow

The logout button uses the shared `data-auth-logout` handler in `assets/js/auth.js`, which posts to:

```text
/api/auth/logout.php
```

`api/auth/logout.php` now writes audit/event records when a logged-in user signs out, clears the session, regenerates the session ID, and returns a redirect to `/signin.php`.

## Security notes

- The header only improves user experience; it is not the permission enforcement layer.
- Admin links are only convenience links.
- Protected API endpoints must continue to call `mg_require_permission()`.
- Session identity is stored server-side and exposed to the browser only as rendered display state.

## Next recommended pass

```text
03I_microgifter_auth_page_polish_and_smoke_tests
```

Recommended focus:

1. Polish forgot/reset/verify auth pages.
2. Verify all auth pages load shared header/footer correctly.
3. Add account/logout test cases to the smoke checklist.
4. Confirm guest/authenticated nav behavior across `index.php`, `build.php`, and `agent.php`.
