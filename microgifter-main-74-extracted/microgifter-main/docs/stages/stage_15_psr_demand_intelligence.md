# Stage 15 — PSR and Demand Intelligence

Stage 15 introduces Purchase Signal Records (PSRs) as the canonical expression of future visits, purchase intent, committed demand, gift interest, repeat visits, and reservation interest.

## Core principles

- PSRs represent intent and expected future demand, not completed sales.
- Merchant, location, and product identities resolve through existing canonical tables.
- A PSR may later bind to a completed canonical Microgift redemption.
- PSR state never mutates product, Microgift, PPPM, entitlement, payment, wallet, ledger, subscription, or social records.
- Demand snapshots are derived read models and can be rebuilt safely.

## PSR lifecycle

`outstanding → redeemed`

Alternative transitions:

- `outstanding → canceled`
- `outstanding → expired`
- `canceled|expired → outstanding`

The signal owner controls cancellation and reopening. The matching merchant controls redemption. Every transition creates an append-only purchase signal event.

## Supported signal types

- future visit
- purchase intent
- committed demand
- gift interest
- repeat visit
- reservation interest

Each record contains an expected time window, quantity, estimated value, currency, confidence score, source, and optional merchant/location/product/asset scope.

## Demand snapshots

The snapshot processor aggregates PSRs into merchant, location, and product scopes for configurable horizons. Snapshots contain:

- outstanding signal count and value
- committed demand count and value
- future visit count
- redeemed signal count and value
- unique users
- confidence-weighted demand score
- 7-day and 30-day velocity
- conversion rate
- versioned feature JSON

Stage 15B defines snapshot windows as UTC half-open intervals: `[snapshot_date 00:00:00, snapshot_date + horizon_days)`. Point signals with no `expected_to` must fall inside that interval. Ranged signals qualify only when their expected interval actually overlaps it. Signals ending exactly at the window start and signals beginning exactly at the window end are excluded.

Only `outstanding` and `redeemed` PSRs participate in snapshots, scope discovery, unique-user totals, and velocity. Canceled and expired records remain available for lifecycle history but cannot contaminate active demand metrics.

The snapshot date is normalized to UTC midnight before aggregation. Velocity uses completed trailing UTC windows ending at that same midnight. Rebuilding the same snapshot date and horizon therefore produces the same query boundaries regardless of the job's execution time.

The Stage 4F forecasting infrastructure remains authoritative for forecast models and forecast runs. Stage 15 supplies explicit demand features and agent-ready signals without creating a competing forecasting engine.

## Agent-ready signals

Deterministic rules emit deduplicated operational signals for:

- accelerating demand velocity
- high committed demand
- clustered future visits

Each signal includes confidence, observed and baseline values, a human-readable summary, and structured recommendation JSON. Merchants can acknowledge, resolve, and reopen signals.

## APIs and jobs

- `GET|POST /api/demand/psr.php`
- `POST /api/demand/psr-transition.php`
- `GET /api/merchant/demand-dashboard.php`
- `GET|POST /api/merchant/demand-signals.php`
- `php scripts/build_demand_snapshots.php [horizon_days] [as_of]`
- `php scripts/stage15.php`
- `php scripts/stage15_smoke.php`

The optional `as_of` value supports deterministic historical rebuilds. It is parsed as a timestamp and normalized to its UTC calendar date.

## Integrity controls

- user-scoped exact-request idempotency
- canonical merchant/location/product resolution
- cross-merchant scope rejection
- expected-window and confidence validation
- owner and merchant lifecycle authorization
- row locking for lifecycle transitions
- UTC half-open horizon boundaries
- overlap-aware point and ranged signal filtering
- active-status filtering for totals, users, velocity, scopes, and dashboard summaries
- snapshot upserts keyed by date, horizon, and scope
- deterministic signal deduplication
- transaction boundaries around each snapshot scope

## Deferred to Stage 16

Agent execution workflows, automated campaign or inventory actions, approval queues, strategy orchestration, and autonomous demand-response operations remain outside Stage 15.
