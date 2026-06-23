# Local Quest Rewards

Local Quest Rewards is a starter-app foundation for the Microgifter Public Distribution API.

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

Microgifter remains the system of record for reward ownership, item status, claim state, redemption, webhooks, and audit history. The starter app owns app login, quest progress, QR/geolocation task context, local action completion, wallet UX, admin controls, and SQL runtime storage for the third-party experience.

## Files

```text
examples/local-quest-rewards/
  README.md
  app.php
  install.php
  security.php
  storage-sql.php
  admin-auth.php
  admin-credentials.php
  config.example.php
  cover.php
  signin.php
  index.php
  link-callback.php
  wallet.php
  wallet-actions.php
  admin.php
  admin-portal.php
  admin-quest-controls.php
  quest-controls.php
  quests.php
  webhook.php
  assets/portal.css
  assets/portal.js
  database/local_quest_rewards.sql
  database/local_quest_admin_auth.sql
```

## Runtime requirement

The starter foundation is now SQL-only. It requires MySQL or MariaDB through PDO.

There is no `data/state.json` runtime, no JSON demo mode, and no JSON-to-SQL migration path in the starter foundation. SQL `JSON` columns are still used where they make sense for metadata, API responses, webhook payloads, QR/geolocation context, and audit context.

## Run locally with SQL storage

Copy config:

```bash
cp examples/local-quest-rewards/config.example.php examples/local-quest-rewards/config.php
```

Create the database and run schema:

```bash
mysql local_quest_rewards < examples/local-quest-rewards/database/local_quest_rewards.sql
mysql local_quest_rewards < examples/local-quest-rewards/database/local_quest_admin_auth.sql
```

Edit `config.php` with the database DSN/user/secret, Microgifter test key, program ID, template ID, app public URL, webhook secret, security signing secret, and admin bootstrap values.

Start PHP:

```bash
php -S 127.0.0.1:8090 -t examples/local-quest-rewards
```

Run installer diagnostics:

```text
http://127.0.0.1:8090/install.php
```

The installer diagnostics screen verifies PHP version, PDO/MySQL support, HTTP client support, important config values, database connectivity, and required schema tables. Remove or protect `install.php` after deployment.

Open the participant app:

```text
http://127.0.0.1:8090/cover.php
```

Open the admin tools:

```text
http://127.0.0.1:8090/admin.php
http://127.0.0.1:8090/admin-portal.php
http://127.0.0.1:8090/admin-quest-controls.php
http://127.0.0.1:8090/admin-credentials.php
```

## SQL runtime storage

The Quest app runtime is handled through:

```text
storage-sql.php
```

`app.php` exposes `lqr_load_state()` and `lqr_save_state()` for the current app layer, but both are SQL-backed. They read/write the Quest SQL tables through PDO.

The schema files are:

```text
database/local_quest_rewards.sql
database/local_quest_admin_auth.sql
```

They define tables for admin users, participant users, link states, quests, completions, rewards, reward claims, admin password recovery tokens, admin audit events, event logs, and app state.

## Admin backend

`admin.php` is the original Quest app control center. It includes dashboard KPIs, quest catalog/editor, user account list, wallet/reward records, claim status management, integration event log, and storage/settings view.

`admin-portal.php` is the styled dashboard layer based on the requested portal layout. It includes the expanding left rail, KPI cards, quick actions, user/customer views, wallet views, claim QR scan forms, geolocation capture, and links back to `admin.php` for existing write flows.

`admin-quest-controls.php` controls active/inactive state, featured state, visibility, sponsor/group, start/end dates, max total completions, and max total rewards.

`admin-credentials.php` manages admin users, admin sign-in, admin password changes, account status, and one-time recovery links.

The admin backend manages the third-party app layer only. It does not replace Microgifter merchant controls, Distribution Program approval, reward ownership, redemption truth, or Microgifter audit history.

## Security layer

`security.php` boots hardened sessions, applies idle timeout behavior, injects CSRF tokens into POST forms, blocks expired/missing CSRF tokens, and provides signed QR/code helpers with replay-key foundations.

Manual QR/code input still exists for usability. Protected production quests should move to signed-code-only verification.

## QR scanning and geolocation

`assets/portal.js` supports camera QR scanning through `BarcodeDetector` when available, manual QR/promo/prize code fallback, browser geolocation capture, and hidden form fields for QR/geolocation evidence.

User quest completion captures QR/geolocation context into app state. Wallet claim reporting sends QR/geolocation evidence in the Microgifter claim metadata.

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

Reward rules live in `quests.php`. The admin backend can publish changes to that file. Each quest maps to event type, program ID, template ID, reward label, controls, and local permission rules.

## Wallet and claim flow

`wallet.php` displays issued rewards from the Local Quest user state. It pulls item IDs from reward issue/status responses when available.

The app-side claim button calls:

```text
POST /api/public/v1/rewards/claim.php
```

The wallet action records `claimed_in_quest_app`, `reported_to_microgifter`, the claim endpoint, the returned Microgifter event ID when available, the QR payload, and claim geolocation metadata.

## Permission model

The Quest app checks that the participant is signed in, completed the quest, connected a Microgifter account, has not already received the quest reward, app mode is allowed, quest controls permit play, and reward IDs are configured.

Microgifter still makes the final authorization decision: credential scope, app environment, program access, template membership, linked account validity, capacity, limits, and idempotency.

## Purpose

This app is the ecosystem proof and starter foundation. If this app needs hidden knowledge to work, the Public Distribution API docs, installer, or Microgifter permission system needs another pass.
