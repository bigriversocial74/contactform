# Stage API-8 — Sandbox/test mode developer flow

Stage API-8 adds a test-mode sandbox path for developer apps and credentials.

## Mode detection

Public API credentials now expose mode through `mg_public_context()`:

- `sandbox_mode: true` when the developer app or API key environment is `test`
- `sandbox_mode: false` only when both the app and API key are `live`

Every authenticated response includes:

```http
X-Microgifter-Environment: test
```

or:

```http
X-Microgifter-Environment: live
```

## Prepare a sandbox linked account

Endpoint:

```http
POST /api/public/v1/sandbox/linked-account.php
```

Required scope: `distribution:rewards.issue`

This endpoint only works with test-mode credentials. It returns a deterministic sandbox linked account ID for an external user ID.

```bash
curl -s "$MG_BASE_URL/api/public/v1/sandbox/linked-account.php" \
  -X POST \
  -H "Authorization: Bearer $MG_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"external_user_id":"player-9001"}'
```

Example response:

```json
{
  "ok": true,
  "message": "Sandbox linked account prepared.",
  "sandbox": true,
  "linked_account_id": "sandbox_linked_...",
  "external_user_id": "player-9001",
  "status": "active"
}
```

## Issue a sandbox reward

Use the standard reward issue endpoint with a test-mode credential and the sandbox linked account ID.

```bash
curl -s "$MG_BASE_URL/api/public/v1/rewards/issue.php" \
  -X POST \
  -H "Authorization: Bearer $MG_API_KEY" \
  -H "Content-Type: application/json" \
  -H "X-Idempotency-Key: sandbox-achievement-1001" \
  -d '{
    "program_id": "sandbox_program",
    "external_event_id": "sandbox-achievement-1001",
    "event_type": "achievement_reward",
    "recipient": {"linked_account_id": "sandbox_linked_..."},
    "reward": {"template_id": "sandbox_template", "quantity": 1},
    "metadata": {"sandbox": true}
  }'
```

Example response:

```json
{
  "ok": true,
  "message": "Sandbox reward delivered.",
  "sandbox": true,
  "reward_id": "sandbox_reward_...",
  "status": "sandbox_delivered",
  "event_id": "sandbox_event_...",
  "program_id": "sandbox_program",
  "template_id": "sandbox_template",
  "quantity": 1,
  "pppm_item_id": "sandbox_item_..."
}
```

Sandbox rewards are persisted to `public_api_sandbox_rewards`. They do not reserve merchant funds, consume Distribution Program capacity, create real distribution allocations, create PPPM items, or require a real Microgifter user link.

## Read sandbox reward status

Use the standard reward status endpoint:

```bash
curl -s "$MG_BASE_URL/api/public/v1/rewards/status.php?id=sandbox_reward_..." \
  -H "Authorization: Bearer $MG_API_KEY"
```

Sandbox status returns the same shape as live reward status, plus `sandbox: true`.

## Webhooks

Sandbox reward issue queues lifecycle webhooks with `sandbox: true` in the payload data:

- `reward.queued`
- `reward.delivered`

The webhook worker delivery mechanics remain the same as live mode.

## Live-mode protection

`/api/public/v1/sandbox/linked-account.php` rejects live-mode credentials with HTTP `403`.

Live credentials using the normal reward issue endpoint continue through the live allocation and issuance path.
