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

Current upload package: `spaced-invaders-hosted-game-v1.1.2.zip`.

## UFO kill-credit reliability v1.1.2

- Settlement UFO kill totals continue beyond the 10-kill Capture Beam milestone.
- Unlocking capture technology no longer auto-arms the beam or changes normal missile-defense behavior.
- Capture mode is armed manually from Settlement Control.
- Stale capture targets are cleared automatically so a settlement cannot remain permanently blocked.
- A settlement continues defending against other hostile UFOs while one craft is descending under the capture beam.
- Significant patrol-craft, energy-weapon, and mixed-defense damage retains settlement credit during same-frame destruction.
- Battlefield labels now display total UFO kills instead of presenting the 10-kill unlock target as a counter cap.

## Settlement defense reliability v1.1.1

- Newly constructed defenses are enabled immediately, even when the settlement previously had every active slot occupied.
- Settlement level-ups can no longer shrink defense capacity below the active loadout.
- Every built Tank Buster zone remains visible: orange means active and gray means built but disabled.
- Tank Buster zones include the full tank hull at zone boundaries.
- Tank Busters prioritize breached and furthest-advanced tanks and credit the firing settlement with the kill.
- Radar, anti-air, swarm guns, hospitals, Tank Busters, and their UI status use one consistent enabled-state check.
- Airborne patrol state is reconciled automatically so launched craft cannot silently disappear.
- Patrol craft engage hostile UFOs, suicide drones, and incoming missiles, with UFO kills credited to the patrol craft's originating settlement.

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

## Validation performed

Both game versions passed JavaScript syntax checks, JSON validation, ZIP integrity checks, browser runtime checks, a 20-kill continuity test, two-hit automatic missile-credit testing, stale capture-lock recovery, mixed-defense credit testing, and manual Capture Beam landing validation.
