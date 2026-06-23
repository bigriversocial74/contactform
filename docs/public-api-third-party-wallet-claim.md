# Third-party reward wallet and claim contract

Microgifter should allow approved third-party apps to show, claim, and report reward activity while Microgifter remains the system of record.

## Product model

A Microgifter merchant approves products/templates for Distribution Programs. Multiple external apps can request those approved rewards for their own user actions.

```text
Merchant product/template
  -> Distribution Program
  -> Developer app access
  -> Third-party quest/action
  -> Reward issue
  -> Third-party reward wallet
  -> Claim/report
  -> Microgifter ownership/redemption lifecycle
```

## User experience goal

The Quest app should feel complete:

- user signs into the Quest app
- user completes quests
- user sees rewards inside the Quest app
- user can claim or start claim from inside the Quest app
- claim activity reports back to Microgifter
- merchant claim/redemption truth stays in Microgifter

## Current API coverage

Already present:

- `POST /api/public/v1/rewards/issue.php`
- `GET /api/public/v1/rewards/status.php?id=<reward_id>`

The status endpoint can return issued item IDs and item status after issuance jobs complete.

Missing public app-facing endpoints:

- `GET /api/public/v1/rewards/list.php?linked_account_id=<linked_account_id>`
- `POST /api/public/v1/rewards/claim.php`

## Reward list endpoint

A third-party app can maintain its own wallet from issue/status responses, but a platform list endpoint would make this cleaner.

```text
GET /api/public/v1/rewards/list.php?linked_account_id=<linked_account_id>
```

Expected response shape:

```json
{
  "ok": true,
  "rewards": [
    {
      "reward_id": "reward_...",
      "program_id": "dist_prog_...",
      "template_id": "tmpl_...",
      "external_event_id": "quest-123",
      "event_type": "quest.completed",
      "status": "issued",
      "item_id": "item_...",
      "item_status": "delivered",
      "title": "$5 Coffee Microgift",
      "issued_at": "2026-06-23T00:00:00Z"
    }
  ]
}
```

## Claim/report endpoint

A third-party app should be able to report that a user viewed, claimed, opened, or started redemption for a reward.

```text
POST /api/public/v1/rewards/claim.php
```

Payload:

```json
{
  "reward_id": "reward_...",
  "item_id": "item_...",
  "linked_account_id": "linked_...",
  "external_user_id": "lqr_...",
  "external_claim_id": "local-quest-claim-001",
  "claim_action": "claimed_in_app",
  "metadata": {
    "app": "local-quest-rewards",
    "quest_id": "downtown-coffee-checkin"
  }
}
```

Expected response shape:

```json
{
  "ok": true,
  "reward_id": "reward_...",
  "item_id": "item_...",
  "claim_status": "claimed_in_app",
  "microgifter_event_id": "evt_..."
}
```

## Claim actions

Suggested stable actions:

- `viewed_in_app`
- `claimed_in_app`
- `redeem_started`
- `redeem_handoff`
- `claim_cancelled`

## Microgifter responsibilities

Microgifter remains responsible for product/template approval, Distribution Program access, app credential authorization, linked account ownership, reward issuance, item ownership, claim/redeem truth, merchant redemption truth, webhook delivery, and audit trail.

## Third-party app responsibilities

The Quest app is responsible for app login, quest progress, local action completion, reward wallet display, calling Microgifter status/claim endpoints, showing user-friendly reward state, and staying inside merchant-approved reward permissions.

## Demo state

`examples/local-quest-rewards/wallet.php` now displays rewards issued through the Quest app and lets users mark a reward as claimed inside the Quest app. The claim action records local state and marks reporting as `pending_microgifter_claim_api` until the public claim/report endpoint is implemented.
