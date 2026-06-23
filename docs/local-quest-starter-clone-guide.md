# Microgifter starter app clone guide

## Purpose

The Local Quest app is now a starter foundation. It should be cloneable into other third-party Microgifter apps without rewriting the core platform pieces.

Clone targets can include:

- Local Quest Rewards
- Loyalty Trigger Rewards
- Creator Fan Drop
- Event Check-in Rewards
- Fitness Challenge Rewards
- Local Guide Rewards
- Venue Passport
- Sponsor Treasure Hunt

## Foundation pieces to keep

Keep these shared pieces intact when cloning:

```text
app.php
security.php
storage-sql.php
install.php
install-lock.php
admin-auth.php
admin-roles.php
admin-credentials.php
wallet.php
wallet-actions.php
webhook.php
webhook-reconcile.php
database/local_quest_rewards.sql
database/local_quest_admin_auth.sql
assets/form-review.js
```

These provide:

- SQL-only runtime
- setup/install flow
- installer lock convention
- user accounts
- admin accounts
- admin roles
- Microgifter API client calls
- account linking
- reward issuing
- wallet display
- claim reporting
- webhook verification
- webhook reconciliation
- CSRF/session helpers
- signed-code helpers

## App-specific pieces to rename or replace

These are app-flavor files:

```text
quests.php
quest-controls.php
admin-quest-controls.php
admin-signed-codes.php
cover.php
signin.php
index.php
admin.php
admin-portal.php
assets/portal.css
assets/portal.js
README.md
```

For a cloned app, rename concepts like:

```text
quest -> action / mission / check-in / drop / challenge
venue -> sponsor / creator / gym / event / merchant
reward -> microgift / offer / unlock / perk
```

## Clone checklist

1. Copy the starter folder.
2. Rename the folder and app title.
3. Update `README.md` with the new app purpose.
4. Update `quests.php` to define the new action model.
5. Update front-end language in `cover.php`, `signin.php`, and `index.php`.
6. Keep `security.php`, `storage-sql.php`, `wallet-actions.php`, and `webhook-reconcile.php` unchanged unless the foundation itself changes.
7. Run `install.php` on the new app host.
8. Configure database settings.
9. Configure Microgifter Developer API key.
10. Configure default Distribution Program and template IDs.
11. Create the first owner admin.
12. Confirm GitHub Actions pass.
13. Confirm webhook receiver validates signatures.
14. Confirm reward issue, list, status, claim, and webhook reconciliation flows.

## Recommended folder model later

Eventually split the foundation from app flavor:

```text
starter-foundation/
  app.php
  security.php
  storage-sql.php
  install.php
  install-lock.php
  admin-auth.php
  admin-roles.php
  wallet-actions.php
  webhook-reconcile.php

starter-apps/
  local-quest-rewards/
  loyalty-trigger-rewards/
  creator-fan-drop/
```

For now, Local Quest remains the working foundation until the SQL/service layer and installer are proven.

## Minimum quality bar before cloning broadly

Before making many starter apps, finish:

- direct installer lock wiring inside `install.php`
- direct installer review script loading inside `install.php`
- owner-only enforcement inside `admin-credentials.php`
- signed-code enforcement for protected quests
- webhook replay/deduplication
- SQL-first repositories instead of translated app state arrays
- end-to-end setup test
