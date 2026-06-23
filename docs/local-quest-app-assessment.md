# Local Quest Rewards app assessment

## Current score

**Overall: 7.5 / 10**

The app is now a credible starter-app foundation with participant accounts, account linking, quest completion, reward issuing, wallet display, claim reporting, admin pages, admin credential management, quest controls, SQL-only runtime storage, security helpers, webhook receiver, and validation coverage.

It is not production-grade yet because trusted quest verification, webhook reconciliation, analytics, role permissions, installer flow, and end-to-end test automation still need more work.

## Score breakdown

| Area | Score | Notes |
|---|---:|---|
| Product concept | 8.5 | Quest apps distribute merchant-approved Microgifter rewards. |
| API integration model | 8.0 | Issue, status, list, claim, and webhooks are represented. |
| Participant UX | 6.8 | Cover, login, quest board, QR/geolocation, and wallet exist. |
| Admin UX | 6.8 | Admin backend, credential tools, quest controls, and portal UI exist. |
| Data model | 8.1 | Runtime is now SQL-only; service/repository cleanup is still needed. |
| Reward/wallet lifecycle | 7.2 | Wallet and claim reporting exist; redemption UX needs polish. |
| Quest verification | 5.0 | Signed-code foundation exists, but protected quests do not enforce it yet. |
| Analytics/reporting | 5.8 | Event history exists; admin analytics are basic. |
| Production readiness | 5.8 | SQL-only runtime and CSRF/session helpers exist; still needs roles, installer, tests. |

## User history and interactions captured today

The app tracks these interactions through SQL runtime storage:

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
- app/admin events in the SQL event log

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

## SQL-only runtime stage completed

`app.php` keeps the same `lqr_load_state()` and `lqr_save_state()` API for the current app layer, but those functions now always use SQL through `storage-sql.php`.

Removed from the starter foundation:

- `data/state.json` runtime
- JSON demo mode
- JSON-to-SQL migration script
- documentation that treated JSON storage as a supported mode

Still allowed:

- SQL `JSON` columns for metadata, API responses, webhook context, claim context, QR/geolocation evidence, and audit payloads

## Security stage completed

The security helper now provides:

- hardened session boot
- HTTP-only session cookies
- SameSite=Lax cookies
- HTTPS-aware secure cookie flag
- session idle timeout
- CSRF token generation
- automatic hidden CSRF token injection into POST forms
- POST CSRF verification before page actions run
- signed QR/code helper foundation
- signed payload expiration
- replay-key helper foundation

## What the app needs next

### 1. GitHub Actions checks

Add a workflow that runs PHP syntax checks and the Local Quest validation script on every pull request.

### 2. Installer/setup flow

Add `/install.php` or a CLI setup script for database checks, schema checks, first admin creation, Microgifter API key test, program/template selection, and webhook test.

### 3. Trusted quest verification

QR/geolocation context exists, but protected quests should require signed QR codes, venue check-in secrets, time windows, anti-replay checks, and optional merchant confirmation.

### 4. SQL-first service layer

The SQL-only runtime still translates through state arrays. A cleaner production foundation should have repository/service functions that query SQL directly.

### 5. Reward rule builder

The admin can edit quest rules, but it should choose Microgifter program/template from API, preview capacity, and validate reward permission before publishing.

### 6. Wallet polish

Add reward images, expiration, merchant redemption instructions, barcode/QR handoff, and clear claim/redeem states.

### 7. User profile and history page

The quest board shows a small history panel, but the user needs a dedicated history screen for completed quests, issued rewards, claims, redemptions, and failed attempts.

### 8. App analytics

Add admin charts for active users, completions, reward issue rate, claim rate, sponsor performance, and quest conversion.

### 9. Webhook reconciliation

Consume Microgifter webhook events into SQL so reward state updates automatically instead of relying on manual refresh.

### 10. Sponsor reporting

A third-party quest operator needs reports per sponsor/merchant: completions, rewards issued, claims, cost exposure, and demand driven.

## Recommended next build stage

**Stage SAF-CI — GitHub Actions and starter validation**

Scope:

1. Add GitHub Actions workflow for PR checks.
2. Run PHP syntax checks against Quest app PHP files.
3. Run `scripts/validate_local_quest_rewards_demo.php`.
4. Fail if JSON runtime files return.
5. Fail if required SQL/security/admin files are missing.
6. Document check behavior in the README.
