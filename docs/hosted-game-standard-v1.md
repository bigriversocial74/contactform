# Microgifter Hosted Game Standard v1

Hosted Game Standard v1 defines one package manifest, one browser SDK, one event vocabulary, and one isolated runtime contract for every game uploaded through Microgifter Hosted Games.

The standard does not expose API credentials, webhook secrets, database credentials, CSRF tokens, or Microgifter cookies to game code. Games run in a sandboxed opaque-origin iframe and communicate only through `window.MicrogifterGame`.

## Standard manifest

New packages should include `game.json`:

```json
{
  "schema": "microgifter.hosted-game/v1",
  "name": "Reward Drop",
  "version": "1.0.0",
  "entry": "index.html",
  "description": "Collect gift drops before time expires.",
  "category": "arcade",
  "orientation": "any",
  "viewport": {
    "min_width": 320,
    "min_height": 480
  },
  "session": {
    "max_duration_seconds": 120
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

Packages without `game.json`, and older manifests without the `schema` field, continue to run in legacy compatibility mode. New packages should use the explicit Standard v1 schema.

## Manifest fields

### Identity

- `schema`: must be `microgifter.hosted-game/v1`.
- `name`: game display name, maximum 180 characters.
- `version`: semantic version such as `1.0.0`.
- `entry`: HTML entry file inside the ZIP.
- `description`: optional package description.
- `category`: `casual`, `arcade`, `puzzle`, `trivia`, `music`, `video`, `adventure`, `simulation`, `educational`, `promotional`, or `other`.

### Presentation

- `orientation`: `any`, `portrait`, or `landscape`.
- `viewport.min_width`: 240–4096.
- `viewport.min_height`: 240–4096.
- `assets.cover`: optional package image path.
- `assets.icon`: optional package image path.

The Hosted Games management modal also supports a separate platform cover-image upload. That uploaded platform cover is used for Hosted Games cards and public listings. Package assets remain available inside the game release.

### Session policy

`session.max_duration_seconds` controls the server-issued run expiration and must be between 30 seconds and 24 hours. The server remains authoritative even when a game displays its own timer.

### Capabilities

Supported capabilities are:

- `player`
- `runs`
- `events`
- `state`
- `scores`
- `leaderboard`
- `inbox`
- `fullscreen`
- `pointer_lock`
- `forms`
- `modals`
- `popups`
- `downloads`
- `gamepad`
- `motion`
- `audio`
- `clipboard_write`

Capabilities control both SDK access and iframe permissions. The runtime never adds `allow-same-origin`.

### Scoring

- `mode`: `none`, `points`, `time`, `distance`, or `custom`.
- `sort`: `high` or `low`.
- `integer`: whether score updates must be integers.

### Qualification

- `none`: the game cannot request a reward.
- `game_reported`: the game may report qualification through the standard completion call.
- `server_review`: reward qualification must be performed by a separately reviewed server endpoint; browser-reported qualification is rejected.

For high-value rewards, use `server_review` and add a reviewed game-specific verification service.

### Network access

`network.connect` is empty by default. Standard v1 games cannot make arbitrary XHR, Fetch, or WebSocket requests unless an HTTPS origin is declared. Values may be `self` or an HTTPS origin, with a maximum of 12 origins.

External API secrets must never be placed in the manifest or game JavaScript.

## SDK

Wait for the bridge before enabling gameplay:

```js
const session = await MicrogifterGame.ready();
const player = await MicrogifterGame.getPlayer();
const program = await MicrogifterGame.getProgram();
const reward = await MicrogifterGame.getReward();
```

The SDK version is available as:

```js
MicrogifterGame.version;          // 1.1.0
MicrogifterGame.standardVersion;  // 1.0.0
```

### Start a run

```js
const response = await MicrogifterGame.startRun({
  mode: "classic",
  clientVersion: "1.0.0"
});
```

The SDK keeps the active run in memory. The run ID and token are never exposed to other players and should not be copied into analytics or leaderboard metadata.

### Update score

```js
await MicrogifterGame.updateScore(25, {
  level: 2,
  combo: 4
});
```

This records the standardized `score_updated` event. It does not automatically create a leaderboard entry.

### Level events

```js
await MicrogifterGame.levelStarted(2, { mode: "classic" });
await MicrogifterGame.levelCompleted(2, { elapsed_seconds: 18 });
```

### Mark qualification

```js
await MicrogifterGame.qualify({
  target: 25,
  achieved: 25
});
```

This records the qualification event in the current run. Reward issuance still occurs only when the server accepts `MicrogifterGame.complete()`.

### Complete a run

```js
const response = await MicrogifterGame.complete({
  score: 25,
  result: {
    level: 2,
    elapsed_seconds: 42
  }
});
```

The SDK uses the current run and qualification state. Existing integrations may continue using `completeRun()` with explicit run identifiers.

### Abandon a run

```js
await MicrogifterGame.abandonRun({
  reason: "player_exit",
  result: { level: 2 }
});
```

The server closes the run without issuing a reward and records `run_abandoned`.

### Emit a standard event

```js
await MicrogifterGame.emitEvent("runtime_error", {
  message: "Audio context unavailable"
});
```

Only events declared in the active Standard v1 manifest are accepted for standard packages.

### State, scores, and leaderboards

```js
await MicrogifterGame.saveState("career", { level: 4 });
const state = await MicrogifterGame.loadState("career");

await MicrogifterGame.submitScore({
  score: 1250,
  metadata: { mode: "classic" }
});

const board = await MicrogifterGame.getLeaderboard(20);
```

State and leaderboard data use the isolated database assigned to that game.

### Error reporting

```js
try {
  await startAudio();
} catch (error) {
  await MicrogifterGame.reportError(error, { phase: "audio_start" });
}
```

Do not include passwords, access tokens, personal contact details, claim codes, or payment information in event payloads.

## Standard events

- `game_loaded`
- `run_started`
- `level_started`
- `score_updated`
- `level_completed`
- `player_qualified`
- `run_completed`
- `run_abandoned`
- `runtime_error`

Server-authoritative events such as run start, completion, abandonment, reward queueing, and delivery remain recorded even if game JavaScript is interrupted.

## Isolation and message security

- The game iframe has an opaque origin.
- `allow-same-origin` is never enabled.
- Sandbox and Permissions Policy values are generated from declared capabilities.
- Standard packages receive a restrictive Content Security Policy.
- Cross-window messages require the correct iframe source, game slug, channel, direction, and per-document random bridge token.
- Bridge messages are size limited.
- Runtime writes require the authenticated Microgifter session and the parent shell’s CSRF token.
- Run writes require a server-issued run token stored only in memory.
- Program, campaign, and reward identifiers are resolved and snapshotted server-side.

## Backward compatibility

The following existing methods remain supported:

- `connectPlayer()`
- `startRun()`
- `completeRun()`
- `getRun()`
- `loadState()`
- `saveState()`
- `submitScore()`
- `getLeaderboard()`
- `track()`
- `openInbox()`
- `signIn()`

Legacy packages keep their existing broad asset compatibility. New Standard v1 packages receive capability-based permissions and a tighter network policy.
