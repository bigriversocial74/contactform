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

Current upload package: `spaced-invaders-hosted-game-v1.1.0.zip`.

## Settlement defense expansion v1.1.0

- Adds **Tank Busters** as a buildable and equipable settlement defense.
- Each settlement owns an elliptical ground-defense zone. Neighboring zones overlap, so multiple equipped settlements can engage the same tank inside shared coverage.
- Tank Busters automatically lob anti-armor missiles at tanks inside the settlement zone and credit tank kills to the firing settlement.
- Captured UFOs launched from the Alien Hangar now leave the ground and fly visibly as patrol craft.
- When patrol craft originate from only one settlement, they protect all surviving settlements.
- Once patrol craft originate from multiple settlements, each patrol focuses on its original settlement zone while naturally sharing overlapping coverage.
- Patrol craft engage hostile UFOs, suicide drones, and incoming missiles.
- UFO kills made by patrol craft are credited to the patrol craft's originating settlement.
- Airborne patrol craft can be recalled through the Alien Hangar tab.

The standalone browser package and the Microgifter Hosted Games upload package use the same defense behavior.

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
