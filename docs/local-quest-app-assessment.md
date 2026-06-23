# Local Quest Rewards app assessment

## Current score

**Overall: 6.8 / 10**

The app is now a credible working ecosystem demo. It has participant accounts, account linking, quest completion, reward issuing, wallet display, claim reporting, admin pages, quest controls, SQL schema, webhook receiver, and validation coverage.

It is not production-grade yet because runtime storage still defaults to JSON, admin security is demo-level, quest verification needs stronger rules, and the UX needs onboarding, notifications, and reporting polish.

## Score breakdown

| Area | Score | Notes |
|---|---:|---|
| Product concept | 8.5 | Quest apps distribute merchant-approved Microgifter rewards. |
| API integration model | 8.0 | Issue, status, list, claim, and webhooks are represented. |
| Participant UX | 6.5 | Cover, login, quest board, and wallet exist. |
| Admin UX | 6.0 | Admin backend exists; needs SQL persistence and stronger auth. |
| Data model | 7.0 | SQL schema exists; runtime conversion is still needed. |
| Reward/wallet lifecycle | 7.0 | Wallet and claim reporting exist; redemption UX needs polish. |
| Quest verification | 4.5 | QR/geolocation context exists, but needs trusted verification. |
| Analytics/reporting | 5.5 | Event history exists; admin analytics are basic. |
| Production readiness | 4.5 | Needs SQL runtime, CSRF, migrations, and tests. |

## User history and interactions captured today

The app currently tracks these user interactions in local state and has SQL tables designed for them:

- account registration
- account login/logout
- display name/profile update
- Microgifter account-link started
- Microgifter account-link completed
- sandbox linked-account shortcut
- quest completion
- QR/manual venue code context
- geolocation context
- reward issue request
- reward status refresh
- reward wallet state
- reward claim in Quest app
- claim report to Microgifter
- webhook deliveries in `webhook-events.log`
- app/admin events in the local event log

The admin can view or manage users, wallets, claims, quest definitions, quest controls, and event history.

## Quest controls added

Admins can now control:

- active/inactive state
- featured state
- visibility: public, hidden, invite only
- sponsor/group
- start date/time
- end date/time
- max total completions
- max total rewards

The quest board now filters playable quests through those controls.

## What the app needs next

### 1. SQL runtime storage

The schema exists, but `app.php` still uses `data/state.json` by default. Convert user accounts, link states, quest completions, rewards, claims, and event logs to PDO-backed storage.

### 2. Admin auth hardening

Move admin users to `lqr_admin_users`, enforce password hashing, add CSRF tokens, rate limiting, and session expiration.

### 3. Real quest verification

QR/geolocation context exists, but it is not trusted yet. Add signed QR codes, venue check-in secrets, time windows, and optional merchant confirmation.

### 4. Reward rule builder

The admin can edit quest rules, but it should have a better rule builder: choose Microgifter program/template from API, preview capacity, and validate reward permission before publishing.

### 5. Wallet polish

Add reward images, expiration, merchant redemption instructions, barcode/QR handoff, and clear claim/redeem states.

### 6. User profile and history page

The quest board shows a small history panel, but the user needs a dedicated history screen for completed quests, issued rewards, claims, redemptions, and failed attempts.

### 7. App analytics

Add admin charts for active users, completions, reward issue rate, claim rate, sponsor performance, and quest conversion.

### 8. Webhook reconciliation

Consume Microgifter webhook events into the app state/database so reward state updates automatically instead of relying on manual refresh.

### 9. Sponsor reporting

A third-party quest operator needs reports per sponsor/merchant: completions, rewards issued, claims, cost exposure, and demand driven.

### 10. Test coverage

Add validation for syntax, schema existence, quest controls, wallet claim reporting, webhook verification, and end-to-end sandbox issue/list/claim.

## Recommended next build stage

**Stage LQ-DB — Convert Local Quest runtime to SQL**

Scope:

1. Add `lqr_db()` helper using config storage settings.
2. Add JSON-to-SQL migration script.
3. Replace user registration/login with `lqr_users`.
4. Replace link states with `lqr_link_states`.
5. Replace quest completions with `lqr_quest_completions`.
6. Replace rewards and claims with `lqr_rewards` and `lqr_reward_claims`.
7. Replace event logs with `lqr_events`.
8. Update admin pages to read SQL when storage driver is `mysql`.
