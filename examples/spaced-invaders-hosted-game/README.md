# Spaced Invaders — Hosted Games Integration

This feature branch isolates the Microgifter Hosted Game Standard v1 integration work for **Spaced Invaders**.

Branch: `feature/spaced-invaders-hosted-game-integration-20260718`

The branch is intentionally not merged into `integration-from-repair-20260628` or `main`.

## Upload package

The complete static browser-game ZIP is generated outside the repository and uploaded through the existing Merchant or Admin Hosted Games workspace. The ZIP contains:

- `index.html`
- `styles.css`
- `game.js`
- `game.json`
- WebP game assets
- landing-page cover
- package README

## Hosted runtime contract

The game uses only `window.MicrogifterGame`. It does not contain API credentials, webhook secrets, database credentials, Microgifter cookies, or custom reward endpoints.

Implemented SDK flow:

1. `ready()` loads the signed-in player, Distribution Program, and reward context.
2. `connectPlayer()` connects the game to the player's Microgifter Inbox when required.
3. `startRun()` creates a server-authorized game run.
4. `levelStarted()`, `levelCompleted()`, and `updateScore()` report meaningful checkpoints.
5. `saveState()` / `loadState()` preserve career totals.
6. `submitScore()` records leaderboard-ready scores.
7. `qualify()` and `complete()` issue the connected reward after the player completes Wave 5 with at least one settlement remaining.
8. `abandonRun()` closes unfinished runs on page exit.
9. `reportError()` reports runtime failures through Hosted Games diagnostics.

## Reward toast

After the hosted runtime accepts completion, the game uses its existing right-side toast stack:

- Delivered: `Gift sent to your Microgifter Inbox: [reward name].`
- Queued: `Gift earned! [reward name] was sent for delivery to your Microgifter Inbox.`

The reward name is read from the Hosted Games reward context. Campaign, program, inventory, issuance, webhook, and Inbox delivery remain server-controlled by Microgifter.

## Qualification

Initial low-value promotional qualification uses `game_reported` mode:

- complete Wave 5;
- keep at least one settlement alive;
- submit the final score and settlement result payload.

High-value reward configurations should move to a reviewed `server_review` integration.
