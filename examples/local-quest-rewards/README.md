# Local Quest Rewards

Local Quest Rewards is a production-oriented starter application for local loyalty, check-in, challenge, event, passport, and sponsored-reward experiences on the Microgifter Public Distribution API.

The application owns participant accounts, quest progress, QR/geolocation evidence, local wallet presentation, administration, and operational reporting. Microgifter remains the system of record for Distribution Program permissions, reward issuance, item status, claim reporting, redemption lifecycle, signed webhooks, and audit history.

## Product flow

1. A participant visits the public landing page.
2. The participant creates or signs into a Local Quest account.
3. The participant connects a Microgifter account through explicit consent.
4. The participant completes a public quest using the required verification method.
5. Local Quest checks schedule, visibility, caps, completion, linking, and reward rules.
6. The approved reward request is sent through the Microgifter Distribution API.
7. The participant follows the reward in the Local Quest wallet.
8. Claim activity reports back to Microgifter.
9. Signed webhooks reconcile lifecycle changes into Local Quest SQL storage.

## Runtime requirements

- PHP 8.2 or newer
- PDO MySQL extension
- MySQL or MariaDB
- cURL or `allow_url_fopen` for Microgifter API requests
- HTTPS for live mode
- A Microgifter Developer API credential
- An approved Distribution Program and reward template

Local Quest is SQL-only. JSON or file-backed application state is not supported.

## Installation

Upload this folder so `install.php` is reachable from the intended application host, then open:

```text
https://quest.example.com/install.php
```

The production installer uses `install-functions.php` and:

- checks PHP, PDO, HTTP-client, and folder readiness
- validates the Microgifter Developer API credential before finalizing setup
- creates the database when permitted or uses an existing database
- safely parses and applies all required SQL schemas
- verifies all 16 required tables
- creates the first owner account
- generates a signed-code secret when one is not supplied
- writes `config.php` atomically and backs up an existing configuration
- records the schema version
- writes `.installed.lock`
- blocks public reruns unless `.install-unlock` is intentionally created

Schema order:

```text
database/local_quest_rewards.sql
database/local_quest_admin_auth.sql
database/local_quest_production_foundation_v1.sql
database/local_quest_participant_auth_v1.sql
```

After installation:

1. Confirm `.installed.lock` exists.
2. Confirm `.install-unlock` does not exist.
3. Remove or server-protect `install.php` on live deployments.
4. Open `runtime-diagnostics.php`.
5. Sign into `admin-credentials.php` with the owner account.
6. Configure the webhook callback as `<app_public_url>/webhook.php`.
7. Run the launch console at `start.php`.

## Configuration

`config.example.php` documents the SQL-only configuration contract. The installer creates `config.php` with:

- application name and public URL
- Microgifter base URL and Developer API key
- default Distribution Program and reward template IDs
- webhook signing value
- test/live mode
- sandbox-shortcut policy
- session, CSRF, and signed-code settings
- SQL driver, DSN, username, and password
- installation schema version

Runtime configuration files are ignored by Git:

```text
config.php
config.php.bak-*
.installed.lock
.install-unlock
```

## Public participant experience

Primary public pages:

```text
cover.php          Professional public landing page
signin.php         Participant registration and sign-in
index.php          Authenticated quest board
wallet.php         Connected reward wallet
history.php        Participant quest and reward history
link-callback.php  Microgifter account-link callback
```

The landing page includes responsive navigation, product positioning, dynamic featured public quests, lifecycle explanation, wallet preview, partner positioning, SEO metadata, structured data, and automatic installation routing when `config.php` is missing.

## Quest controls

Quest definitions currently live in `quests.php`. Controls include:

- active/inactive status
- public, hidden, or invite-only visibility
- featured status
- sponsor and location
- start and end dates
- maximum completions and rewards
- signed-code requirement and code type
- linked-account and per-user reward rules

Only public quests appear in participant-facing lists. Hidden and invite-only quests are excluded until an explicit access model is implemented.

## Administration

Administrative pages include:

```text
admin.php
admin-portal.php
admin-quest-controls.php
admin-credentials.php
admin-password-reset.php
admin-signed-codes.php
admin-programs.php
admin-ledger.php
app-console-admin.php
admin-demo-tools.php
admin-developer-readiness.php
```

Roles are Owner, Admin, Quest Manager, Support, and Sponsor Viewer. Owner-only controls include administrator creation, status changes, recovery-link creation, and owner-level access management. The final active owner cannot be disabled.

Administrative and participant sessions are separate. Successful authentication regenerates the session identifier, and repeated failed administrator sign-ins trigger a temporary session-level lockout.

## Webhooks

`webhook.php` accepts signed Microgifter POST deliveries and provides an authenticated administrative status page for GET requests.

Webhook behavior:

- validates signature version `v1`
- validates HMAC SHA-256 over `<timestamp>.<raw body>`
- enforces a five-minute timestamp window
- uses `X-Microgifter-Delivery` as the idempotency key
- prevents duplicate delivery reconciliation
- stores verified and rejected deliveries in `lqr_webhook_deliveries`
- reconciles matching reward and item lifecycle changes into the local wallet
- does not expose a public raw log file

Reusable SQL webhook helpers live in `webhook-storage.php`.

## Security foundation

The application includes HTTP-only and SameSite cookies, HTTPS-aware secure cookies, session idle timeout, session-ID regeneration, automatic CSRF protection, signed QR/code payloads, SQL-backed replay protection, installer lockdown, atomic configuration writes, owner-only credential management, and private database-backed webhook evidence.

## Developer and QA tools

```text
start.php                       Guided launch console
runtime-diagnostics.php         Read-only production diagnostics
developer-starter.php           API setup and integration portal
api-examples.php                Copy-ready API examples
webhook-tools.php               Signed local webhook test generator
admin-demo-tools.php            Admin-only deterministic demo seed/reset
admin-developer-readiness.php   Operational launch evidence
```

## Validation

The repository workflow `.github/workflows/local-quest-checks.yml` runs PHP 8.2 and 8.3 syntax checks, existing Local Quest regressions, signed QR and replay validation, and the production installer/landing/access/webhook/visibility contract.

The production contract validator is:

```text
scripts/validate_local_quest_production_foundation_v1.php
```

Static validation does not replace browser, database, API, webhook, or end-to-end deployment testing.

## Existing installation upgrade

For an existing Local Quest database, import:

```text
database/local_quest_production_foundation_v1.sql
database/local_quest_participant_auth_v1.sql
```

Then update the application files, confirm `config.php` uses `storage.driver => mysql`, and create `.installed.lock` through an intentional maintenance window after verifying configuration.

## Deployment boundary

Microgifter still decides Developer API scope, Distribution Program access, template approval, linked-account validity, reward capacity, idempotency, ownership, item state, claims, redemption truth, webhook authority, and audit history. Local Quest must never issue rewards by directly writing Microgifter platform tables.
