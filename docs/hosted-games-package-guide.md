# Microgifter Hosted Games package guide

Hosted Games lets a merchant upload a complete browser game ZIP, connect it to a Microgifter Distribution Program, campaign, and reward, and publish it at a clean URL:

```text
https://microgifter.com/games/your-game-slug/
```

Each game has:

- its own merchant-owned game record and release history;
- a dedicated live Developer App and encrypted API credential;
- a signed per-game webhook;
- a selected Distribution Program, campaign, and PPPM reward;
- an isolated MySQL database configured by Microgifter Admin;
- protected Microgifter login, player connection, Inbox reward delivery, state, score, leaderboard, and event APIs.

## ZIP structure

The simplest package is:

```text
index.html
game.js
game.css
assets/
  image.png
  sound.mp3
```

A package can optionally include `game.json`:

```json
{
  "name": "Pizza Catcher",
  "version": "1.0.0",
  "entry": "index.html",
  "integrationVersion": "1",
  "description": "Catch pizza slices and qualify for a merchant reward."
}
```

The ZIP may contain one wrapper directory. Hosted Games removes that wrapper automatically when every file is inside it.

## Supported game files

Hosted Games accepts static browser assets including:

- HTML, CSS, JavaScript, JSON, source maps, text, XML, and CSV;
- JPG, PNG, GIF, WebP, SVG, ICO, and AVIF;
- MP3, M4A, AAC, WAV, OGG, and FLAC;
- MP4, WebM, MOV, and OGV;
- WOFF/WOFF2, TTF, OTF, and EOT fonts;
- WASM, WebGL data, Unity WebGL assets, binary bundles, Brotli, and gzip assets;
- GLB, glTF, OBJ, MTL, FBX, DAE, and 3DS models;
- VTT, SRT, LRC, and PDF files.

Executable server files, dependency manifests, hidden files, symbolic links, parent-directory paths, duplicate paths, and unsafe compressed packages are rejected. Merchant-uploaded PHP is not executed inside Microgifter. This prevents a game package from reading Microgifter cookies, server credentials, other merchants’ data, or the host filesystem.

Game-specific server features are implemented as reviewed Microgifter endpoints that connect to that game’s isolated database. The standard bridge already provides player state, scores, leaderboards, event tracking, runs, and reward issuance.

## Browser isolation

The uploaded game runs inside a sandboxed iframe with an opaque origin. The game cannot directly access:

- Microgifter session cookies;
- CSRF tokens;
- API credentials or webhook secrets;
- the game database username or password;
- merchant/admin pages;
- another hosted game’s private files or database.

The parent Microgifter shell exposes only the approved `window.MicrogifterGame` bridge.

## JavaScript bridge

Wait for the bridge before starting gameplay:

```js
const session = await MicrogifterGame.ready();

if (!session.player.signed_in) {
  MicrogifterGame.signIn();
}

if (!session.player.connected) {
  await MicrogifterGame.connectPlayer();
}
```

### Start a run

```js
const response = await MicrogifterGame.startRun({
  mode: "classic",
  clientVersion: "1.0.0"
});

const run = response.run;
```

Keep `run.run_id` and `run.run_token` in memory for the current play session. Do not put them in a public leaderboard or analytics payload.

### Complete without a reward

```js
await MicrogifterGame.completeRun({
  runId: run.run_id,
  runToken: run.run_token,
  qualified: false,
  score: 420,
  result: {
    reason: "target_not_reached",
    level: 3
  }
});
```

### Complete and request the configured reward

```js
const result = await MicrogifterGame.completeRun({
  runId: run.run_id,
  runToken: run.run_token,
  qualified: true,
  score: 1250,
  result: {
    level: 8,
    elapsedSeconds: 94
  }
});

console.log(result.run.reward_id, result.run.status);
```

Microgifter snapshots the selected program, campaign, and PPPM reward when the run begins. A merchant configuration change cannot alter an in-progress player’s reward.

Reward issuance is protected by the player’s Microgifter session, CSRF validation, game ownership, one-time run token, run expiration, API scopes, Distribution Program capacity, per-recipient limits, idempotency, and signed webhook confirmation. High-value games should add a reviewed game-specific server validation endpoint rather than trusting client-only qualification logic.

### Check reward status

```js
const status = await MicrogifterGame.getRun(run.run_id);
console.log(status.run.status);
```

### Save player state

```js
await MicrogifterGame.saveState("career", {
  level: 4,
  inventory: ["key", "map"],
  checkpoint: "desert-gate"
});

const saved = await MicrogifterGame.loadState("career");
console.log(saved.state);
```

State is stored in the isolated database assigned to this game.

### Submit a score

```js
await MicrogifterGame.submitScore({
  runId: run.run_id,
  runToken: run.run_token,
  score: 1250,
  metadata: {
    mode: "classic"
  }
});
```

### Load the leaderboard

```js
const result = await MicrogifterGame.getLeaderboard(20);
console.table(result.leaderboard);
```

Public leaderboard entries use anonymous player labels generated by Microgifter.

### Track a game event

```js
await MicrogifterGame.track("level.completed", {
  level: 4,
  elapsedSeconds: 31
});
```

Event names must use lowercase letters, numbers, dots, colons, underscores, or hyphens.

### Open the Inbox

```js
MicrogifterGame.openInbox();
```

## Merchant publishing flow

1. Open **Merchant → Hosted Games**.
2. Create the game identity and URL slug.
3. Upload the game ZIP.
4. Select the Distribution Program, campaign, and active PPPM reward.
5. Microgifter creates the dedicated live app, credential, scopes, and signed webhook.
6. Microgifter Admin assigns and tests the game’s isolated database.
7. Publish the game.

## Main-admin database flow

1. Open **Admin → Hosted Games**.
2. Choose the merchant game.
3. Enter a dedicated MySQL host, port, database name, username, and password.
4. Save and test.
5. Microgifter encrypts the username and password and initializes:
   - `microgifter_game_player_state`
   - `microgifter_game_scores`
6. The merchant can publish when all readiness checks pass.

The database password is never returned to the browser after saving and is never added to the game ZIP, JavaScript, `.htaccess`, or GitHub.

## Package limits

- Maximum ZIP size: 100 MB
- Maximum files: 5,000
- Maximum extracted size: 512 MB
- Maximum single extracted file: 150 MB
- Maximum `game.json`: 64 KB
- Maximum HTML entry: 20 MB
- Maximum standard state payload: 64 KB
- Maximum score metadata: 32 KB

Larger game packages or custom server runtimes should be reviewed as a separate deployment profile.
