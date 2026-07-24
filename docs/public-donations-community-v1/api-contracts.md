# Public Donations + Community Role v1 — API Contracts

## 1. General rules

All merchant write endpoints require:

- active authenticated user
- merchant access
- appropriate permission
- CSRF token
- same-origin credentials
- rate limiting
- campaign ownership
- feature-state eligibility
- JSON request body
- deterministic validation errors
- audit and security logging

All public endpoints return only privacy-safe data.

Standard success envelope:

```json
{
  "ok": true,
  "message": "Completed.",
  "data": {}
}
```

Standard error envelope:

```json
{
  "ok": false,
  "message": "Request could not be completed.",
  "errors": {}
}
```

Suggested status codes:

- `400` malformed payload
- `401` authentication required
- `403` permission/ownership/feature restriction
- `404` campaign, assignment, user, batch, or reward not found
- `409` idempotency conflict, duplicate state, inventory race, invalid lifecycle transition
- `419` CSRF failure
- `422` field validation or business-rule failure
- `429` rate limit
- `500` unexpected failure

## 2. Public profile Community badge

### `GET /api/public/profile-role-badges.php`

Query:

```text
slug=<public-profile-slug>
```

Response:

```json
{
  "ok": true,
  "data": {
    "roles": ["customer", "community"],
    "badges": [
      {"key": "community", "label": "Community", "icon": "star"}
    ]
  }
}
```

Rules:

- profile must satisfy existing public/unlisted visibility and active-status rules
- inactive or unavailable profiles return `404`
- no private role metadata is returned

## 3. Community search

### `GET /api/merchant/public-donations/community-search.php`

Query:

```text
campaign_id=<campaign-public-id>
q=<search-text>
limit=20
cursor=<opaque-cursor>
```

Validation:

- query minimum two characters for free search
- default limit 20; maximum 50
- campaign belongs to current merchant
- campaign type is `public_donation`

Response:

```json
{
  "ok": true,
  "data": {
    "items": [
      {
        "user_id": 245,
        "display_name": "Phoenix Youth Network",
        "username": "phoenix-youth",
        "avatar_url": "/uploads/profile.jpg",
        "location": "Phoenix, Arizona",
        "roles": ["customer", "community"],
        "badges": [
          {"key": "community", "label": "Community", "icon": "star"}
        ],
        "profile_url": "/profile.php?slug=phoenix-youth",
        "merchant_profile": false,
        "assignment": null,
        "can_add": true
      }
    ],
    "next_cursor": null,
    "has_more": false
  }
}
```

Never return email, phone, exact address, internal notes, claim data, or administrative state beyond active eligibility.

## 4. Assignment listing

### `GET /api/merchant/public-donations/assignments.php`

Query:

```text
campaign_id=<campaign-public-id>
status=active|paused|removed|all
limit=25
cursor=<opaque-cursor>
```

Response item:

```json
{
  "assignment_id": "uuid",
  "community_user_id": 245,
  "display_name": "Phoenix Youth Network",
  "profile_url": "/profile.php?slug=phoenix-youth",
  "avatar_url": "/uploads/profile.jpg",
  "roles": ["customer", "community"],
  "status": "active",
  "public_display_status": "approved",
  "metrics": {
    "gross_received": 25,
    "available": 18,
    "regifted": 4,
    "claimed": 2,
    "redeemed": 1,
    "recalled": 0
  },
  "last_allocated_at": "2026-07-24 10:30:00"
}
```

## 5. Assignment mutation

### `POST /api/merchant/public-donations/assignments.php`

Request:

```json
{
  "campaign_id": "campaign-uuid",
  "community_user_id": 245,
  "action": "add",
  "idempotency_key": "client-generated-uuid"
}
```

Allowed actions:

```text
add
pause
remove
reactivate
```

Rules:

- target user must be active and currently hold Community for `add`/`reactivate`
- one campaign/user row only
- adding an active relationship returns an idempotent success or `409` with current state
- pausing/removing never alters already-issued rewards
- adding creates no inventory records

Response:

```json
{
  "ok": true,
  "message": "Community account added.",
  "data": {
    "assignment_id": "uuid",
    "status": "active",
    "notification_created": true
  }
}
```

Assignment notification:

Title: `Added to a Public Donations campaign`

Body: `[Merchant] added your Community account to [Campaign]. The merchant can now allocate rewards to your account.`

Action URL: `/wallet.php`

## 6. Public-display decision

### `POST /api/community/public-donations/display-consent.php`

Request:

```json
{
  "assignment_id": "assignment-uuid",
  "decision": "approved"
}
```

Allowed decisions:

```text
approved
declined
revoked
```

Rules:

- current user must own the Community account
- allocation eligibility is independent of public-display decision
- `approved` still requires an eligible public profile at render time

## 7. Allocation preview

### `POST /api/merchant/public-donations/allocation-preview.php`

Request:

```json
{
  "campaign_id": "campaign-uuid",
  "mode": "custom_quantity",
  "recipients": [
    {"community_user_id": 245, "quantity": 25},
    {"community_user_id": 378, "quantity": 10}
  ]
}
```

Allowed modes:

```text
single
same_quantity
custom_quantity
```

Preview response:

```json
{
  "ok": true,
  "data": {
    "campaign": {
      "id": "campaign-uuid",
      "name": "Back-to-School Community Rewards"
    },
    "reward": {
      "id": "reward-uuid",
      "title": "Free Family Meal",
      "value_cents": 5000,
      "currency": "USD"
    },
    "inventory_before": 500,
    "recipient_count": 2,
    "total_quantity": 35,
    "total_stated_value_cents": 175000,
    "inventory_after": 465,
    "confirmation_level": "elevated",
    "confirmation_phrase": "ALLOCATE",
    "preview_expires_at": "2026-07-24T10:35:00Z",
    "recipients": [
      {"community_user_id": 245, "name": "Phoenix Youth Network", "quantity": 25},
      {"community_user_id": 378, "name": "Westside Outreach", "quantity": 10}
    ],
    "can_allocate": true
  }
}
```

Preview does not reserve inventory.

## 8. Allocation execution

### `POST /api/merchant/public-donations/allocate.php`

Request:

```json
{
  "campaign_id": "campaign-uuid",
  "mode": "custom_quantity",
  "idempotency_key": "client-generated-uuid",
  "preview_token": "signed-preview-token",
  "confirmation_phrase": "ALLOCATE",
  "message": "For your August outreach program.",
  "internal_note": "Approved by community support manager.",
  "recipients": [
    {"community_user_id": 245, "quantity": 25},
    {"community_user_id": 378, "quantity": 10}
  ]
}
```

Validation:

- 1–50 recipient rows
- user IDs unique
- quantity integer >= 1
- total quantity <= 1,000
- message length <= 500
- internal note length <= 1,000
- all assignments active
- all users active and still Community
- campaign active and in date range
- reward active
- sufficient effective inventory
- elevated confirmation when 100+ units or $1,000+ stated value
- preview token current and payload-compatible

Response:

```json
{
  "ok": true,
  "message": "Rewards allocated.",
  "data": {
    "operation_id": "operation-uuid",
    "recipient_count": 2,
    "completed_quantity": 35,
    "inventory_before": 500,
    "inventory_after": 465,
    "batches": [
      {
        "batch_id": "batch-uuid-1",
        "community_user_id": 245,
        "quantity": 25
      },
      {
        "batch_id": "batch-uuid-2",
        "community_user_id": 378,
        "quantity": 10
      }
    ]
  }
}
```

Idempotency:

- same merchant + key + request hash returns this same operation
- same key + changed hash returns `409`

Allocation notification:

Title: `You received donated rewards`

Body: `[Merchant] sent [quantity] × [Reward] through [Campaign]. The rewards are available in your Microgifter wallet.`

Action URL: `/wallet.php`

## 9. Batch detail

### `GET /api/merchant/public-donations/batch.php`

Query:

```text
batch_id=<batch-public-id>
```

Response includes:

- campaign
- reward snapshot
- Community account
- operation type/mode
- original quantity
- recalled quantity
- available/regifted/claimed/redeemed/expired counts
- actor and timestamps
- privacy-safe individual lifecycle rows for merchant-authorized operational use

Do not return final-recipient contact information unless an existing claim/redemption endpoint already authorizes it for fulfillment.

## 10. Recall preview

### `POST /api/merchant/public-donations/recall-preview.php`

Request:

```json
{
  "campaign_id": "campaign-uuid",
  "batch_id": "batch-uuid"
}
```

Response:

```json
{
  "ok": true,
  "data": {
    "original_quantity": 25,
    "recallable_quantity": 18,
    "regifted_quantity": 4,
    "claimed_quantity": 2,
    "redeemed_quantity": 1,
    "expired_quantity": 0,
    "already_recalled_quantity": 0,
    "maximum_recall_quantity": 18
  }
}
```

## 11. Recall execution

### `POST /api/merchant/public-donations/recall.php`

Request:

```json
{
  "campaign_id": "campaign-uuid",
  "batch_id": "batch-uuid",
  "quantity": 10,
  "reason": "Program allocation was reduced.",
  "idempotency_key": "client-generated-uuid"
}
```

Validation:

- quantity >= 1 and <= recalculated eligible quantity
- reason 8–500 characters
- merchant owns campaign
- actor has recall permission
- individual records remain eligible inside locked transaction

Response:

```json
{
  "ok": true,
  "message": "Unused rewards recalled.",
  "data": {
    "operation_id": "recall-operation-uuid",
    "recalled_quantity": 10,
    "remaining_recallable_quantity": 8,
    "inventory_before": 475,
    "inventory_after": 485
  }
}
```

Recall notification:

Title: `Unused rewards were recalled`

Body: `[Merchant] recalled [quantity] unused rewards from [Campaign]. Rewards already regifted or claimed were not affected.`

## 12. Merchant dashboard summary

### `GET /api/merchant/public-donations/dashboard.php`

Query:

```text
campaign_id=<optional>
date_from=<optional ISO date>
date_to=<optional ISO date>
```

Response:

```json
{
  "ok": true,
  "data": {
    "summary": {
      "active_campaigns": 3,
      "community_accounts": 12,
      "gross_allocated": 420,
      "recalled": 20,
      "net_allocated": 400,
      "available": 210,
      "regifted": 190,
      "claimed": 120,
      "redeemed": 85,
      "expired": 5,
      "gross_stated_value_cents": 2100000,
      "net_stated_value_cents": 2000000,
      "currency": "USD"
    },
    "attention": []
  }
}
```

## 13. Dashboard lists

### `GET /api/merchant/public-donations/campaigns.php`
### `GET /api/merchant/public-donations/community-accounts.php`
### `GET /api/merchant/public-donations/batches.php`
### `GET /api/merchant/public-donations/activity.php`

All support filters, cursor pagination, and merchant isolation.

Activity types:

```text
community.assignment.added
community.assignment.paused
community.assignment.removed
community.assignment.reactivated
public_donation.allocated
public_donation.regifted
public_donation.claimed
public_donation.redeemed
public_donation.recalled
```

## 14. Public campaign data

### `GET /api/public/public-donation.php`

Query:

```text
campaign=<campaign-slug>
```

Response:

```json
{
  "ok": true,
  "data": {
    "campaign": {
      "slug": "back-to-school-community-rewards",
      "name": "Back-to-School Community Rewards",
      "description": "...",
      "status": "active",
      "image_url": "/uploads/campaign.jpg"
    },
    "merchant": {
      "name": "Local Restaurant",
      "profile_url": "/profile.php?slug=local-restaurant"
    },
    "reward": {
      "title": "Free Family Meal",
      "description": "...",
      "image_url": "/uploads/reward.jpg",
      "stated_value_cents": 5000,
      "currency": "USD",
      "redemption_summary": "..."
    },
    "impact": {
      "community_accounts_supported": 3,
      "gross_allocated": 55,
      "net_allocated": 49,
      "regifted": 4,
      "claimed": 2,
      "redeemed": 1
    },
    "supported_accounts": [
      {
        "display_name": "Phoenix Youth Network",
        "username": "phoenix-youth",
        "avatar_url": "/uploads/profile.jpg",
        "location": "Phoenix, Arizona",
        "profile_url": "/profile.php?slug=phoenix-youth",
        "badges": [
          {"key": "community", "label": "Community", "icon": "star"}
        ],
        "quantity_received": 25
      }
    ],
    "disclaimer": "Merchant-funded promotional rewards provided for community distribution. Rewards are not cash donations or tax-deductible charitable contributions."
  }
}
```

No public transaction capability is returned.

## 15. Merchant profile Community data

### `GET /api/public/profile-community.php`

Query:

```text
slug=<merchant-profile-slug>
```

Response:

- privacy-safe summary metrics
- deduplicated eligible Community accounts
- active and completed Public Donations campaigns
- no final-recipient data

## 16. Feature-state endpoint behavior

When feature state is:

- `disabled`: merchant/public feature routes return `404` or feature-unavailable response; no entry links render
- `admin_only`: only permitted administrative test context may use feature
- `selected_merchants`: allowlist controls merchant actions and public rendering
- `enabled`: normal permission and visibility rules apply

## 17. Rate limits

Suggested starting limits:

| Action | Limit |
|---|---:|
| Community search | 60/minute/user |
| Assignment writes | 30/minute/user |
| Allocation preview | 60/minute/user |
| Allocation execution | 10/minute/user |
| Recall preview | 60/minute/user |
| Recall execution | 10/minute/user |
| Public campaign read | 120/minute/IP |

## 18. Request hashing

Canonical request hash must:

- sort recipients by Community user ID
- normalize integers and strings
- exclude non-semantic transient fields
- include merchant, campaign, operation kind/mode, quantities, message, and applicable confirmation context
- use a stable cryptographic hash such as SHA-256

## 19. Audit events

Write audit/security records for:

- Community role added/removed
- assignment added/paused/removed/reactivated
- allocation accepted/rejected/completed
- recall accepted/rejected/completed
- permission denied
- inventory conflict
- idempotency conflict
- reconciliation drift and repair
