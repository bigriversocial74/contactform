# Microgifter Stage 1 Installation and Local Test Guide

Build package: `03J_microgifter_stage1_installation_and_local_test_guide`

This guide installs the Stage 1 identity foundation for local development or a basic PHP/MySQL server.

## 1. Requirements

- PHP 8.1 or newer
- MySQL 8 or MariaDB 10.5+
- Apache or Nginx with PHP-FPM
- HTTPS in production
- A database user with access to the Microgifter database

## 2. Repository root

The active app now uses PHP entry files at the repository root:

```text
index.php
build.php
agent.php
signin.php
signup.php
forgot-password.php
reset-password.php
verify-email.php
account.php
```

The legacy root HTML files remain as reference/prototype files until intentionally removed.

## 3. Database setup

Create a database in your hosting control panel or MySQL shell, then import:

```bash
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < database/stage_1_identity.sql
```

Expected Stage 1 tables include:

```text
users
user_profiles
roles
permissions
role_permissions
user_roles
password_reset_tokens
email_verification_tokens
audit_logs
events
```

## 4. Configure database credentials

Open:

```text
api/config.php
```

Set the local/server values:

```php
'db_host' => 'localhost',
'db_name' => 'YOUR_DB_NAME',
'db_user' => 'YOUR_DB_USER',
'db_pass' => 'YOUR_DB_PASSWORD',
```

Production rule: do not commit production database passwords to GitHub. Move secrets to environment variables before public launch.

## 5. Web server document root

For a simple shared-hosting deployment, point the web root to the repository root so files such as `index.php` and `signin.php` resolve directly.

For a stronger production deployment, the future target should be a `/public` document root with private includes and API bootstrap outside the public path. That is a later hardening pass.

## 6. First browser check

Open these pages:

```text
/index.php
/signup.php
/signin.php
/forgot-password.php
/reset-password.php
/verify-email.php
/build.php
/agent.php
/account.php
```

Expected behavior:

- Public pages load with the shared header/footer.
- Guests see Sign in and Create account in the header.
- `account.php` redirects or blocks when no user is authenticated.
- `build.php` allows guest test/draft behavior.
- `agent.php` shows guest-safe agent behavior and locks account-only features.

## 7. Register first normal user

Go to:

```text
/signup.php
```

Create an account using a real email format and a strong password. Registration should:

- create a `users` row
- create a `user_profiles` row
- assign the default `customer` role
- write audit/event records
- return a login/session state

## 8. First-run admin strategy

Do not expose public admin creation in the browser. Use a direct database promotion for the first trusted admin account. See:

```text
docs/installation/first_run_admin_setup.md
```

## 9. Manual API smoke tests

Use the cURL examples in:

```text
tests/stage_1_api_curl_smoke_examples.md
```

## 10. Security checklist before production

Before a public launch:

- Move DB secrets out of committed config files.
- Enforce HTTPS-only cookies.
- Add rate limiting for login/register/password reset.
- Add mail delivery for reset and verification tokens.
- Review all admin endpoints for `mg_require_permission()`.
- Add server-level security headers.
- Confirm no `.docx`, build notes, SQL files, or private docs are publicly served in production.
