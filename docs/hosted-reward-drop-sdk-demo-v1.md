# Hosted Reward Drop SDK Demo v1

Reward Drop SDK Demo is the reference uploadable game for the Microgifter Hosted Games foundation.

It is intentionally separate from the physical PHP application at `/games/reward-drop/`.

## Two Reward Drop implementations

### Standalone first-party application

Path:

`/games/reward-drop/`

Uses:

- custom PHP endpoints under `/games/reward-drop/api/`
- `database/reward_drop_game_v1.sql`
- Public Distribution API credentials and signed lifecycle webhooks
- dedicated cooldown and idempotency records

This remains useful as an example of a direct first-party Public Distribution API integration.

### Uploadable Hosted Game SDK demo

Source:

`examples/hosted-game-reward-drop-demo/`

Download endpoint:

`/api/hosted-games/demo-package.php`

Generated filename:

`reward-drop-sdk-demo-v2.0.0.zip`

Uses:

- `microgifter.hosted-game/v1` manifest
- `window.MicrogifterGame`
- Hosted Games managed runtime and credentials
- Hosted Games release lifecycle
- protected preview and isolated QA tables
- the isolated game database assigned through Hosted Games

It does not use `database/reward_drop_game_v1.sql`.

## Current SDK coverage

The demo exercises:

- `MicrogifterGame.ready()`
- `getPlayer()`
- `getProgram()`
- `getReward()`
- `startRun()`
- `levelStarted()`
- `updateScore()`
- `levelCompleted()`
- `submitScore()`
- `qualify()`
- `complete()`
- `saveState()` and `loadState()`
- `getLeaderboard()`
- `openInbox()`
- `abandonRun()`
- `reportError()`

No custom Fetch/XHR endpoint is used by the uploaded package.

## Protected preview behavior

When launched through:

`/hosted-game-preview.php?game={game-id}&release={release-id}`

The demo receives:

- a signed-in test player
- a test Distribution Program context
- a simulated reward template
- isolated test runs, events, scores, leaderboard, and saved state
- simulated reward delivery
- `inventory_consumed: false`

The Release and QA consoles show standard events, SDK requests, durations, and runtime errors.

## Live behavior

After the release is health-checked, activated, and the Hosted Game is enabled, the same package uses the live managed runtime. Program, campaign, reward, credentials, and webhook configuration are resolved server-side.

## Operator test sequence

1. Open Merchant Hosted Games or Admin Hosted Games.
2. Download the demo game ZIP.
3. Create a Hosted Game named `Reward Drop SDK Demo`.
4. Assign a Distribution Program.
5. Configure and verify an isolated MySQL database.
6. Upload the ZIP. It should appear as a validated draft release.
7. Open Releases and select Preview & Test.
8. Collect eight gifts before twenty seconds expires.
9. Confirm `Test reward delivered` appears.
10. Confirm the preview console records run start, score updates, qualification, completion, state save, score submission, and SDK timing.
11. Confirm the result reports no live inventory consumption.
12. Reset preview data and repeat the test.
13. Run the release health check.
14. Activate the release.
15. Enable the Hosted Game only when the Distribution Program and isolated database are ready.

## Package generation

Build locally:

```bash
php scripts/build_hosted_reward_drop_demo_v1.php /tmp/reward-drop-sdk-demo-v2.0.0.zip
```

Validate source and ZIP:

```bash
php scripts/validate_hosted_reward_drop_demo_v1.php /tmp/reward-drop-sdk-demo-v2.0.0.zip
```

The GitHub workflow builds and publishes the same ZIP as a workflow artifact.

## SQL

No new SQL is required for this demo.

Required Hosted Games SQL was introduced by:

- `database/hosted_games_management_v1.sql`
- `database/hosted_games_analytics_diagnostics_v1.sql`
- `database/hosted_games_release_qa_foundation_v1.sql`
