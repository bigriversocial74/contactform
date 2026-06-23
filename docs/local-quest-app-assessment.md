# Local Quest Rewards app assessment

## Current score

**Overall: 7.3 / 10**

The app is now a credible working ecosystem demo with participant accounts, account linking, quest completion, reward issuing, wallet display, claim reporting, admin pages, admin credential management, quest controls, SQL runtime storage support, JSON-to-SQL migration, webhook receiver, and validation coverage.

It is not production-grade yet because admin CSRF/session hardening, trusted quest verification, webhook reconciliation, analytics, role permissions, and end-to-end test automation still need more work.

## Score breakdown

| Area | Score | Notes |
|---|---:|---|
| Product concept | 8.5 | Quest apps distribute merchant-approved Microgifter rewards. |
| API integration model | 8.0 | Issue, status, list, claim, and webhooks are represented. |
| Participant UX | 6.8 | Cover, login, quest board, QR/geolocation, and wallet exist. |
| Admin UX | 6.8 | Admin backend, credential tools, quest controls, and portal UI exist. |
| Data model | 7.8 | SQL schema and runtime bridge exist; direct SQL-first repos/services can still be cleaner. |
| Reward/wallet lifecycle | 7.2 | Wallet and claim reporting exist; redemption UX needs polish. |
| Quest verification | 4.8 | QR/geolocation context exists, but needs trusted verification. |
| Analytics/reporting | 5.8 | Event history exists; admin analytics are basic. |
| Production readiness | 5.5 | SQL runtime is available; still needs CSRF, roles, email recovery, and tests. |

## User history and interactions captured today

The app tracks these interactions through JSON or SQL runtime storage:

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
- admin user creation/status/password events
- recovery token creation/completion events
- webhook deliveries in `webhook-events.log`
- app/admin events in the local event log

The admin can view or manage users, wallets, claims, quest definitions, quest controls, admin credentials, and event history.

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

The quest board filters playable quests through those controls.

## SQL runtime stage completed

`app.php` keeps the same `lqr_load_state()` and `lqr_save_state()` API, but those functions now switch by config:

- `storage.driver = json` uses `data/state.json`
- `storage.driver = mysql` uses `storage-sql.php` and the SQL tables

A migration script exists:

```text
examples/local-quest-rewards/scripts/migrate-json-to-sql.php
```

It reads `data/state.json`, writes the SQL tables, and verifies counts by loading state back from SQL.

## What the app needs next

### 1. CSRF and session hardening

Add CSRF tokens to every admin/user POST, admin session timeout, secure cookie settings, and login throttling.

### 2. Trusted quest verification

QR/geolocation context exists, but it is not trusted yet. Add signed QR codes, venue check-in secrets, time windows, anti-replay checks, and optional merchant confirmation.

### 3. SQL-first service layer

The runtime bridge works, but a cleaner production version should have repository/service functions that query SQL directly instead of translating state arrays.

### 4. Reward rule builder

The admin can edit quest rules, but it should choose Microgifter program/template from API, preview capacity, and validate reward permission before publishing.

### 5. Wallet polish

Add reward images, expiration, merchant redemption instructions, barcode/QR handoff, and clear claim/redeem states.

### 6. User profile and history page

The quest board shows a small history panel, but the user needs a dedicated history screen for completed quests, issued rewards, claims, redemptions, and failed attempts.

### 7. App analytics

Add admin charts for active users, completions, reward issue rate, claim rate, sponsor performance, and quest conversion.

### 8. Webhook reconciliation

Consume Microgifter webhook events into SQL so reward state updates automatically instead of relying on manual refresh.

### 9. Sponsor reporting

A third-party quest operator needs reports per sponsor/merchant: completions, rewards issued, claims, cost exposure, and demand driven.

### 10. End-to-end tests

Add syntax checks, schema checks, migration checks, admin-auth checks, sandbox issue/list/claim tests, and webhook signature verification tests.

## Recommended next build stage

**Stage LQ-Security — CSRF, sessions, role checks, and trusted verification foundations**

Scope:

1. Add CSRF helper and tokens to all POST forms.
2. Add admin/user session timeout and secure cookie recommendations.
3. Add admin role checks for owner-only credential actions.
4. Add signed QR code format and verifier.
5. Add replay protection for QR/claim codes.
6. Add basic login throttling using SQL/app state.
