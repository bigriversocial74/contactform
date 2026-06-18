# Microgifter PHP-Only Public Route Map

## Status

Microgifter public runtime is PHP-only. The old prototype `.html` pages are not active runtime files and should not exist in the HostGator `public_html` folder.

## Active public browser routes

| Route | Purpose | Auth behavior |
|---|---|---|
| `/` | Public landing entry | Uses `index.php`; logged-in users are sent to `/agent.php` |
| `/index.php` | Public landing page | Logged-in users are sent to `/agent.php` |
| `/build.php` | Product/gift builder | Works logged out for draft/demo flow; enhanced when logged in |
| `/agent.php` | Agent/workspace page | Shows permission-aware account/workspace state |
| `/signin.php` | Sign in page | Posts to `/api/auth/login.php` |
| `/signup.php` | Sign up page | Posts to `/api/auth/register.php` |
| `/account.php` | Account/security/session page | Requires account context for full function |
| `/api/health.php` | Host health check | Returns JSON and database connectivity status |

## Dead prototype routes

These routes are intentionally non-existent and should return `410 Gone` from `.htaccess`:

```text
/index.html
/build.html
/builder.html
/agent.html
/signin.html
/signup.html
```

## Source-of-truth files

```text
index.php
build.php
agent.php
signin.php
signup.php
account.php
.htaccess
assets/js/auth-state.js
assets/js/auth.js
```

## Server file cleanup rule

Delete these from HostGator if they exist:

```text
public_html/index.html
public_html/build.html
public_html/builder.html
public_html/agent.html
public_html/signin.html
public_html/signup.html
```

## Carry-forward rule

Do not add new public `.html` runtime pages. Future browser pages should be `.php` templates or routed through a proper controller when the app is refactored into a `/public` web root.
