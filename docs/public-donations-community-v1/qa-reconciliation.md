# Public Donations + Community Role v1 — QA and Reconciliation

## 1. Purpose

Prove that Public Donations uses the existing canonical Microgifter ownership lifecycle correctly, preserves inventory integrity, remains private by default, and can be reconciled after failures or deployments.

## 2. Required validation workflow

Recommended workflow file:

`.github/workflows/public-donations-community-v1.yml`

Run:

- PHP 8.2 syntax
- PHP 8.3 syntax
- JavaScript syntax
- SQL single-install contract
- focused static contract checks
- integration/fixture tests where database support is available

No test may be reported as passed unless it was actually executed locally or confirmed in GitHub Actions.

## 3. Canonical acceptance fixture

### Merchant

- one active merchant account
- one active reward template
- reward quantity limit: 100
- reward issued count: 0
- one active Public Donations campaign
- campaign quantity limit: 100
- campaign issued count: 0

### Community users

- User A: `customer + community`
- User B: `merchant + community`
- User C: `community`
- all active with eligible public profiles for the primary public-page test

### Allocation

Merchant allocates:

- User A: 10
- User B: 20
- User C: 25

Expected immediately after allocation:

- gross allocated: 55
- net allocated: 55
- recalled: 0
- reward-template issued count: 55
- campaign issued count: 55
- inventory remaining: 45
- exactly 55 wallet staging records
- exactly 55 PPPM items
- exactly 55 Microgift instances
- exactly 55 active Community Inbox projections
- exactly 55 donation attribution records
- exactly 3 child batches
- exactly 1 parent operation
- exactly 3 allocation notifications

### Downstream lifecycle

User A regifts four rewards.

Of those four:

- two are claimed
- one of the claimed rewards is redeemed

Expected lifecycle metrics:

- gross allocated: 55
- regifted: 4
- claimed: 2
- redeemed: 1

### Recall

Merchant attempts to recall 10 rewards from User A.

User A originally received 10 and regifted 4, so maximum untouched recall is 6.

Expected preview:

- original quantity: 10
- recallable: 6
- regifted: 4
- claimed: 2
- redeemed: 1

Merchant recalls six.

Expected final inventory and metrics:

- gross allocated: 55
- recalled: 6
- net allocated: 49
- reward-template issued count: 49
- campaign issued count: 49
- inventory remaining: 51
- regifted: 4
- claimed: 2
- redeemed: 1

The six recalled items must no longer be available in User A's wallet/Inbox and must be terminally represented in wallet, PPPM, Microgift, and donation attribution records.

## 4. Identity and role tests

- Admin can assign Community to an existing customer.
- Admin can assign Community to an existing merchant.
- Community can coexist with all permitted non-elevated roles.
- Normal user cannot self-assign Community.
- Non-super admin cannot assign `admin` or `super_admin` through the Community changes.
- User Center role filter returns Community users.
- Community badge appears in User Center.
- Community badge appears on eligible public profiles.
- Badge means role status only and does not replace existing verification indicators.
- Removing another role does not remove Community.
- Removing Community does not delete wallet/PPPM records already issued.

## 5. Campaign tests

- Public Donations appears in the campaign builder only when feature state permits.
- Reward template is required.
- Draft can be created without public exposure.
- Active campaign receives normal public slug behavior.
- Public Donations campaign can be paused, completed, or archived under existing campaign rules.
- Public campaign route renders informational content only.
- No generic signup, join, purchase, checkout, claim, request, email-capture, or quantity form is present.
- Existing campaign types are unchanged.

## 6. Community search tests

- Active Community user appears.
- Disabled Community user does not appear.
- Active user without Community does not appear.
- Multi-role Community user appears once.
- Search matches display name, public slug, merchant name, and general location.
- Search response excludes email, phone, exact address, internal notes, claim identifiers, and administrative metadata.
- Pagination and query limits work.
- Cross-merchant campaign ownership is enforced.

## 7. Assignment tests

- Add creates one unique campaign/user row.
- Add creates no reward inventory.
- Add creates one in-app notification.
- Duplicate add is idempotent or returns a controlled conflict.
- Pause prevents new allocation.
- Remove prevents new allocation.
- Reactivate restores eligibility if user remains active and Community.
- Re-adding a removed user reuses the existing row.
- Removing assignment does not alter already-issued rewards.
- Public-display approval is independent of allocation eligibility.

## 8. Allocation tests

### Single user

- quantity 1
- quantity 25
- exact canonical record counts
- inventory counters update once
- notification created

### Same quantity, multiple users

- three users receive identical quantity
- total calculation correct
- one parent operation and one batch per user

### Custom quantities

- per-user quantities preserved
- total and stated value correct
- sorted canonical request hash stable regardless of input order

### Validation failures

- zero or negative quantity
- non-integer quantity
- duplicate user row in request
- inactive assignment
- removed Community role
- disabled target user
- paused campaign
- expired campaign date
- inactive reward
- insufficient template inventory
- insufficient campaign inventory
- over 50 recipients
- over 1,000 total units
- missing elevated confirmation
- stale preview token

Each failure must commit no partial operation, batch, reward, inventory, or notification records.

## 9. Idempotency tests

- same key + identical request returns original operation
- same key + recipient order changed but semantically identical returns original operation
- same key + changed quantity returns `409`
- same key + changed message returns `409` when message is part of the semantic request
- browser retry after response timeout does not issue twice
- two simultaneous requests with same key issue once

## 10. Concurrency tests

- two operations compete for final inventory
- deterministic lock order avoids deadlock where possible
- one operation succeeds and the other receives a controlled inventory conflict
- issued counts never exceed limits
- issued counts never become negative after recall
- simultaneous pause/allocate fails safely
- simultaneous role removal/allocate fails safely
- simultaneous recall/regift cannot recall an item after ownership changed

## 11. Canonical lifecycle tests

For every allocated unit verify:

- wallet item exists
- PPPM item exists
- Microgift instance exists
- Inbox projection exists
- donation attribution links all canonical IDs
- original Community user recorded
- reward title/value/currency snapshots stored
- current ownership remains authoritative in PPPM

Regift tests:

- existing Action Center/regift endpoint works without Public Donations-specific logic
- owner changes in PPPM and Microgift
- original Community attribution remains unchanged
- final recipient receives existing Inbox projection and notification

Claim/redemption tests:

- existing claim flow works
- existing redemption flow works
- merchant/public metrics reflect lifecycle milestones

## 12. Recall tests

Recallable:

- untouched, unexpired reward still owned by original Community user

Not recallable:

- regifted
- claimed
- redeemed
- expired
- already recalled
- cancelled
- ownership mismatch
- missing canonical record

Verify:

- preview and execution recalculate independently
- partial recall selects deterministic eligible units
- required reason enforced
- one recall notification per affected Community account
- inventory restored only for recalled units
- downstream recipients unaffected
- recalled units disappear from active wallet/Inbox availability
- audit and security events recorded

## 13. Public privacy tests

- eligible approved Community account displays public identity
- declined/revoked display consent remains aggregate only
- private/inactive/unavailable public profile remains aggregate only
- public campaign includes no final-recipient names
- no email, phone, exact address, claim code, wallet ID, PPPM ID, Microgift ID, internal note, recall reason, or fraud signal appears
- merchant Community tab follows the same rules
- search-engine behavior follows active campaign and public-profile visibility

## 14. Dashboard tests

- campaign totals reconcile
- Community user connected to multiple campaigns appears once in aggregate account list
- gross/recalled/net are separate
- lifecycle milestones are cumulative
- stated value snapshots remain stable after reward template changes
- merchant cannot access another merchant's data
- attention panel detects low inventory and role removal
- pagination/filtering works

## 15. Metric reconciliation queries

Required logical checks:

```text
count(donation_rewards) = gross_allocated
count(donation_rewards where status=recalled) = recalled
gross_allocated - recalled = net_allocated
campaign.issued_count = net allocated for campaign
reward_template.issued_count = net active issued inventory governed by template
```

For every non-recalled attribution:

```text
wallet_item_id exists
pppm_item_id exists
microgift_instance_id exists
```

For every recalled attribution:

```text
not available in active Inbox
not transferable by original Community user
inventory counters restored
```

## 16. Reconciliation command

Required script:

`scripts/reconcile_public_donations.php`

Supported options:

```text
--dry-run
--campaign=<public-id>
--operation=<public-id>
--repair
--limit=100
```

Default behavior must be dry-run unless explicit repair is supplied.

Detect:

- attribution row missing canonical ID
- canonical ID references missing record
- wallet/PPPM/Microgift/Inbox mismatch
- ownership mismatch
- reward snapshot missing
- campaign issued-count drift
- reward-template issued-count drift
- recalled item still active
- assignment active while user no longer Community
- completed operation quantity differs from child/reward totals

Safe repair candidates:

- deterministic missing attribution link where one unambiguous canonical record exists
- counter recalculation
- hiding a recalled Inbox projection
- marking assignment attention state

Do not automatically repair ambiguous ownership, claim, redemption, payment, or recipient identity conflicts.

Every repair requires audit/security receipt with before/after values.

## 17. Failure-injection tests

Inject failures after:

- parent operation insert
- first batch insert
- first wallet item
- PPPM bridge
- Microgift creation
- Inbox projection
- attribution insert
- inventory update
- notification insert

Expected: complete transaction rollback unless the architecture intentionally uses a documented recovery boundary. No orphan operation may be reported complete.

## 18. Regression checks

Run existing validation covering:

- user management
- campaign creation
- wallet
- PPPM
- regift
- claim
- redemption
- notifications
- public profiles
- privacy erasure
- merchant navigation

Public Donations must not alter behavior of existing campaign types or normal customer/merchant rewards.

## 19. Production smoke test

After code deployment and SQL import are separately confirmed:

1. Verify Community role exists.
2. Assign Community to a controlled test account.
3. Create a one-unit test reward and draft Public Donations campaign.
4. Add test Community account.
5. Allocate one reward.
6. Verify wallet, PPPM, Microgift, Inbox, attribution, counters, and notification.
7. Regift to a second controlled account.
8. Verify attribution remains intact.
9. Do not test recall on the regifted unit; create another unit and recall it untouched.
10. Run reconciliation dry-run.
11. Verify public campaign is informational only.
12. Verify merchant Community tab/dashboard privacy boundaries.

## 20. Completion standard

Phase 10 is complete only when:

- all scoped workflows pass
- canonical fixture reconciles
- dry-run reports zero unexplained drift
- repair mode has focused tests
- deployment requirements are documented
- SQL/import/deployment status is stated accurately
