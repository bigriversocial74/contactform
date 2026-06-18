# Recovery Baseline

## Purpose

This baseline restores a repeatable development and validation environment after the completed Stage 1–18 codebase was recovered from an archive. It does not change feature behavior.

The baseline provides:

- one canonical ordered migration manifest;
- compatibility with the existing consolidated production migration history;
- a clean PHP 8.3 and MySQL 8 development environment;
- one command for the complete validation suite;
- a GitHub Actions gate for clean-install and runtime validation;
- readiness checks derived from the canonical migration state.

## Current database snapshot

A June 17, 2026 MySQL 8.0 schema export was reviewed during recovery. It contains 235 tables and records completed migrations through Stage 18E. The export also contains production-like records and must not be committed to this repository.

The existing database uses these historical coverage markers:

- `stage_9e4_consolidated_stage1_to_stage9_upgrade`
- `stage_11h_backend_hardening`

The canonical runner recognizes those markers and will not replay the earlier covered DDL. A clean database, which has no coverage markers, applies every migration in `config/migrations.php` in order.

## Start the development environment

```bash
docker compose up --build
```

Services:

- Application: `http://localhost:8000`
- MySQL from the host: `127.0.0.1:3307`
- Database: `microgifter`
- Development database user/password: `microgifter` / `microgifter`

The application container installs Composer dependencies, validates the migration manifest, applies pending migrations, and starts PHP's development server.

## Run the complete baseline

With the containers running:

```bash
docker compose exec app composer recovery-baseline
```

The command performs:

1. Composer validation.
2. Migration-manifest validation.
3. Full-upgrade SQL generation.
4. Stage 10F upgrade compatibility validation.
5. PHP syntax validation.
6. Canonical migration application and verification.
7. Security, frontend, money, Microgift, tip, subscription, profile, moderation, storefront, social, demand, agent, orchestration, checkout, fulfillment, and PHPUnit validation.

## Reset the local database

```bash
docker compose down -v
docker compose up --build
```

Deleting the volume is destructive only to the local Docker database.

## Migration rules

- Add every new migration to `config/migrations.php` in dependency order.
- Do not add a second migration list to another script or workflow.
- `scripts/run_migrations.php`, `scripts/build_full_upgrade_sql.php`, readiness checks, preflight, and CI all consume the same manifest.
- Never modify an already-applied migration. Add a new additive migration instead.
- Manual operator migrations remain in the `manual_only` section and are never applied automatically.
- Never commit database exports containing customer, authentication, payment, security-log, or other production data.

## Recovery workflow

1. Create a focused branch.
2. Run `composer recovery-baseline` before changing feature behavior.
3. Make the smallest bounded change.
4. Run targeted tests and the complete baseline.
5. Open a pull request only after the clean-install workflow passes.
