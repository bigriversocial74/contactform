# Stage API-6 — Public docs polish and real examples

This guide gives developers copy/paste examples for the Microgifter Public Distribution API.

## Environment variables

```bash
export MG_BASE_URL="https://microgifter.com"
export MG_API_KEY="mg_test_replace_with_server_side_key"
```

API credentials are server-side secrets. Do not embed them in browser JavaScript or mobile clients.

## Authentication headers

```http
Authorization: Bearer mg_test_replace_with_server_side_key
Content-Type: application/json
X-Request-ID: req_20260622_0001
X-Idempotency-Key: achievement-1001
```

## 1. List merchant programs

Required scope: `distribution:programs.read`

```bash
curl -s "$MG_BASE_URL/api/public/v1/programs/index.php" \
  -H "Authorization: Bearer $MG_API_KEY"
```

Example response:

```json
{
  "ok": true,
  "programs": [
    {
      "public_id": "dist_prog_7e3c2f",
      "name": "Subscriber welcome rewards",
      "program_type": "api",
      "status": "active",
      "product_count": 3
    }
  ]
}
```

## 2. Start account linking

Required scope: `distribution:rewards.issue`

The app starts a link request with its own stable external user ID. Microgifter returns a link URL that the user opens to approve the connection.

```bash
curl -s "$MG_BASE_URL/api/public/v1/account-link-start.php" \
  -X POST \
  -H "Authorization: Bearer $MG_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "external_user_id": "player-9001",
    "return_url": "https://example.app/rewards/connected",
    "state": "checkout-session-42",
    "metadata": {
      "plan": "founder",
      "source": "launch-campaign"
    }
  }'
```

Example response:

```json
{
  "ok": true,
  "message": "Account link started.",
  "link_request_id": "a7e9d5f2-0ed3-4f2d-a3fd-1d70853b8510",
  "link_url": "https://microgifter.com/account-link.php?code=...",
  "expires_at": "2026-06-22 01:30:00",
  "external_user_id": "player-9001"
}
```

After approval, Microgifter redirects to the supplied return URL:

```text
https://example.app/rewards/connected?status=linked&linked_account_id=linked_abc123&external_user_id=player-9001&state=checkout-session-42
```

Store `linked_account_id` on your backend and use it when issuing rewards.

## 3. Issue a reward

Required scope: `distribution:rewards.issue`

```bash
curl -s "$MG_BASE_URL/api/public/v1/rewards/issue.php" \
  -X POST \
  -H "Authorization: Bearer $MG_API_KEY" \
  -H "Content-Type: application/json" \
  -H "X-Request-ID: req_achievement_1001" \
  -H "X-Idempotency-Key: achievement-1001" \
  -d '{
    "program_id": "dist_prog_7e3c2f",
    "external_event_id": "achievement-1001",
    "event_type": "achievement_reward",
    "recipient": {
      "linked_account_id": "linked_abc123"
    },
    "reward": {
      "template_id": "tmpl_pizza_25",
      "quantity": 1
    },
    "metadata": {
      "level": 5,
      "campaign": "launch-week"
    }
  }'
```

Example response:

```json
{
  "ok": true,
  "message": "Reward queued for issuance.",
  "reward_id": "reward_38ca2f",
  "status": "queued",
  "event_id": "event_596b01",
  "program_id": "dist_prog_7e3c2f",
  "template_id": "tmpl_pizza_25",
  "quantity": 1
}
```

## 4. Read reward status

Required scope: `distribution:rewards.status`

```bash
curl -s "$MG_BASE_URL/api/public/v1/rewards/status.php?id=reward_38ca2f" \
  -H "Authorization: Bearer $MG_API_KEY"
```

Example response after issuance:

```json
{
  "ok": true,
  "reward": {
    "reward_id": "reward_38ca2f",
    "status": "issued",
    "program_id": "dist_prog_7e3c2f",
    "template_id": "tmpl_pizza_25",
    "product_title": "Large pizza reward",
    "job_count": 1,
    "queued_jobs": 0,
    "issued_jobs": 1,
    "failed_jobs": 0,
    "jobs": [
      {
        "job_id": "job_4e15d1",
        "item_sequence": 1,
        "job_status": "issued",
        "pppm_item_id": "pppm_91f8d2",
        "pppm_item_status": "delivered",
        "failure_message": null
      }
    ]
  }
}
```

## 5. Webhook receiver example

Webhook requests include delivery headers:

```http
X-Microgifter-Event: reward.delivered
X-Microgifter-Delivery: 329dbf4c-5bb8-4d24-9676-313934d72a19
X-Microgifter-Timestamp: 1782092400
X-Microgifter-Signature: sha256=...
```

Example payload:

```json
{
  "id": "evt_8bbf2c",
  "type": "reward.delivered",
  "created_at": "2026-06-22T01:10:00+00:00",
  "app_id": "app_1a2b3c",
  "data": {
    "reward_id": "reward_38ca2f",
    "job_id": "job_4e15d1",
    "pppm_item_id": "pppm_91f8d2",
    "delivery_id": "329dbf4c-5bb8-4d24-9676-313934d72a19",
    "program_id": "dist_prog_7e3c2f",
    "template_id": "tmpl_pizza_25"
  }
}
```

PHP receiver sketch:

```php
<?php
$payload = file_get_contents('php://input') ?: '';
$event = $_SERVER['HTTP_X_MICROGIFTER_EVENT'] ?? '';
$delivery = $_SERVER['HTTP_X_MICROGIFTER_DELIVERY'] ?? '';
$timestamp = $_SERVER['HTTP_X_MICROGIFTER_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_MICROGIFTER_SIGNATURE'] ?? '';

// TODO: verify signature with the merchant app webhook secret when secret rotation/display is finalized.
$data = json_decode($payload, true);
if (!is_array($data) || $event === '' || $delivery === '') {
    http_response_code(400);
    echo 'invalid webhook';
    exit;
}

// Idempotency: store $delivery or $data['id'] and ignore duplicates.
http_response_code(204);
```

## Events

| Event | Meaning |
| --- | --- |
| `account_link.started` | A link request was created. |
| `account_link.approved` | The user approved the account link. |
| `account_link.cancelled` | The user cancelled the account link. |
| `account_link.expired` | The account link expired. |
| `reward.queued` | The reward request was accepted and queued. |
| `reward.issued` | The worker created a Microgifter item. |
| `reward.delivered` | The item was delivered to the Microgifter INBOX. |
| `reward.failed` | The reward issuance job failed or dead-lettered. |
| `webhook.test` | Merchant-triggered test webhook. |

## Error handling

| Status | Meaning |
| --- | --- |
| `401` | Missing, invalid, expired, or revoked API credential. |
| `403` | Credential lacks the required scope, or return URL origin is not allowed. |
| `404` | Program, product template, linked account, or reward was not found. |
| `409` | Program inactive, capacity exceeded, product limit reached, or recipient limit reached. |
| `422` | Request payload is missing or invalid. |

Use `X-Idempotency-Key` when issuing rewards. A duplicate request returns the existing reward allocation instead of creating another reward.
