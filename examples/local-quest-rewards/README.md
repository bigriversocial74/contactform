# Local Quest Rewards

Local Quest Rewards is a working demo app for the Microgifter Public Distribution API.

It behaves like a third-party local experience app:

1. A participant lands on a cover page.
2. The participant creates or signs into a Local Quest account.
3. The app assigns a stable `external_user_id` for that Local Quest user.
4. The participant connects a Microgifter account through Microgifter account-link consent.
5. The participant completes a local quest using QR and optional geolocation context.
6. The app checks its local reward permission rule.
7. The app issues the mapped Microgift through the Public Distribution API.
8. The app shows the reward in a Quest wallet.
9. The participant can claim the reward inside the Quest app with QR/geolocation evidence.
10. Claim activity reports back to Microgifter.

Sandbox linking is only a developer shortcut. It is not the primary user flow.

## Platform model

One Microgifter merchant can approve products/templates for a Distribution Program. Multiple third-party Quest-style apps can be allowed to distribute those approved rewards.

Microgifter remains the system of record for reward ownership, item status, claim state, redemption, webhooks, and audit history. The Quest app owns app login, quest progress, QR/geolocation task context, local action completion, wallet UX, and the admin controls for the third-party experience.

## UI model

The user and admin surfaces now share a portal-style design system:

- white dashboard cards
- KPI blocks
- quick action panels
- top navigation bar
- compact left rail navigation
- rail expands on hover to reveal labels
- user wallet and admin claim pages include QR/geolocation helpers

Shared assets:

```text
assets/portal.css
assets/portal.js
```

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
  admin-portal.php
  quests.php
  webhook.php
  assets/portal.css
  assets/portal.js
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

Open the original admin backend:

```text
http://127.0.0.1:8090/admin.php
```

Open the styled portal admin dashboard:

```text
http://127.0.0.1:8090/admin-portal.php
```

## Admin backend

`admin.php` is the original Quest app control center. It includes:

- admin login/logout
- dashboard KPIs
- quest catalog and quest editor
- user account list
- Microgifter link reset for users
- wallet/reward records
- claim status management
- integration event log
- storage/settings view

`admin-portal.php` is the styled dashboard layer based on the requested portal layout. It includes the expanding left rail, KPI cards, quick actions, user/customer views, wallet views, claim QR scan forms, geolocation capture, and links back to `admin.php` for existing write flows.

The admin backend manages the third-party app layer only. It does not replace Microgifter merchant controls, Distribution Program approval, reward ownership, redemption truth, or Microgifter audit history.

## QR scanning and geolocation

`assets/portal.js` supports:

- camera QR scanning through `BarcodeDetector` when available
- manual QR/promo/prize code input fallback
- browser geolocation capture
- hidden form fields for `qr_payload`, `geo_lat`, `geo_lng`, `geo_accuracy`, and `geo_captured_at`

User quest completion captures QR/geolocation context into local event state. Wallet claim reporting sends QR/geolocation evidence in the Microgifter claim metadata.

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
8. Scan or paste a quest QR/task code.
9. Capture geolocation if the quest requires location proof.
10. Complete a quest.
11. Issue the reward.
12. Check status.
13. Open `wallet.php`.
14. Refresh reward status from Microgifter.
15. Scan/paste claim QR or prize code.
16. Capture claim geolocation.
17. Claim the reward inside the Quest app.
18. Confirm claim report status changes to `reported_to_microgifter`.

## Reward mapping

Reward rules live in `quests.php`. The admin backend can publish changes to that file. Each quest maps to event type, program ID, template ID, reward label, and local permission rules.

## Wallet and claim flow

`wallet.php` displays issued rewards from the Local Quest user state. It pulls item IDs from reward issue/status responses when available.

The app-side claim button calls:

```text
POST /api/public/v1/rewards/claim.php
```

The wallet action records `claimed_in_quest_app`, `reported_to_microgifter`, the claim endpoint, the returned Microgifter event ID when available, the QR payload, and claim geolocation metadata.

## Permission model

The Quest app checks that the participant is signed in, completed the quest, connected a Microgifter account, has not already received the quest reward, app mode is allowed, and reward IDs are configured.

Microgifter still makes the final authorization decision: credential scope, app environment, program access, template membership, linked account validity, capacity, limits, and idempotency.

## Purpose

This app is the ecosystem proof. If this app needs hidden knowledge to work, the Public Distribution API docs or Microgifter permission system needs another pass.
