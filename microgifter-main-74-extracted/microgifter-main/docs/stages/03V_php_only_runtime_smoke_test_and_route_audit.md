# 03V PHP-Only Runtime Smoke Test and Route Audit

## Purpose

This pass locks the Stage 1 HostGator runtime around PHP-only public pages after removing the old prototype HTML runtime files.

## Decisions

- Public runtime pages are PHP-only.
- Old `.html` prototype routes are not redirected to active pages.
- Old `.html` routes should return `410 Gone` so they are treated as non-existent.
- No future public runtime page should be added as a root `.html` page.
- Route behavior is now documented before moving into auth UI polish.

## Active route set

```text
/
/index.php
/build.php
/agent.php
/signin.php
/signup.php
/account.php
/api/health.php
```

## Dead route set

```text
/index.html
/build.html
/builder.html
/agent.html
/signin.html
/signup.html
```

## Files verified or added

Verified:

```text
.htaccess
index.php
build.php
agent.php
```

Added:

```text
docs/architecture/php_only_public_route_map.md
docs/testing/php_only_runtime_smoke_test.md
docs/stages/03V_php_only_runtime_smoke_test_and_route_audit.md
```

## Repository search result

A repository search for `.html` returned no active file/code references at the time of this pass.

## HostGator upload requirement

Upload the latest `.htaccess`, `index.php`, `build.php`, and `agent.php` if not already uploaded.

Delete any stale `.html` files from `public_html`.

## Acceptance criteria

- Active PHP routes load.
- Dead `.html` routes return `410 Gone`.
- Logged-in users visiting `/index.php` redirect to `/agent.php`.
- Logout redirects to `/index.php`.
- Signup, signin, account, build, agent, and health all work on HostGator.
- `/api/health.php` returns database connected.

## Next recommended pass

```text
03W_microgifter_auth_pages_sticky_scroll_ui
```

That pass should redesign signin/signup/password pages using the sticky-scroll visual system while preserving the working CSRF/API auth flow.
