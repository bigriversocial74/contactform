# 03U PHP-Only Runtime Cleanup

## Decision

Microgifter public runtime pages are PHP-only. Static prototype `.html` routes are no longer active pages and should be treated as non-existent.

## Active public pages

```text
/index.php
/build.php
/agent.php
/signin.php
/signup.php
/account.php
```

## Removed prototype pages

```text
/index.html
/build.html
/builder.html
/agent.html
/signin.html
/signup.html
```

## Apache/cPanel behavior

The root `.htaccess` now returns `410 Gone` for old prototype HTML URLs instead of redirecting them.

This is intentional because the project has no users yet and we do not want stale static URLs, duplicate source-of-truth pages, or bypassable prototype routes.

## HostGator cleanup instruction

After uploading the latest `.htaccess`, delete any old `.html` prototype files still present in `public_html`.

## Verification

Expected behavior:

```text
/                 -> loads /index.php when logged out
/index.php        -> landing page when logged out; redirects to /agent.php when logged in
/build.php        -> builder
/agent.php        -> agent workspace
/index.html       -> 410 Gone
/build.html       -> 410 Gone
/builder.html     -> 410 Gone
/agent.html       -> 410 Gone
/signin.html      -> 410 Gone
/signup.html      -> 410 Gone
```
