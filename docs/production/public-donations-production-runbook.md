# Public Donations production runbook

## Release boundary

Public Donations deployment has two independent requirements:

1. **Code upload status** — the deployed application files must match the verified integration commit.
2. **SQL import status** — the Public Donations installer must be imported and verified separately.

Do not mark deployment complete until both statuses are independently confirmed. A merged pull request or downloadable ZIP does not prove that production code was uploaded, and a code upload does not prove that SQL was imported.

Required installer:

`database/20260724_public_donations_community_v1_single_install.sql`

No additional Phase 10 SQL is expected.

## Before deployment

Record the following values in the deployment ticket:

- Integration commit SHA.
- Code upload status: pending or confirmed.
- SQL import status: pending or confirmed.
- Current feature state.
- Selected merchant IDs, when applicable.
- Reconciliation dry-run receipt ID and checksum.
- Person completing each deployment step.

Back up the production database before importing SQL or enabling the feature.

## Code deployment

1. Download or deploy the exact verified `integration-from-repair-20260628` commit.
2. Preserve the active production configuration and secrets.
3. Upload the code.
4. Confirm the deployed commit or release manifest matches the verified integration SHA.
5. Keep the feature disabled until SQL verification and smoke testing are complete.

Recommended initial state:

```text
MG_PUBLIC_DONATIONS_FEATURE_STATE=disabled
```

## SQL import and verification

Import:

```text
database/20260724_public_donations_community_v1_single_install.sql
```

Verify these tables exist:

- `campaign_community_assignments`
- `campaign_donation_operations`
- `campaign_donation_batches`
- `campaign_donation_rewards`

Verify these permissions exist:

- `merchant.public_donations.view`
- `merchant.public_donations.manage`
- `merchant.public_donations.assign`
- `merchant.public_donations.allocate`
- `merchant.public_donations.recall`
- `merchant.public_donations.report`

Verify the Community role exists and does not receive merchant Public Donations permissions.

## Reconciliation dry run

Dry-run is the default and must be completed before rollout:

```bash
php scripts/reconcile_public_donations.php --merchant=MERCHANT_ID --dry-run
```

Optional campaign or operation scope:

```bash
php scripts/reconcile_public_donations.php \
  --merchant=MERCHANT_ID \
  --campaign=CAMPAIGN_PUBLIC_ID_OR_SLUG \
  --limit=250 \
  --dry-run

php scripts/reconcile_public_donations.php \
  --merchant=MERCHANT_ID \
  --operation=OPERATION_PUBLIC_ID \
  --dry-run
```

Store the returned receipt ID and checksum. Investigate every report-only finding before rollout:

- Missing attribution.
- Missing Wallet, PPPM, Microgift, or Inbox links.
- Ownership mismatch across canonical stores.

The reconciliation tool intentionally does not invent attribution or ownership.

## Explicit safe repair

Use repair mode only after reviewing the dry-run output. Safe deterministic modes are:

- `counters`
- `batch_totals`
- `recalled_visibility`
- `assignments`

Apply all safe modes:

```bash
php scripts/reconcile_public_donations.php \
  --merchant=MERCHANT_ID \
  --campaign=CAMPAIGN_PUBLIC_ID_OR_SLUG \
  --repair=safe \
  --actor=ADMIN_USER_ID
```

Run a second dry-run after repair and retain both receipts. Repairs must leave zero unexplained drift. Existing rewards must remain valid when a Community role is removed.

## Feature rollout

Promote the feature gradually:

1. `MG_PUBLIC_DONATIONS_FEATURE_STATE=disabled`
2. `MG_PUBLIC_DONATIONS_FEATURE_STATE=admin_only`
3. `MG_PUBLIC_DONATIONS_FEATURE_STATE=selected_merchants`
4. `MG_PUBLIC_DONATIONS_FEATURE_STATE=enabled`

For selected merchant rollout:

```text
MG_PUBLIC_DONATIONS_FEATURE_STATE=selected_merchants
MG_PUBLIC_DONATIONS_MERCHANT_IDS=123,456
```

At each stage, confirm merchant navigation, APIs, public campaign pages, and public merchant-profile Community tabs follow the same rollout state.

## Smoke test

Complete the following Smoke test for one approved merchant:

1. Open Merchant Campaigns and confirm Public Donations is available only to authorized roles.
2. Create or inspect an active Public Donations campaign.
3. Assign a multi-role Community account.
4. Run allocation preflight and verify inventory is not reserved.
5. Allocate one reward with a unique idempotency key.
6. Replay the identical allocation and confirm no duplicate reward is issued.
7. Verify Wallet, PPPM, Microgift, and Inbox records agree.
8. Regift one reward and confirm recall excludes it.
9. Claim one reward and redeem one eligible reward.
10. Recall one untouched original-owner reward.
11. Confirm recalled Wallet, PPPM, Microgift, and Inbox records are terminal and hidden.
12. Confirm the merchant Community Support dashboard, public campaign page, and profile Community tab show the same gross, recalled, and net totals.
13. Confirm public pages expose no final-recipient identity, claim code, ownership identifier, private contact information, or exact location.
14. Confirm operational copy states that Public Donations are merchant-funded promotional rewards, not cash donations or tax-deductible charitable contributions.
15. Run reconciliation dry-run and require zero unexplained drift.

## Production acceptance metrics

The automated acceptance fixture proves:

- Initial reward inventory: 100.
- Three multi-role Community accounts receive 10, 20, and 25 rewards.
- Gross allocated: 55.
- Regifted: 4.
- Claimed: 2.
- Redeemed: 1.
- Recalled untouched rewards: 6.
- Net allocated: 49.
- Remaining inventory: 51.

## Rollback

Rollback is feature-first and data-preserving:

1. Set `MG_PUBLIC_DONATIONS_FEATURE_STATE=disabled`.
2. Confirm Public Donations disappears from merchant and public entry points.
3. Stop new assignments, allocations, and recalls.
4. Do not delete Wallet, PPPM, Microgift, Inbox, operation, batch, attribution, campaign, or audit records.
5. Roll back application code only to a verified integration commit that understands the installed additive schema.
6. Run reconciliation dry-run and retain the receipt.
7. Investigate any report-only ownership or link findings before re-enabling.

The additive SQL is not rolled back by dropping tables during an incident. Historical reward ownership and campaign attribution must remain intact.

## Deployment status template

```text
Integration commit: ______________________________
Code upload status: pending / confirmed
Code upload confirmed by: ________________________
SQL import status: pending / confirmed
SQL import confirmed by: _________________________
Feature state: disabled / admin_only / selected_merchants / enabled
Selected merchant IDs: ___________________________
Pre-rollout reconciliation receipt: ______________
Pre-rollout receipt checksum: _____________________
Smoke test status: pending / passed / failed
Post-rollout reconciliation receipt: _____________
Rollback required: yes / no
Notes: ___________________________________________
```

Do not mark deployment complete until the code upload status and SQL import status are both confirmed and the smoke test passes.
