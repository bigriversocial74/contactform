# Hosted Games Analytics and Diagnostics v1

Hosted Games Analytics provides a dedicated performance and health workspace for every merchant-hosted game. The module uses the Hosted Game Standard v1 lifecycle, server-authoritative run records, Distribution reward records, PPPM item lifecycle, release snapshots, and privacy-limited browser telemetry.

## Workspaces

Merchant:

```text
/merchant-game-analytics.php?game={hosted-game-public-id}
```

Microgifter Admin:

```text
/admin/hosted-game-analytics.php?game={hosted-game-public-id}
```

The Hosted Games inventory automatically adds an **Analytics** action for each game.

## Performance metrics

The workspace reports:

- game loads;
- unique and connected players;
- runs started and completed;
- qualification and abandonment rates;
- average play duration;
- average, highest, and lowest scores;
- repeat-player rate;
- rewards queued, delivered, failed, claimed, and redeemed;
- reward inventory consumed;
- allocated reward value and value per qualified player;
- device, browser, viewport, event, and level breakdowns;
- release-version comparisons.

Date ranges are limited to 366 days. A release filter can isolate a specific uploaded version.

## Release authority

Every observed run receives a durable snapshot in `hosted_game_run_observability` containing:

- the hosted-game release public ID and version;
- SDK and game versions;
- privacy-limited client context;
- qualification and abandonment timestamps;
- measured play duration.

This prevents a later release activation from rewriting historical attribution.

## Client telemetry

The Standard v1 child bridge records:

- game load and startup duration;
- SDK request latency and failures;
- JavaScript errors and unhandled promise rejections;
- failed image, script, style, audio, video, font, and other asset loads;
- legacy-manifest warnings;
- run start, qualification, completion, and abandonment context.

Telemetry passes through the trusted parent shell to:

```text
/api/hosted-games/telemetry.php
```

The endpoint enforces CSRF, active-game lookup, event allowlisting, payload limits, session-based rate limits, and run-token authorization for run-linked telemetry.

Telemetry does not accept or retain API credentials, webhook secrets, database credentials, cookies, raw IP addresses, email addresses, or arbitrary browser payloads.

## Diagnostic groups

Runtime and platform failures are normalized into a SHA-256 fingerprint based on:

- game and release;
- browser family;
- diagnostic category;
- normalized title and message;
- the first stack line.

Repeated occurrences update one diagnostic group while preserving individual occurrence records. Groups support:

- `open`;
- `resolved`;
- `ignored`.

A new occurrence reopens a resolved group. Ignored groups remain ignored until manually reopened.

Supported diagnostic categories include:

- `runtime_error`;
- `sdk_request_failed`;
- `asset_load_failed`;
- `manifest_warning`;
- `game_startup` and slow SDK/API requests;
- `webhook_failed`;
- `database_failed`;
- `reward_failed`.

## Health reporting

The health section combines:

- release readiness;
- Distribution Program/API/webhook readiness;
- isolated database readiness;
- average and maximum game startup latency;
- open diagnostic counts and categories.

## Reward lifecycle

Reward metrics join `hosted_game_runs` to Distribution allocations, issuance jobs, and PPPM items. Claimed and redeemed totals therefore follow the authoritative PPPM lifecycle rather than client-reported game events.

## Diagnostics export

Authorized Merchant and Admin users can download a ZIP containing:

- `summary.json`;
- `diagnostic-groups.csv`;
- `diagnostic-occurrences.csv`;
- `README.txt`.

Exports are capped at 5,000 diagnostic groups and 10,000 occurrences. They intentionally omit secrets, credentials, cookies, CSRF tokens, email addresses, and raw IP addresses.

## Permissions

Merchant:

- `merchant.hosted_games.analytics.view`
- `merchant.hosted_games.diagnostics.manage`

Admin:

- `admin.hosted_games.analytics.view`
- `admin.hosted_games.diagnostics.manage`

Merchant APIs always verify game ownership. Admin APIs require the dedicated permission or settings-management authority.

## Installation

Import:

```text
database/hosted_games_analytics_diagnostics_v1.sql
```

The migration must run after `database/hosted_games_management_v1.sql`.
