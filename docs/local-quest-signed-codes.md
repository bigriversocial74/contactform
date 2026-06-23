# Local Quest signed quest codes

## Purpose

Quest completion cannot rely on freeform text codes forever. Protected quests need signed, expiring, anti-replay check-in payloads that can be printed as QR codes or shown by venues/sponsors.

## Generator

The admin generator lives at:

```text
examples/local-quest-rewards/admin-signed-codes.php
```

It creates signed payloads using the security helper:

```text
lqr_signed_payload()
lqr_verify_signed_payload()
lqr_mark_replay()
lqr_replay_seen()
```

## Signed payload format

```text
lqr1.<base64url-json-payload>.<hmac-sha256-signature>
```

Example payload before signing:

```json
{
  "type": "quest_checkin",
  "quest_id": "downtown-coffee-checkin",
  "venue_id": "demo_venue",
  "issued_by_admin": "admin_123",
  "iat": 1780000000,
  "nonce": "random"
}
```

## Supported code types

Current generator options:

```text
quest_checkin
reward_claim
venue_proof
```

## Intended production enforcement

Next enforcement pass should add a quest control such as:

```text
requires_signed_code = true
```

When enabled, the participant quest flow should:

1. read the QR payload
2. verify signature with `lqr_verify_signed_payload()`
3. verify expected type, such as `quest_checkin`
4. verify matching `quest_id`
5. reject expired payloads
6. reject replayed payloads with `lqr_replay_seen()`
7. mark accepted payloads with `lqr_mark_replay()`
8. reject freeform/manual codes for protected quests

## Current scope

The signed-code generator and verifier foundation exist. Protected quests are not fully enforced yet.
