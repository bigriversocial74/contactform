# Hosted Games Release and QA v1

## Installation

Import `database/hosted_games_release_qa_foundation_v1.sql` after the Hosted Games management and analytics migrations.

## Release lifecycle

Releases use `draft`, `testing`, `active`, `failed`, and `archived` states. Uploading a ZIP creates a validated draft and leaves the current live release unchanged.

Each release records notes, uploader, activator, original filename, preserved ZIP size, extracted size, file count, checksum, entry file, manifest schema and version, SDK version, validation result, health result, and timestamps.

Activation and rollback require acceptable validation and health results. The operation archives the previous active release and updates the game release pointer in one transaction. Active releases cannot be archived.

## Release workspaces

Merchant:

`/merchant-game-releases.php?game={game-public-id}`

Admin:

`/admin/hosted-game-releases.php?game={game-public-id}`

These pages provide history, notes, health checks, activation, rollback, archive, original ZIP download, and normalized manifest comparison.

## Preview

Protected preview URL:

`/hosted-game-preview.php?game={game-public-id}&release={release-public-id}`

Preview requires an authenticated Merchant owner or authorized Admin. It serves the selected private release without changing the live game.

The signed-in operator is the test player. Test runs use dedicated QA tables and simulated rewards. Preview completion never calls the live Distribution reward API and reports `inventory_consumed: false`.

QA data is stored only in:

- `hosted_game_test_sessions`
- `hosted_game_test_runs`
- `hosted_game_test_events`
- `hosted_game_test_state`

The preview console includes lifecycle events, SDK requests, timing, runtime errors, asset failures, desktop/tablet/mobile viewports, reload, and test-data reset.

Reset affects only the selected preview session. It does not alter the release, live runs, live analytics, game database, player account, Distribution Program, or reward inventory.

## Health checks

Health checks verify the private release directory, entry file, normalized manifest, preserved ZIP checksum, and open release diagnostics when available. Blocking failures prevent activation.

## Permissions

Merchant permissions:

- `merchant.hosted_games.releases.manage`
- `merchant.hosted_games.preview`

Admin permissions:

- `admin.hosted_games.releases.manage`
- `admin.hosted_games.preview`

## Recommended workflow

1. Upload a draft with notes.
2. Review validation.
3. Preview the unpublished release.
4. Test multiple viewports and inspect console events.
5. Reset test data and repeat.
6. Run the health check.
7. Compare manifests.
8. Activate or roll back.
