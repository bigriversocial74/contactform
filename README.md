# Microgifter Platform

Official from-scratch Microgifter platform build.

## Current status

All 18 planned build stages are complete. The active work is feature connection, end-to-end validation, deployment recovery, and production hardening.

The platform includes identity and security, products and storefronts, commerce and financial operations, PPPM ownership, entitlements, the Microgift Engine, merchant-location redemption, Action Center, tips, subscriptions, social publishing, demand intelligence, agent orchestration, operational controls, dashboards, outbox processing, retention, moderation, and engagement mutations.

## Canonical ownership

- Commerce owns order and payment truth.
- PPPM owns permanent issued-unit identity and ownership.
- Entitlements own protected digital access.
- Microgift templates and instances own gift lifecycle behavior.
- Merchant locations and claim codes own local redemption authority.
- The Action Center is a user-facing read model, not an ownership or financial authority.

## Development environment

Start the PHP 8.3 and MySQL 8 development stack:

```bash
docker compose up --build
```

Run the complete recovery baseline:

```bash
docker compose exec app composer recovery-baseline
```

See `docs/recovery-baseline.md` for setup, reset, migration, and validation guidance.

## Database migrations

`config/migrations.php` is the single ordered migration source of truth. These commands all consume that manifest:

- `php scripts/run_migrations.php`
- `php scripts/build_full_upgrade_sql.php`
- `php scripts/validate_migration_manifest.php`
- `composer recovery-baseline`

Historical consolidated migration markers are supported so an existing production database is not forced to replay covered DDL.

## Action Center

The customer gift workspace has three folders:

- **INBOX** — received and redeemable gifts currently owned by the user.
- **SENT** — gifts transferred by the user to another recipient.
- **CLAIMED** — gifts successfully verified and redeemed by an authorized merchant location.

See `docs/stages/stage_10f_action_center_state_model.md`.

## Pull-request validation

The Recovery Baseline workflow creates a clean MySQL database, applies the canonical migration history, starts the application, and runs syntax checks, migration validation, behavior validators, security tests, and the complete PHPUnit suite.
