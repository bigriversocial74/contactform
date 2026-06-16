# Stage 9E-3 Zip Upload / Extract Checklist

Use this checklist when uploading the latest code over the early Stage 1 install.

## Before upload

- [ ] Export a full database backup.
- [ ] Download a copy of the current server files.
- [ ] Confirm the two existing accounts can log in before changes.
- [ ] Confirm you know the active database name, username, and password.
- [ ] Confirm environment/config secrets are available.

## Upload/extract

- [ ] Download the latest merged repo zip from GitHub.
- [ ] Upload the zip to the server.
- [ ] Extract over the existing application files.
- [ ] Preserve server-only config/secrets.
- [ ] Preserve uploaded media/storage directories.
- [ ] Reset file permissions if your host requires it.

## After upload

- [ ] Run `php scripts/stage9e3_preflight.php`.
- [ ] Run the additive stage script sequence from `stage_9e3_early_install_upgrade.md`.
- [ ] Run `php scripts/stage9e3_smoke.php`.
- [ ] Log in with an existing account.
- [ ] Create a new test account.
- [ ] Confirm health/API endpoints respond.
- [ ] Only then continue to Stage 10 planning.

## Stop conditions

Stop and inspect before continuing if:

- a migration command fails,
- a smoke check fails,
- login stops working,
- permissions are missing,
- the app cannot connect to the database,
- secrets/config are overwritten.
