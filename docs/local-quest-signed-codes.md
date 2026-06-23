# Local Quest signed quest codes

## Purpose

Quest completion cannot rely on freeform text codes forever. Protected quests need signed, expiring, anti-replay check-in payloads that can be printed as QR codes or shown by venues/sponsors.

## Files

```text
examples/local-quest-rewards/admin-signed-codes.php
examples/local-quest-rewards/signed-quest-enforcement.php
examples/local-quest-rewards/security.php
examples/local-quest-rewards/quest-controls.php
examples/local-quest-rewards/index.php
```

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

## Enforcement controls

Quest controls now include:

```text
requires_signed_code
signed_code_type
```

Default values:

```text
requires_signed_code = false
signed_code_type = quest_checkin
```

When `requires_signed_code` is enabled, the participant quest flow calls:

```php
lqr_enforce_signed_quest_code($config, $state, $questId, $quest, $qrPayload);
```

## Enforcement behavior

For protected quests, the completion flow now:

1. reads the QR payload
2. verifies the signature with `lqr_verify_signed_payload()`
3. verifies expected type, such as `quest_checkin`
4. verifies matching `quest_id`
5. rejects expired payloads
6. rejects replayed payloads with `lqr_replay_seen()`
7. marks accepted payloads with `lqr_mark_replay()`
8. rejects empty/freeform/manual codes that are not signed payloads
9. logs `quest.signed_code_verified`
10. records signed payload context in the last scan record

The quest board also shows a **Signed QR required** badge for protected quests.

## Remaining production work

1. Add signed-code controls to the admin quest controls UI.
2. Store replay keys directly in SQL instead of translated app state.
3. Add a printable QR rendering page for generated signed payloads.
4. Add signed-code fixture tests.
5. Add venue/sponsor rotation support for generated QR codes.
