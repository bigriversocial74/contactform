# Public Donations + Community Role v1 — Technical Blueprint

## 1. Purpose

Build a merchant-funded Community reward allocation system on top of Microgifter's existing campaign, inventory, wallet, Inbox, PPPM, Microgift, claim, redemption, notification, public-profile, admin-role, audit, and privacy systems.

This feature does not create a second account architecture, a Community-specific wallet, or a new ownership lifecycle.

## 2. Product invariants

1. `community` is a role on the existing user account.
2. Only administrators assign or remove the Community role.
3. One user owns one Community account in v1.
4. Community may coexist with other roles.
5. Only active users currently holding Community may receive new allocations.
6. Already-issued rewards survive later role removal, assignment removal, campaign pause, or campaign completion.
7. `public_donation` campaigns are public-facing but non-transactional.
8. Every allocated quantity becomes one individually owned canonical reward.
9. Existing PPPM remains authoritative for ownership and regifting.
10. Recall applies only to untouched rewards still owned by the original Community user.
11. Public reporting never reveals final-recipient identity, claim codes, wallet IDs, PPPM IDs, Microgift IDs, internal notes, fraud data, or exact addresses.
12. Allocation and recall are atomic and idempotent.
13. Merchant and reward inventory counters must reconcile with canonical individual reward records.
14. No deployment, SQL import, or production readiness is implied by source code alone.

## 3. Public terminology

Internal campaign type: `public_donation`

Primary display label: `Public Donations`

Required explanatory copy:

> Merchant-funded promotional rewards provided for community distribution. Rewards are not cash donations, charitable contributions, or tax-deductible gifts.

Public value copy should say `stated reward value`, not `cash donated`.

## 4. Existing architecture to reuse

### Identity

- `users`
- `roles`
- `user_roles`
- existing admin user-management endpoints and permissions
- existing public-profile identity and visibility rules

### Campaigns and inventory

- central campaign-type registry
- merchant campaign builder
- existing campaign status and date controls
- reward templates and inventory limits
- campaign `quantity_limit` / `issued_count`
- reward-template `quantity_limit` / `issued_count`

### Canonical ownership

- wallet staging/issuance records
- PPPM ownership items
- Microgift instances
- Inbox projections
- existing regift endpoint and lifecycle
- existing claim and redemption flows

### Platform services

- in-app notifications
- audit logs
- security logs
- events
- privacy erasure/anonymization lifecycle
- merchant/public profile rendering

## 5. Canonical lifecycle

```text
reward template inventory
→ Public Donations campaign
→ campaign assignment
→ allocation operation
→ per-user donation batch
→ individual wallet item
→ PPPM item
→ Microgift instance
→ Community Inbox
→ existing regift flow
→ final recipient Inbox
→ claim
→ redemption
```

Attribution remains tied to the original merchant, campaign, batch, and Community account even when current ownership changes through PPPM.

## 6. Database contract

Single-install migration:

`database/20260724_public_donations_community_v1_single_install.sql`

The migration must be idempotent and must not remove campaign/source ENUM values added by later migrations. ENUM changes should inspect the live column definition before appending `public_donation`.

### 6.1 Role registration

Insert or preserve:

```text
roles.slug = community
roles.name = Community
```

No user receives Community automatically.

### 6.2 Campaign/source type support

Add `public_donation` to applicable campaign and wallet source-type columns only when those columns use ENUM.

### 6.3 `campaign_community_assignments`

Purpose: unique relationship between one Public Donations campaign and one Community user.

Required fields:

- `id`
- `public_id`
- `merchant_user_id`
- `campaign_id`
- `community_user_id`
- `status`: `active`, `paused`, `removed`
- `public_display_status`: `pending`, `approved`, `declined`, `revoked`
- `public_display_decided_at`
- `public_display_decided_by_user_id`
- `added_by_user_id`
- `added_at`
- `reactivated_at`
- `paused_at`
- `removed_at`
- `last_allocated_at`
- timestamps

Constraints:

- unique `public_id`
- unique `(campaign_id, community_user_id)`
- indexes for merchant/status, campaign/status, user/status, and public-display status

Re-adding a removed assignment reactivates the existing row.

Public identity may be rendered only when both the account/profile is publicly eligible and the assignment's display state permits it. Allocation does not depend on public-display approval.

### 6.4 `campaign_donation_operations`

Purpose: one merchant allocation or recall request.

Fields:

- `id`, `public_id`
- merchant, campaign, reward-template IDs
- `operation_kind`: `allocation`, `recall`
- `operation_mode`: `single`, `same_quantity`, `custom_quantity`, `partial_recall`
- `idempotency_key`
- `request_hash`
- recipient count
- requested/completed quantity
- inventory before/after
- total stated value and currency
- `confirmation_level`
- public message
- internal note
- actor and timestamps

Constraints:

- unique `public_id`
- unique `(merchant_user_id, idempotency_key)`
- campaign/kind/date indexes
- reward/date indexes

Idempotency rules:

- same key + same hash returns the existing operation
- same key + different hash returns HTTP 409
- failed transactions commit nothing

### 6.5 `campaign_donation_batches`

Purpose: one child batch per Community account within an operation.

Fields:

- IDs and attribution
- assignment ID
- Community user ID
- quantity
- recalled quantity
- status: `allocated`, `partially_recalled`, `recalled`
- message
- actor and timestamps

Constraints:

- unique `public_id`
- unique `(operation_id, community_user_id)`
- campaign/user/date indexes

### 6.6 `campaign_donation_rewards`

Purpose: immutable attribution for every issued unit.

Fields:

- IDs for operation, batch, merchant, campaign, reward template
- original Community user
- wallet item ID
- PPPM item ID
- Microgift instance ID
- status: `allocated`, `recalled`
- reward title snapshot
- value snapshot in cents
- currency snapshot
- allocated/recalled timestamps
- recall actor and reason
- timestamps

Constraints:

- unique `public_id`
- unique wallet, PPPM, and Microgift references
- campaign/status/date index
- original Community user/status/date index
- batch/status index

Do not store current owner here; PPPM remains authoritative.

## 7. Campaign registry contract

Register `public_donation` centrally with:

- label `Public Donations`
- category `community_support`
- reward template required
- public enabled
- public transactional false
- public mode informational
- no public submission endpoint
- merchant-initiated bulk wallet issue mode
- default draft status
- analytics bucket `community_support`

Add a helper equivalent to:

```php
mg_campaign_type_public_transactional(string $type): bool
```

Shared public campaign templates must render engagement controls only when this returns true.

## 8. Feature rollout

Server-side feature state:

```text
disabled
admin_only
selected_merchants
enabled
```

Suggested configuration helper:

```php
mg_public_donations_feature_state(): string
mg_public_donations_is_enabled_for(?int $merchantUserId): bool
```

The schema may be installed while UI and endpoints remain unavailable.

## 9. Inventory accounting

Template remaining:

```text
reward_template.quantity_limit - reward_template.issued_count
```

Campaign remaining when limited:

```text
campaign.quantity_limit - campaign.issued_count
```

Effective availability is the minimum applicable remaining quantity.

Allocation increments campaign and template issued counts by the committed quantity.

Recall decrements those counts only for successfully recalled individual rewards.

Historical gross allocations remain derived from attribution rows.

All inventory updates must use guarded SQL inside a transaction and lock the campaign and reward-template rows.

## 10. Allocation transaction

1. Authenticate and authorize merchant actor.
2. Validate CSRF, rate limit, request size, and idempotency key.
3. Canonicalize payload and calculate request hash.
4. Begin transaction.
5. Resolve existing idempotent operation.
6. Lock campaign.
7. Verify ownership, `public_donation` type, active status, dates, and feature state.
8. Lock reward template and confirm active inventory.
9. Lock assignments in deterministic Community-user-ID order.
10. Confirm all users are active and currently hold Community.
11. Calculate total quantity and stated value.
12. Recalculate effective inventory.
13. Reject complete request if any validation fails.
14. Insert operation and per-user batches.
15. For each unit, create the canonical wallet → PPPM → Microgift → Inbox chain.
16. Insert immutable donation attribution.
17. Increment inventory counters once.
18. Update assignment activity.
19. Insert one in-app notification per Community user.
20. Mark operation complete and commit.
21. Write audit/security summary.

All three UI allocation modes normalize to:

```json
{
  "recipients": [
    {"community_user_id": 245, "quantity": 25},
    {"community_user_id": 378, "quantity": 10}
  ]
}
```

Limits for v1 transaction safety:

- maximum 50 Community accounts per operation
- maximum 1,000 reward units per operation

Large-operation confirmation is required at 100+ units or $1,000+ stated reward value. Merchant must review inventory-after and type `ALLOCATE`.

## 11. Recall rules

A reward is recallable only when:

- attribution status is allocated
- original Community user remains current PPPM owner
- Microgift owner matches
- reward has not been regifted
- reward has not been claimed or redeemed
- reward has not expired
- reward has not been cancelled or recalled
- Community Inbox projection remains with the original user

Recall is performed against individual records, never counters alone.

Canonical mapping should use supported terminal states:

- donation attribution: `recalled`
- wallet staging: cancelled or equivalent
- PPPM: supported cancelled/revoked terminal state
- Microgift: supported cancelled terminal state
- Inbox projection: archived/removed from active availability

Event:

```text
public_donation.reward_recalled
```

Partial recall selects eligible units deterministically and restores only their inventory.

## 12. Community search and assignment UI

Search fields:

- display name
- full name
- public username/profile slug
- merchant/storefront display name
- public general location

Only active Community users appear.

Search result card:

- avatar/logo
- display name
- username/slug
- `★ Community`
- other public role badges
- general location
- public profile link
- assignment state
- Add User action

Never return email, phone, exact address, private notes, or admin metadata.

Assigned account row:

- account identity
- assignment status
- total received
- currently available
- regifted
- claimed
- redeemed
- recalled
- last allocation
- Send Rewards
- History
- Pause/remove

## 13. Merchant campaign management UI

Public Donations campaigns receive a Community Accounts panel containing:

- campaign inventory summary
- Community search
- assigned-account list
- single-user allocation modal
- same-quantity bulk allocation
- custom-quantity bulk allocation
- preview and final confirmation
- recall preview and recall form
- allocation/batch history

No Community-specific recipient workspace is added.

## 14. Merchant Community Support dashboard

Route:

`/merchant-community-support.php`

Summary metrics:

- active campaigns
- Community accounts supported
- gross allocated
- recalled
- net allocated
- available with original Community users
- regifted
- claimed
- redeemed
- expired
- remaining inventory
- gross/net stated reward value

Tabs:

- Campaigns
- Community Accounts
- Donation Batches
- Activity

Attention panel:

- low inventory
- campaigns nearing end date
- large untouched balances
- disabled users or removed Community role
- failed operations
- reconciliation drift

## 15. Public campaign page

Route contract:

`/public-donations.php?campaign=<slug>`

Content:

- Public Donations badge
- merchant name and profile link
- campaign name, description, image, and status
- reward artwork, title, description, stated value, and redemption summary
- aggregate allocated/regifted/claimed/redeemed totals
- supported Community account cards when publicly eligible
- merchant profile and Community-tab links
- links to normal purchasable offers

Required explanation:

> This campaign highlights rewards allocated directly by the merchant to Community accounts. These rewards are not available for public purchase or request.

Excluded:

- Buy
- Join
- Request
- Quantity selector
- Checkout
- Claim
- Email capture

## 16. Merchant public profile

Add a Community tab containing:

- Public Donations campaign totals
- Community accounts supported
- gross/net rewards and stated value
- regifted, claimed, redeemed totals
- deduplicated supported-account cards
- active and completed campaign cards

Public Donations also appear in existing Active Campaigns with a special card:

- Public Donations badge
- campaign image/name
- Community accounts supported
- rewards allocated
- View Campaign
- no transaction controls

## 17. Community badge

Shared representation:

```json
{"key":"community","label":"Community","icon":"star"}
```

Rendered label:

```text
★ Community
```

Apply to:

- Admin User Center
- public user profile
- merchant profile when applicable
- Community campaign search
- assignment management
- public campaign account cards
- Community Support dashboard
- merchant profile Community tab

## 18. Permissions

Reuse existing merchant campaign permissions initially where required, then add/verify:

```text
merchant.community.view
merchant.community.assign
merchant.community.allocate
merchant.community.recall
merchant.community.reporting
```

All writes require authentication, active account, permission, CSRF, campaign ownership, feature eligibility, rate limiting, idempotency, transaction, deterministic locking, audit log, and security log.

## 19. Privacy and erasure

Publicly allowed:

- public display name
- public slug/username
- avatar/logo
- Community badge and other public roles
- general location
- public profile copy
- campaign connection
- aggregate allocation/impact counts

Never public:

- email or phone
- exact address
- final recipient identity
- claim codes
- wallet/PPPM/Microgift identifiers
- merchant internal notes
- recall reasons
- fraud/security data

Privacy erasure must preserve the minimum anonymized commerce and ownership evidence required by the platform's existing privacy-retention system while removing public identity and private account data according to policy.

## 20. Metrics

```text
gross_allocated = count(all donation reward attribution rows)
recalled = count(attribution status recalled)
net_allocated = gross_allocated - recalled
available_with_community = current owner is original Community user and transferable
regifted = current owner differs from original Community user
claimed = canonical lifecycle reached claimed or redeemed
redeemed = canonical lifecycle reached redeemed
expired = canonical lifecycle reached expired
```

Milestones are cumulative. A reward may count as allocated, regifted, claimed, and redeemed.

## 21. Reconciliation tooling

Required command:

`scripts/reconcile_public_donations.php`

Modes:

```text
--dry-run
--campaign=<uuid>
--operation=<uuid>
--repair
--limit=100
```

Detect:

- missing attribution
- missing wallet/PPPM/Microgift/Inbox links
- ownership mismatch
- campaign/reward counter drift
- recalled item still available
- missing notification/audit receipts where deterministically repairable
- assignment whose Community role was removed

Repair only safe deterministic defects and produce audit receipts.

## 22. Phase order

1. Community role and master schema
2. Campaign registry/builder foundation
3. Search and assignments
4. Allocation engine
5. Recall
6. Merchant dashboard
7. Public landing page
8. Merchant profile integration
9. Governance hardening
10. Production QA/reconciliation

Every phase must start from the latest merged integration branch and remain scoped to its issue.
