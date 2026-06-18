# Stage 4F Future Demand Intelligence, Forecasting, and Merchant Analytics

## Objective

Stage 4F converts operational facts from catalog, PPPM, distribution, fulfillment, engagement, claims, and redemption into privacy-safe merchant intelligence. It is descriptive and predictive, not an autonomous decision engine. Forecasts include model identity, input checksums, training windows, uncertainty intervals, and basic backtest metrics.

## Data layers

1. Operational systems remain authoritative.
2. `demand_fact_daily` stores merchant/product/program/source aggregates.
3. Feature snapshots record versioned model inputs.
4. Forecast runs and points store reproducible projections.
5. Merchant intelligence snapshots calculate demand, engagement, fulfillment, and redemption scores.
6. Alert rules identify material changes without modifying operational records.
7. Exports contain aggregate or k-anonymous rows only.

## Initial models

- Seven-day seasonal naive forecast for item volume.
- Moving-average forecast for issued value.
- Optional exponential smoothing for merchant-specific models.

The model layer is intentionally provider-neutral. A later external forecasting service can write runs and points through the same contracts.

## Privacy and governance

- Dashboard queries are merchant-scoped.
- Export jobs never contain recipient identifiers.
- K-anonymous exports suppress cohorts below the configured threshold.
- Export files remain in private storage and expire after seven days.
- Forecast inputs are checksummed for reproducibility.
- Forecasts are estimates and must not silently change budgets, offers, eligibility, inventory, or PPPM lifecycle state.

## Operations

Recommended daily order:

1. Run Stage 4D engagement aggregation.
2. Run Stage 4E distribution aggregation.
3. Run `aggregate_stage4f_demand.php`.
4. Run active merchant forecasts.
5. Run `build_stage4f_snapshots.php`.
6. Evaluate alert rules.
7. Process privacy-safe export jobs.

## Stage 5 carry-forward

Stage 5 should move from foundational architecture into merchant operating workflows: administration UI, onboarding, product/store management, distribution controls, analytics configuration, scheduled workers, deployment observability, and controlled beta operations.
