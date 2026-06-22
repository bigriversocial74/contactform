# Local Quest Rewards

Local Quest Rewards is a full demo app for the Microgifter Public Distribution API.

It behaves like a third-party local experience app:

1. A user opens the app as a guest/demo user.
2. The app assigns a stable `external_user_id`.
3. The app creates a sandbox linked Microgifter account.
4. The user completes a local quest.
5. The app checks its local reward permission rule.
6. The app issues the mapped Microgift through the Public Distribution API.
7. The app checks reward status and records webhook delivery.

## Files

```text
examples/local-quest-rewards/
  README.md
  app.php
  config.example.php
  index.php
  quests.php
  webhook.php
  data/README.md
```

## Run locally

Copy config:

```bash
cp examples/local-quest-rewards/config.example.php examples/local-quest-rewards/config.php
```

Edit `config.php` with a test key, program ID, template ID, and webhook secret.

Start PHP:

```bash
php -S 127.0.0.1:8090 -t examples/local-quest-rewards
```

Open:

```text
http://127.0.0.1:8090/index.php
```

## Demo flow

1. Save or change the demo user.
2. Click **List Microgifter programs** to validate credentials.
3. Click **Create sandbox linked account**.
4. Complete a quest.
5. Issue the reward.
6. Check status.
7. Send a Microgifter webhook test and confirm `webhook-events.log` records it.

## Reward mapping

Reward rules live in `quests.php`. Each quest maps to:

- `event_type`
- `program_id` or default program fallback
- `template_id` or default template fallback
- `reward_label`
- local permission rules

## Permission model

This demo has two permission layers.

### Local app permission

The Quest app checks that the user completed the quest, has a linked account, has not already received the quest reward, app mode is allowed, and reward IDs are configured.

### Microgifter permission

Microgifter still makes the final authorization decision: credential scope, app environment, program access, template membership, linked account validity, capacity, limits, and idempotency.

## Purpose

This app is the ecosystem proof. If this app needs hidden knowledge to work, the Public Distribution API docs or Microgifter permission system needs another pass.
