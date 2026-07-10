# Stage 887 — Training Lab Signed Launch v1

## Purpose

Stage 887 completes the Microgifter half of the shared-account integration. An authenticated Microgifter user can open Training Lab through a short-lived HMAC-SHA256 identity assertion generated entirely from the trusted server session.

No password or password hash is copied, exposed, or sent to Training Lab.

## Routes

- Account launch page: `/training-lab.php`
- Protected POST endpoint: `/api/training-lab/launch.php`
- Training Lab receiver: `https://labs.microgifter.com/account-link.php`

The launch endpoint requires:

- POST
- a valid Microgifter CSRF token
- a current DB-backed authenticated session
- the Stage 887 feature flag and shared secret
- the existing database-backed rate limiter

## Required Microgifter server configuration

Set these values in the Microgifter server environment or ignored local configuration:

```text
TL_IDENTITY_LAUNCH_ENABLED=true
TL_IDENTITY_SHARED_SECRET=<minimum 32-character random shared secret>
TL_IDENTITY_ISSUER=microgifter.com
TL_IDENTITY_AUDIENCE=training-lab
TL_IDENTITY_TARGET_URL=https://labs.microgifter.com/account-link.php
TL_IDENTITY_LAUNCH_TTL=120
```

Use the exact same `TL_IDENTITY_SHARED_SECRET`, issuer, and audience on the deployed Training Lab server.

The launch TTL is clamped to 60–180 seconds.

## Signed assertion

Header:

```json
{"alg":"HS256","typ":"TL-ID"}
```

Claims:

- `iss` — configured Microgifter issuer
- `aud` — configured Training Lab audience
- `sub` — authenticated Microgifter user ID
- `email` — authenticated Microgifter email
- `name` — authenticated display name
- `role` — normalized Training Lab role
- `merchant` — server-derived merchant workspace public ID when available
- `organization` — server-derived merchant workspace display name when available
- `iat` — issued timestamp
- `exp` — expiration timestamp
- `nonce` — cryptographically random single-use nonce
- `jti` — cryptographically random token ID

The assertion is submitted by POST as `identity_assertion`. It is not placed in a query string.

## Role mapping

- Microgifter `super_admin`, `admin`, or `platform_admin` → Training Lab `admin`
- Microgifter `reviewer` or `proof_reviewer` → Training Lab `reviewer`
- Microgifter `coach`, `trainer`, or `mentor` → Training Lab `coach`
- Microgifter merchant roles or trusted merchant-management permissions → Training Lab `manager`
- Unknown or customer roles → Training Lab `participant`

Browser input cannot select or elevate the mapped role.

## Security boundaries

- No passwords or password hashes in the assertion
- No browser-supplied user ID, email, role, merchant, or organization fields
- HTTPS receiver required
- Receiver path must end in `/account-link.php`
- Credential-bearing or fragment-bearing target URLs rejected
- CSRF and refreshed DB-backed Microgifter session required
- Rate limited per authenticated user
- No payments, wallet changes, claims, redemptions, reward issuing, or destructive synchronization

## SQL

No new Microgifter SQL is required for Stage 887.

The separate Training Lab migration remains pending until David imports it:

```text
database/stage886_shared_account_integration_v1.sql
```

That Training Lab migration creates `training_account_links` and `training_auth_nonces`. The live handoff cannot persist account links or reject nonce replays until that migration is imported.

## Validation

Run:

```bash
php scripts/validate_training_lab_signed_launch_v1.php
```

GitHub Actions runs the validator and syntax checks on PHP 8.2 and PHP 8.3.
