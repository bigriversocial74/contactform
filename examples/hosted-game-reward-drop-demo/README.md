# Reward Drop SDK Demo v2.0.0

This folder is the source for an uploadable Microgifter Hosted Game Standard v1 demo.

## What it tests

- Standard v1 manifest validation
- Draft release upload and preserved ZIP storage
- Protected preview of an unpublished release
- Signed-in test player
- Test Distribution Program and simulated reward
- No live inventory consumption in preview
- Current `window.MicrogifterGame` SDK syntax
- Runs, standard events, scoring, qualification, completion, state, leaderboard, Inbox command, and error reporting
- Health check, activation, rollback, and analytics attribution

## Package contents

The upload ZIP contains:

- `index.html`
- `game.css`
- `game.js`
- `game.json`
- `assets/cover.svg`
- `assets/icon.svg`

## Test procedure

1. Create a Hosted Game in Merchant or Admin.
2. Assign a Distribution Program and isolated game database.
3. Open Releases.
4. Upload `examples/packages/reward-drop-sdk-demo-v2.0.0.zip`.
5. Open Preview & Test before activating.
6. Collect eight gifts.
7. Confirm the preview reports `simulated_delivered` and `inventory_consumed: false`.
8. Review Event, SDK Request, and Error consoles.
9. Reset the preview session and test again.
10. Run the release health check and activate when ready.

## SQL distinction

This uploadable demo does not use `database/reward_drop_game_v1.sql`.

- `reward_drop_game_v1.sql` belongs to the separate physical PHP application at `/games/reward-drop/`.
- This SDK demo uses the Hosted Games management, analytics, and release/QA tables plus the isolated database assigned to the Hosted Game.
