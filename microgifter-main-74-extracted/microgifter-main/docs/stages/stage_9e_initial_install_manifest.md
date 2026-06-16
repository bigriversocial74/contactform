# Stage 9E Initial Install Manifest

The project has not been installed in production yet. Treat the current repository as a clean initial install baseline before Stage 10.

## Clean install order

1. Upload the full repository code.
2. Configure environment variables and database credentials.
3. Run the base schema migration.
4. Run stage schema runners in the same order as consolidated CI.
5. Run smoke checks.
6. Run security and PHPUnit tests.
7. Only then begin Stage 10 development.

## Current CI-backed sequence

- `composer migrate`
- Stage 3 delivery runner
- Stage 4 asset, builder, feed, engagement, distribution, and demand runners
- Stage 5 commerce and reconciliation runners
- Stage 7 money engine runner
- Stage 8 entitlement runner
- Stage 9 Microgift engine, lifecycle, operations, reconciliation, and aggregation runners

## Migration policy before first install

Because no production data exists yet, we should favor a clean, correct initial install over complex legacy migrations. Legacy compatibility tables and review queues remain useful for future imports, but destructive migrations are not needed now.

## Stage 10 gate

Stage 10 begins only after the Stage 9E reconciliation PR and its main regression are green.
