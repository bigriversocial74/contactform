# GitHub Actions CI Optimization

## Goal

Preserve security, migration, smoke, and regression coverage while reducing duplicated GitHub Actions usage.

## Previous model

Every pull request triggered 17 separate historical stage workflows. Each workflow repeated repository checkout, PHP setup, Composer installation, database startup, migrations, linting, and overlapping PHPUnit tests.

## Consolidated model

### PR Validation

Runs once per non-documentation pull request.

- Cancels obsolete runs when a newer commit is pushed to the same PR.
- Restores the Composer cache.
- Validates Composer configuration.
- Lints all PHP files.
- Performs a clean database install and all stage smoke checks.
- Starts the PHP test server.
- Runs the security foundation tests.
- Runs the full PHPUnit suite.

### Main Regression

Runs once after a non-documentation change reaches `main`.

- Repeats the complete clean-install and smoke validation.
- Runs security tests and the full regression suite.
- Cancels an older main regression if a newer merge arrives first.

### Deep Validation

Runs weekly and can be started manually.

- Performs Composer dependency auditing.
- Performs a full clean install.
- Repeats the Stage 5J schema and smoke checks to verify idempotency.
- Runs the complete PHPUnit suite.

## Historical workflow policy

The Stage 4B through Stage 6A workflow wrappers and the standalone Security Foundation wrapper are removed. Their migrations, smoke scripts, security checks, and PHPUnit tests remain in the repository and are executed through the three consolidated workflows.

## Expected usage

Typical pull request:

- Before: approximately 17 workflow runs per commit, plus repeated runs after merge.
- After: one PR Validation run per active PR commit and one Main Regression run after merge.

Documentation-only changes do not trigger PR Validation or Main Regression.

## Required check

Repository branch protection should use `PR Validation / validate` as the required pull-request check. Historical stage workflow check names should be removed from branch protection after this change is merged.
