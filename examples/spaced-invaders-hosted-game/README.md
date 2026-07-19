# Spaced Invaders — Hosted Games Integration

This feature branch isolates the Microgifter Hosted Game Standard v1 integration work for **Spaced Invaders**.

Branch: `feature/spaced-invaders-hosted-game-integration-20260718`

The branch is intentionally not merged into `integration-from-repair-20260628` or `main`.

## Current upload package

`spaced-invaders-hosted-game-v1.2.0.zip`

The complete static browser-game ZIP is generated outside the repository and uploaded through the Merchant or Admin Hosted Games workspace. It contains `index.html`, `styles.css`, `game.js`, `game.json`, WebP battlefield assets, the landing cover, and package documentation.

## Specialty settlement gameplay v1.2.0

The former eight-tab market and trade interface has been replaced with one specialty-driven command screen for each settlement.

- **Iron Hollow — Tank Defense Engineering:** Tank Buster range, reload, payload progression, overlapping-zone support, and the permanent Tank Buster ledger.
- **Harvest Vale — Civilian Recovery Command:** population recovery, injury treatment, morale, hospital restoration, and emergency triage.
- **Loom Ridge — Shield Engineering:** increased shield capacity, passive recharge, and emergency shield surge.
- **Fort Ember — Air Defense Command:** anti-air missiles, radar, swarm guns, capture systems, and regional UFO patrol craft.

Settlement levels 1–5 now unlock named specialty milestones and apply specialty-specific combat bonuses. Levels and XP persist between runs.

Trade routes, supply convoys, market inventory, production controls, and the expansion placeholder are removed from active settlement gameplay.

## Permanent Tank Buster ledger

Every settlement records:

- lifetime Tank Buster kills;
- current-run kills;
- assists;
- shots fired and hits;
- accuracy;
- elite tanks destroyed;
- highest kills in one wave;
- most recently destroyed tank;
- recent kill entries.

Every ground tank receives a unique runtime ID and per-settlement Tank Buster damage record. The settlement delivering final damage receives the kill. Other settlements that contributed Tank Buster damage receive assists. A tank can only be credited once.

Standalone career records use browser local storage. Hosted Games stores the same settlement levels, XP, and lifetime ledger through the isolated `saveState()` / `loadState()` career record.

## Hosted runtime contract

The game uses only `window.MicrogifterGame`. It contains no API credentials, webhook secrets, database credentials, Microgifter cookies, hardcoded campaign IDs, or custom reward endpoints.

The SDK flow uses:

1. `ready()` to load player, Distribution Program, and reward context.
2. `connectPlayer()` to connect the game to the player's Inbox.
3. `startRun()` to create a protected run.
4. level and score events at meaningful wave checkpoints.
5. `saveState()` / `loadState()` for career and settlement records.
6. `submitScore()` for leaderboard-ready scores.
7. `qualify()` and `complete()` after Wave 5 with at least one settlement alive.
8. `abandonRun()` for unfinished sessions.
9. `reportError()` for runtime diagnostics.

## Reward toast

After the runtime accepts completion, the existing game toast stack displays either:

- `Gift sent to your Microgifter Inbox: [reward name].`
- `Gift earned! [reward name] was sent for delivery to your Microgifter Inbox.`

Campaign, program, reward inventory, issuance, webhook processing, and Inbox delivery remain server-controlled by Microgifter.

## Validation

Both the standalone and Hosted Games packages pass JavaScript syntax validation, JSON validation, ZIP integrity checks, and runtime contract tests covering specialty selection, persistent settlement snapshots, final-hit Tank Buster credit, assist credit, and duplicate-kill prevention.
