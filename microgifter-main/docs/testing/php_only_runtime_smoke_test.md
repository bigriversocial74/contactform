# PHP-Only Runtime Smoke Test

Use this checklist after uploading the PHP-only cleanup files to HostGator.

## 1. Active route checks

Open each route directly in a browser:

```text
https://microgifter.com/
https://microgifter.com/index.php
https://microgifter.com/build.php
https://microgifter.com/agent.php
https://microgifter.com/signup.php
https://microgifter.com/signin.php
https://microgifter.com/account.php
https://microgifter.com/api/health.php
```

Expected:

- `/` loads `index.php` when logged out.
- `/index.php` loads the public landing page when logged out.
- `/build.php` loads the builder.
- `/agent.php` loads the agent/workspace page.
- `/signup.php` loads the signup form.
- `/signin.php` loads the signin form.
- `/account.php` loads the account shell or account state.
- `/api/health.php` returns JSON with `database: connected`.

## 2. Dead `.html` route checks

Open each route directly:

```text
https://microgifter.com/index.html
https://microgifter.com/build.html
https://microgifter.com/builder.html
https://microgifter.com/agent.html
https://microgifter.com/signin.html
https://microgifter.com/signup.html
```

Expected:

- Each old `.html` URL returns `410 Gone` or a browser/server Gone page.
- None of these routes should render an old page.

## 3. Auth flow checks

1. Sign up with a throwaway test email.
2. Confirm account creation succeeds.
3. Confirm the header/account menu shows logged-in options.
4. Visit `/index.php` while logged in.
5. Confirm it redirects to `/agent.php`.
6. Sign out.
7. Confirm signout redirects to `/index.php`.
8. Confirm the header/account menu shows logged-out options.
9. Sign back in from `/signin.php`.
10. Confirm `/agent.php`, `/build.php`, and `/account.php` still load.

## 4. API checks

Open:

```text
https://microgifter.com/api/health.php
```

Expected JSON shape:

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

## 5. Failure notes

If active routes work but `.html` routes still load old pages, delete the `.html` files from `public_html` and re-upload `.htaccess`.

If `/api/health.php` fails, check `api/config.local.php`, database credentials, and HostGator PHP extensions.

If signup/login fail while health passes, inspect browser DevTools Network responses for `/api/auth/register.php` or `/api/auth/login.php`.
