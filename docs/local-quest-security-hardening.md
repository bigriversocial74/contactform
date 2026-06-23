# Local Quest security hardening

Branch:

```text
lq-security-csrf-session-hardening
```

## What this stage adds

Local Quest now has a shared security helper:

```text
examples/local-quest-rewards/security.php
```

It provides:

- hardened session boot helper
- HTTP-only session cookies
- SameSite=Lax session cookies
- secure cookie flag when HTTPS is active
- session idle timeout
- CSRF token generation
- automatic hidden CSRF token injection into POST forms
- POST CSRF verification from the global app bootstrap
- signed QR / claim-code payload helpers
- signed payload expiration
- replay-key helpers for signed codes

## App bootstrap wiring

`examples/local-quest-rewards/app.php` now requires the security helper before the app functions load. It boots the secure session, starts automatic CSRF output injection, and blocks POST requests with missing or expired tokens before app actions run.

## Signed QR / code foundation

The signed code format is:

```text
lqr1.<base64url-json-payload>.<hmac-sha256-signature>
```

Payloads can include:

```json
{
  "type": "quest_checkin",
  "quest_id": "downtown-coffee-checkin",
  "venue_id": "demo-coffee",
  "iat": 1780000000,
  "nonce": "random"
}
```

The helper verifies:

- format
- HMAC signature
- payload type when expected
- age/TTL

Replay protection helpers are present so the app can mark a signed code as used.

## SQL-only runtime note

The starter foundation no longer supports file-backed JSON runtime state. App state is SQL-backed through `storage-sql.php`. SQL `JSON` columns remain valid for metadata, API responses, webhook context, claim context, QR/geolocation evidence, and audit payloads.

## Current scope

This stage wires CSRF/session protection globally and adds the signed-code foundation. It does not yet replace every QR/manual code flow with signed-code-only behavior. Manual code input still exists for demo usability.

## Remaining security work

Before production use:

1. Add signed QR generation for admins/venues.
2. Require signed QR payloads for protected quests.
3. Store replay keys in SQL rather than translated app state.
4. Add role-specific owner/admin permission checks.
5. Add login throttling and lockouts to admin and participant login.
6. Add CSRF-specific tests.
7. Add end-to-end POST tests to confirm CSRF protection does not break normal app flow.
8. Add email-based admin recovery delivery instead of displaying recovery links.
