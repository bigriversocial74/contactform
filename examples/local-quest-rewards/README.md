# Local Quest Rewards

Local Quest Rewards is a working demo app for the Microgifter Public Distribution API.

It behaves like a third-party local experience app:

1. A participant lands on a cover page.
2. The participant creates or signs into a Local Quest account.
3. The app assigns a stable `external_user_id` for that Local Quest user.
4. The participant connects a Microgifter account through Microgifter account-link consent.
5. The participant completes a local quest.
6. The app checks its local reward permission rule.
7. The app issues the mapped Microgift through the Public Distribution API.
8. The app shows the reward in a Quest wallet.
9. The participant can claim the reward inside the Quest app.
10. Claim activity reports back to Microgifter.

Sandbox linking is only a developer shortcut. It is not the primary user flow.

## Platform model

One Microgifter merchant can approve products/templates for a Distribution Program. Multiple third-party Quest-style apps can be allowed to distribute those approved rewards.

Microgifter remains the system of record for reward ownership, item status, claim state, redemption, webhooks, and audit history. The Quest app owns app login, quest progress, local action completion, wallet UX, and the admin controls for the third-party experience.

## Microgifter account creation

Microgifter has its own account creation endpoint and page:

- `signup.php`
- `api/auth/register.php`

That belongs to Microgifter identity, not the third-party Public Distribution API. The current real integration path is account-link consent: the user signs into or creates a Microgifter account on Microgifter, approves the connection, then returns to the app.

## Files

```text
examples/local-quest-rewards/
  README.md
  app.php
  config.example.php
  cover.php
  signin.php
  index.php
  link-callback.php
  wallet.php
  wallet-actions.php
  admin.php
  quests.php
  webhook.php
  database/local_quest_rewards.sql
  data/README.md
```

## Run locally

Copy config:

```bash
cp examples/local-quest-rewards/config.example.php examples/local-quest-rewards/config.php
```

Edit `config.php` with a test key, program ID, template ID, app public URL, webhook secret, and admin values.

Start PHP:

```bash
php -S 127.0.0.1:8090 -t examples/local-quest-rewards
```

Open the participant app:

```text
http://127.0.0.1:8090/cover.php
```

Open the admin backend:

```text
http://127.0.0.1:8090/admin.php
```

## Admin backend

`admin.php` is the Quest app control center. It includes:

- admin login/logout
- dashboard KPIs
- quest catalog and quest editor
- user account list
- Microgifter link reset for users
- wallet/reward records
- claim status management
- integration event log
- storage/settings view

The admin backend manages the third-party app layer only. It does not replace Microgifter merchant controls, Distribution Program approval, reward ownership, redemption truth, or Microgifter audit history.

## SQL schema

The Quest app has its own schema file:

```text
database/local_quest_rewards.sql
```

It defines tables for admin users, participant users, link states, quests, completions, rewards, reward claims, admin audit events, event logs, and app state.

Current runtime default is still `json` storage for zero-config demo use. The SQL schema is ready for the real app storage pass.

## Real app flow

1. Open the cover page.
2. Create a Local Quest account.
3. Open the quest board.
4. Click **Connect Microgifter account**.
5. Sign in or create a Microgifter account on Microgifter if prompted.
6. Approve the account-link request.
7. Return to `link-callback.php`.
8. Complete a quest.
9. Issue the reward.
10. Check status.
11. Open `wallet.php`.
12. Refresh reward status from Microgifter.
13. Claim the reward inside the Quest app.
14. Confirm claim report status changes to `reported_to_microgifter`.

## Reward mapping

Reward rules live in `quests.php`. The admin backend can publish changes to that file. Each quest maps to event type, program ID, template ID, reward label, and local permission rules.

## Wallet and claim flow

`wallet.php` displays issued rewards from the Local Quest user state. It pulls item IDs from reward issue/status responses when available.

The app-side claim button calls:

```text
POST /api/public/v1/rewards/claim.php
```

The wallet action records `claimed_in_quest_app`, `reported_to_microgifter`, the claim endpoint, and the returned Microgifter event ID when available.

## Permission model

The Quest app checks that the participant is signed in, completed the quest, connected a Microgifter account, has not already received the quest reward, app mode is allowed, and reward IDs are configured.

Microgifter still makes the final authorization decision: credential scope, app environment, program access, template membership, linked account validity, capacity, limits, and idempotency.

## Purpose

This app is the ecosystem proof. If this app needs hidden knowledge to work, the Public Distribution API docs or Microgifter permission system needs another pass.
