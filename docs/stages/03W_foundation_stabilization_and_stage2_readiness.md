# 03W Microgifter Foundation Stabilization and Stage 2 Readiness

## Purpose

This pass locks the current Stage 1 foundation before real Stage 2 product, gift, claim, order, inbox, and agent data modules are expanded.

The goal is to keep the build disciplined:

- PHP-only public runtime.
- HostGator-compatible staging mode.
- AWS-ready future architecture.
- Universal header/footer source of truth.
- Working auth/session/account baseline.
- Stable database/import path.
- Stage 2 guardrails before feature tables are added.

## Current foundation status

### Runtime

Approved current runtime:

```text
PHP + MySQL-compatible database + HostGator/cPanel-compatible Apache
```

Future production direction:

```text
AWS + Aurora MySQL-compatible + S3 + CloudFront + ElastiCache/Valkey + SQS/EventBridge when needed
```

### Public pages

Active public pages are PHP-only:

```text
/
/index.php
/build.php
/agent.php
/signup.php
/signin.php
/account.php
/api/health.php
```

Dead prototype routes must remain non-existent:

```text
/index.html
/build.html
/builder.html
/agent.html
/signin.html
/signup.html
```

Expected response for old prototype routes: `410 Gone` or equivalent.

### Auth/session behavior

Required behavior:

- Logged-out users can access `/`, `/index.php`, `/signup.php`, and `/signin.php`.
- Logged-in users visiting `/index.php` are redirected to `/agent.php`.
- Signup creates a session and forwards into the authenticated app flow.
- Signin creates a session and forwards into the authenticated app flow.
- Logout revokes the active session and forwards to `/index.php`.
- `/api/health.php` returns JSON and must not expose secrets.

### Shared UI behavior

The header and footer must be universal:

- Same lightning logo mark.
- Same nav font, sizing, spacing, and alignment.
- Same account dropdown behavior across pages.
- Logged-out header shows account dropdown only, not separate signup/create-gift buttons.
- Main nav sits left beside the logo.
- Header is full-width and visually consistent with the agent workspace.
- Footer is shared and ready for later expansion.

## Stage 2 readiness gate

Do not start Stage 2 feature implementation until these checks pass on HostGator:

```text
/api/health.php returns database connected
/signup.php creates a test user
/signin.php logs in the test user
/account.php loads while signed in
/logout from account/header returns to /index.php
/index.php redirects signed-in users to /agent.php
/agent.php loads while signed in
/build.php loads while signed in
old .html URLs return 410 Gone
universal header/footer are visually consistent across active pages
```

## Stage 2 guardrails

Before creating real Stage 2 tables or endpoints, every module must define:

- Owner/scope key: `account_id`, `store_id`, or `owner_user_id`.
- Public identifier strategy.
- Object-level authorization rule.
- Delivery event mapping.
- Idempotency requirements.
- Outbox requirements.
- Hot-read query and index plan.
- HostGator-compatible behavior.
- AWS-enhanced behavior, if different.

## Feature modules blocked until scoped

The following modules must not be casually added without the guardrails above:

```text
products
gift offers
vouchers
claims
orders
payments
inbox messages
agent records
merchant/store records
PPPM/workplace reward records
```

## Practical next step

After this pass, the next recommended implementation stage is:

```text
04A_microgifter_product_and_gift_schema_design
```

That pass should design the first real commerce/gifting tables using the high-volume foundation, delivery events, outbox, idempotency, and object-level authorization rules already created in Stage 1.
