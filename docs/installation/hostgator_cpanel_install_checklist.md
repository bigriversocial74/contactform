# HostGator / cPanel Install Checklist

## 1. Prepare hosting

- Confirm PHP 8.1+ is selected in cPanel if available.
- Enable HTTPS for the domain.
- Create a MySQL database.
- Create a MySQL user with a strong password.
- Assign the user to the database with required privileges.
- Record DB name, DB user, DB password, host, and port.

## 2. Upload files

Upload the repository contents to the hosting account.

Early-stage option:

```text
public_html/
  index.php
  build.php
  agent.php
  api/
  assets/
  includes/
  database/
  docs/
  scripts/
  tests/
  .htaccess
```

Preferred future option:

```text
repo root outside browser root
public_html/ points to repo/public
```

If the preferred future option is not possible on basic hosting, the root `.htaccess` guard must remain active.

## 3. Configure environment

Use HostGator/cPanel environment support if available. If not available, configure `api/config.php` carefully from secure account-level values.

Required values:

```text
MG_DB_HOST
MG_DB_PORT
MG_DB_NAME
MG_DB_USER
MG_DB_PASS
MG_APP_ENV
MG_APP_URL
```

Recommended private-beta values:

```text
MG_APP_ENV=staging
MG_APP_DEBUG=false
MG_FORCE_HTTPS=true
```

## 4. Import SQL migrations

Use phpMyAdmin or CLI if SSH is available.

Import order:

```text
1. database/stage_1_identity.sql
2. database/stage_1_repair_03M.sql
3. database/stage_1_security_hardening_03N.sql
4. database/stage_1_security_hardening_03N_3.sql
5. database/stage_1_high_volume_foundation_03O.sql
```

## 5. First browser smoke test

Open:

```text
/api/health.php
/signin.php
/signup.php
/account.php
/build.php
/agent.php
```

Expected:

- health returns shallow OK JSON
- sign in page loads
- sign up page loads
- account page loads in guest mode or redirects safely
- build page loads
- agent page loads with guest-safe permissions

## 6. Register owner account

- Register a normal account through `signup.php`.
- Confirm sign in works.
- Promote the trusted owner account manually using the first-run admin setup guide.
- Do not create a public admin registration endpoint.

## 7. Security verification

Confirm these browser paths are blocked:

```text
/database/stage_1_identity.sql
/includes/app.php
/scripts/run_migrations.php
/docs/stages/stage_1_build_manifest.md
/tests/stage_1_auth_smoke_checklist.md
.env
```

## 8. Optional Composer/testing

If SSH and Composer are available:

```bash
composer install
composer test
```

If not available, run tests locally or through GitHub Actions.

## 9. Private-beta operating rules

- Keep traffic low.
- Backup the database before every major upload.
- Import migrations manually and record dates.
- Watch `security_logs` after testing.
- Do not enable heavy worker/queue features on shared hosting.
- Move to AWS before high-volume public use.
