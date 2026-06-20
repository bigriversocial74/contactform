# Microgifter Platform

Official from-scratch Microgifter platform build.

## Current status

All 18 foundational build stages and focused V1 Stages A–F are complete. The active package is **V1 Release Hardening**: browser golden-path validation, production readiness enforcement, real Stripe test-provider evidence, deployment recovery, and staging verification.

The platform includes identity and security, products and storefronts, commerce and financial operations, PPPM ownership, entitlements, the Microgift Engine, merchant-location redemption, Action Center, Stripe Connect and hosted Checkout, tips, subscriptions, social publishing, demand intelligence, agent orchestration, operational controls, dashboards, outbox processing, retention, moderation, and engagement mutations.

## Canonical repository root

The active application is the repository root of `bigriversocial74/contactform`. The nested `microgifter-main/` directory is an archived recovery copy and is not a deployment, workflow, migration, or implementation source.

See `docs/architecture/current_active_file_map.md`.

## Canonical ownership

- Commerce owns order truth.
- Payment services and signed provider webhooks own payment confirmation.
- Stage 7 wallets and the double-entry ledger own financial truth.
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

## Focused V1 payment path

Production checkout uses Stripe Hosted Checkout with a connected-account destination and an included platform share. A signed Stripe webhook is the payment-confirmation authority. The paid-order workflow posts the ledger split, finalizes the receipt, issues PPPM and Microgift units, projects the Action Center, and creates confirmations once.

Platform configuration is managed through `/admin-payments.php`. Merchant Connect onboarding is managed through `/merchant-payments.php`.

## Action Center

The customer gift workspace has three folders:

- **INBOX** — received and redeemable gifts currently owned by the user.
- **SENT** — gifts transferred by the user to another recipient.
- **CLAIMED** — gifts successfully verified and redeemed by an authorized merchant location.

See `docs/stages/stage_10f_action_center_state_model.md`.

## Pull-request validation

The Recovery Baseline workflow creates a clean MySQL database, applies the canonical migration history, starts the application, and runs syntax checks, migration validation, behavior validators, security tests, the gated product-to-PPPM golden-path audit, and the complete PHPUnit suite.

The active-root Browser Validation workflow runs Playwright against the PHP application for pull requests and pushes to `main`. The protected Stripe Test Integration workflow validates the real Stripe test API and connected-account destination boundary when repository test credentials are configured.
