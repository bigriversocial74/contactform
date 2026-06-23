# Local Quest installer hardening

## Purpose

The Local Quest starter foundation should be cloneable by loading:

```text
https://example.com/install.php
```

The installer should help the operator configure database access, app settings, Microgifter API settings, security settings, and the first owner admin.

## Current installer capabilities

`examples/local-quest-rewards/install.php` can:

- show server compatibility checks
- collect database host, database name, database user, and database credential
- collect app name, app public URL, mode, and sandbox shortcut setting
- collect Microgifter base URL, Developer API key, default program ID, default template ID, and webhook signing value
- collect a local signed-code secret or generate one when omitted
- collect first owner admin username, email, and credential
- create the database when the database user has permission
- run `database/local_quest_rewards.sql`
- run `database/local_quest_admin_auth.sql`
- write `config.php`
- seed the first owner admin

## Review before install

`examples/local-quest-rewards/assets/form-review.js` provides a browser-side review step before the form submits.

It shows non-protected setup values, hides protected values, and requires a second click on **Confirm and install** before the installer request is submitted.

The intended final installer flow is:

```text
Enter setup values
System compatibility checks remain visible
Review setup summary
Confirm install
Create database/schema/config/owner admin
Lock installer
```

## Lockdown convention

`examples/local-quest-rewards/install-lock.php` defines the installer lock convention:

```text
.installed.lock
.install-unlock
```

After setup, the installer should write `.installed.lock`. If `config.php` or `.installed.lock` exists, the installer should show a locked screen unless `.install-unlock` exists.

The unlock file should only be created temporarily by an operator who intentionally wants to re-run setup.

## Runtime files ignored by Git

`examples/local-quest-rewards/.gitignore` ignores:

```text
config.php
.installed.lock
.install-unlock
webhook-events.log
```

## Remaining wiring item

The review helper and lock helper are present. The installer still needs the final direct include/wiring in `install.php` when the connector allows editing that file safely:

```html
<script src="assets/form-review.js"></script>
```

and the top of the installer should call the lock helper:

```php
require __DIR__ . '/install-lock.php';
lqi_guard_installer();
```

The helper files are committed separately so the foundation behavior is explicit and easy to wire in a local editor if connector safety checks block the server-side installer page.
