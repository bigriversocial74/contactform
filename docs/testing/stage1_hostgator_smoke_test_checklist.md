# Stage 1 HostGator Smoke Test Checklist

Run this checklist after every upload/extract cycle on HostGator.

Use a throwaway test user until the flow is confirmed.

## 1. Basic runtime

Open:

```text
https://microgifter.com/api/health.php
```

Expected JSON:

```json
{
  "ok": true,
  "message": "OK",
  "data": {
    "service": "microgifter",
    "runtime": "hostgator-compatible",
    "database": "connected"
  }
}
```

Failure rules:

- If this returns `500`, check `api/config.local.php` and HostGator PHP error logs.
- If this returns `database` not connected, verify database name, username, password, host, and user privileges.
- Browser-facing failure output must stay generic and must not expose credentials, DSNs, filesystem paths, SQL errors, exception classes, or stack details.
- Detailed health failures should appear only in server/PHP error logs with the `[microgifter-health]` prefix.

## 2. PHP-only active routes

These must load:

```text
/
/index.php
/build.php
/agent.php
/signup.php
/signin.php
/account.php
```

These must not exist and should return `410 Gone` or equivalent:

```text
/index.html
/build.html
/builder.html
/agent.html
/signin.html
/signup.html
```

## 3. Logged-out route behavior

While logged out:

- `/` loads the public landing page.
- `/index.php` loads the public landing page.
- `/signup.php` loads signup.
- `/signin.php` loads signin.
- Header shows the universal logo/nav/account dropdown.
- Header does not show separate Create Gift/Create Account buttons.

## 4. Signup flow

Create a throwaway test account.

Expected:

- Form submits without browser console errors.
- API returns success JSON.
- User becomes authenticated.
- User can reach `/account.php`.

## 5. Signin flow

Sign out, then sign back in.

Expected:

- Signin succeeds.
- User can open `/account.php`.
- User can open `/agent.php`.
- User can open `/build.php`.

## 6. Logged-in route behavior

While logged in:

- `/index.php` redirects to `/agent.php`.
- `/agent.php` loads the workspace.
- `/build.php` loads the builder.
- Header account dropdown shows account/dashboard/signout actions.
- Footer is consistent across active pages.

## 7. Logout flow

Click Sign out from the account dropdown.

Expected:

- Active session is revoked.
- Browser lands on `/index.php`.
- Public landing page is visible.
- Reopening `/account.php` should require authentication or show logged-out behavior.

## 8. Browser checks

Open DevTools console.

Expected:

- No fatal JavaScript errors.
- No missing `auth-state.js` errors.
- No missing core CSS errors.
- No failed API calls except intentional unauthorized checks.

## 9. File cleanup checks

In `public_html`, verify these do not exist:

```text
index.html
build.html
builder.html
agent.html
signin.html
signup.html
```

Verify these do exist:

```text
index.php
build.php
agent.php
signup.php
signin.php
account.php
.htaccess
api/config.php
api/config.local.php
api/health.php
includes/header.php
includes/footer.php
assets/css/microgifter.css
assets/js/auth-state.js
```

## 10. Pass/fail rule

Stage 1 foundation is considered HostGator-stable only when all items above pass without manual workarounds.
