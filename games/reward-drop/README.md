# Microgifter Reward Drop v1

Reward Drop is a first-party browser game hosted at:

`https://microgifter.com/games/reward-drop/`

It reuses the existing Microgifter session. Players do not create a separate game account or password.

## End-to-end flow

1. Reward Drop recognizes the signed-in Microgifter session.
2. On first use, the existing Microgifter account-link approval flow connects the Developer App to that user.
3. The server creates a one-time, expiring game run and stores only a SHA-256 hash of the run token.
4. The player collects the required gift score.
5. The server validates CSRF, user ownership, token hash, elapsed time, target score, expiration, and reward cooldown.
6. The server calls `/api/public/v1/rewards/issue.php` with the live API key and a unique idempotency key.
7. The Public Distribution API queues the configured campaign reward.
8. Signed lifecycle webhooks update the local game run.
9. The player opens `/inbox.php` to view the delivered Microgift.

The API credential and webhook signing value are never sent to browser JavaScript.

## Required SQL

Import:

`database/reward_drop_game_v1.sql`

## Required environment values

```text
MG_REWARD_DROP_API_KEY=<live server-side Developer API key>
MG_REWARD_DROP_PROGRAM_ID=<active Distribution Program public ID>
MG_REWARD_DROP_TEMPLATE_ID=<active PPPM reward template public ID attached to the program>
MG_REWARD_DROP_WEBHOOK_SECRET=<signing value shown when the webhook secret is rotated>
```

Recommended explicit values:

```text
MG_REWARD_DROP_API_BASE_URL=https://microgifter.com
MG_REWARD_DROP_PUBLIC_URL=https://microgifter.com/games/reward-drop
MG_REWARD_DROP_STATE_KEY=<random long secret used to sign account-link state>
MG_REWARD_DROP_TARGET_SCORE=12
MG_REWARD_DROP_DURATION_SECONDS=20
MG_REWARD_DROP_MIN_PLAY_SECONDS=8
MG_REWARD_DROP_REWARD_COOLDOWN_HOURS=24
```

When `MG_REWARD_DROP_STATE_KEY` is omitted, the server derives a state-signing key from the API key. An explicit independent key is preferred.

## Developer App requirements

The Developer App and credential must both use the `live` environment and be active.

Required credential scopes:

```text
distribution:rewards.issue
distribution:rewards.status
```

Allowed origin:

```text
https://microgifter.com
```

Webhook URL:

```text
https://microgifter.com/games/reward-drop/webhook.php
```

The configured Developer App should use the same active Distribution Program referenced by `MG_REWARD_DROP_PROGRAM_ID`.

## Distribution Program requirements

Create or choose a program in `/merchant-distribution.php` with:

- type `gaming` or `external_api`
- active status
- active start/end window
- at least one attached PPPM template
- sufficient budget and item capacity
- a per-recipient limit compatible with the game cooldown

Set `MG_REWARD_DROP_TEMPLATE_ID` to the public ID of the attached PPPM template.

## Security controls

- existing Microgifter session only
- HttpOnly root-path session cookie
- same-origin CSRF protection
- signed, expiring account-link state
- one-time hashed game-run token
- minimum play duration
- maximum accepted score
- server-side reward cooldown
- unique external event ID
- Public API idempotency header
- signed webhook verification with five-minute timestamp tolerance
- webhook event and delivery deduplication
- user-scoped status lookup

## Manual QA

1. Import the SQL migration and configure all environment values.
2. In Developer API, confirm the app and credential are live and include both required scopes.
3. Configure the Reward Drop webhook URL and rotate/copy its signing value into the environment.
4. Open `/merchant-distribution.php` and confirm the Game Integration checks are ready.
5. While signed out, open `/games/reward-drop/` and confirm the page requests Microgifter sign-in.
6. Sign in and return to the game without creating another account.
7. Complete the one-time Inbox connection approval.
8. Start the game, reach the target, and confirm only one API reward request is created.
9. Confirm the result displays queued, then delivered after the signed webhook arrives.
10. Open `/inbox.php` and confirm the configured reward is present.
11. Replay or resubmit the same completion request and confirm no duplicate reward is created.
12. Confirm the cooldown blocks another reward until the configured window expires.
