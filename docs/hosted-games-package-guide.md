# Microgifter Hosted Games package guide

Hosted Games lets a merchant upload a browser game ZIP, attach one Distribution Program, and publish the game at:

```text
https://microgifter.com/games/your-game-slug/
```

Microgifter manages the Developer App, encrypted API credential, signed webhook, campaign, reward inventory, player connection, run authorization, isolated MySQL database, and Inbox delivery. Game JavaScript never receives those credentials.

## Recommended ZIP structure

```text
index.html
game.json
game.js
game.css
assets/
  cover.webp
  icon.png
  sound.mp3
```

The package may contain one wrapper directory. Hosted Games removes it automatically when every package file is inside that directory.

## Standard v1 manifest

New games should use the Hosted Game Standard v1 schema:

```json
{
  "schema": "microgifter.hosted-game/v1",
  "name": "Hosted Game Starter",
  "version": "1.0.0",
  "entry": "index.html",
  "category": "casual",
  "orientation": "any",
  "viewport": {
    "min_width": 320,
    "min_height": 480
  },
  "session": {
    "max_duration_seconds": 180
  },
  "capabilities": [
    "player",
    "runs",
    "events",
    "state",
    "scores",
    "leaderboard",
    "inbox",
    "fullscreen",
    "audio"
  ],
  "events": [
    "game_loaded",
    "run_started",
    "level_started",
    "score_updated",
    "level_completed",
    "player_qualified",
    "run_completed",
    "run_abandoned",
    "runtime_error"
  ],
  "scoring": {
    "mode": "points",
    "sort": "high",
    "integer": true
  },
  "qualification": {
    "mode": "game_reported"
  },
  "network": {
    "connect": []
  },
  "assets": {
    "cover": "assets/cover.webp",
    "icon": "assets/icon.png"
  }
}
```

Complete manifest, SDK, event, capability, and security documentation is in `docs/hosted-game-standard-v1.md`.

Packages without `game.json` remain supported in legacy compatibility mode.

## Supported package files

Hosted Games accepts static browser assets including HTML, CSS, JavaScript, JSON, images, audio, video, fonts, WebGL, WASM, Unity WebGL data, compressed assets, 3D models, captions, and PDFs.

Executable server files, dependency manifests, hidden files, symbolic links, parent-directory paths, duplicate paths, and unsafe compressed packages are rejected. Merchant-uploaded PHP is not executed inside Microgifter.

## Standard SDK example

```js
const session = await MicrogifterGame.ready();

if (!session.player.signed_in) {
  MicrogifterGame.signIn();
}

if (!session.player.connected) {
  await MicrogifterGame.connectPlayer();
}

await MicrogifterGame.startRun({ mode: "classic" });
await MicrogifterGame.updateScore(10, { level: 1 });
await MicrogifterGame.qualify({ target: 10 });

const result = await MicrogifterGame.complete({
  score: 10,
  result: { level: 1 }
});
```

The older explicit `MicrogifterGame.completeRun({runId, runToken, ...})` API remains supported for existing uploaded games and custom integrations.

## Browser isolation

The uploaded game runs inside a sandboxed iframe with an opaque origin. The game cannot directly access:

- Microgifter cookies or CSRF tokens;
- API credentials or webhook secrets;
- game database credentials;
- merchant or administrator pages;
- another game’s files or database.

Standard v1 generates iframe permissions from declared capabilities and blocks arbitrary network requests unless an HTTPS origin is declared in `network.connect`.

## Publishing flow

1. Open Merchant or Admin Hosted Games.
2. Create the game identity and URL slug.
3. Upload the game cover image or retain an external cover URL.
4. Upload the game ZIP.
5. Select the Distribution Program.
6. Microgifter resolves the campaign and active reward inventory and provisions encrypted credentials.
7. Microgifter Admin assigns and tests the isolated MySQL database.
8. Turn **Game enabled** on after readiness is complete.

## Package limits

- Maximum ZIP size: 100 MB
- Maximum files: 5,000
- Maximum extracted size: 512 MB
- Maximum single extracted file: 150 MB
- Maximum `game.json`: 64 KB
- Maximum HTML entry: 20 MB
- Maximum state payload: 64 KB
- Maximum event or score metadata: 32 KB

Larger packages and custom server verification must use a separately reviewed deployment profile.
