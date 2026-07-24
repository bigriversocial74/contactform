# Public Donations + Community Role v1 — Deployment Runbook

## 1. Scope

This runbook controls branch reconciliation, phased PR delivery, SQL installation, feature rollout, smoke testing, rollback, and status reporting.

It does not authorize a merge or production deployment by itself.

## 2. Repository workflow

Repository:

`bigriversocial74/contactform`

Integration/deploy branch:

`integration-from-repair-20260628`

Planning branch:

`feature/community-user-role-v1-20260724`

Master tracker:

`#1315`

Phase trackers:

`#1316` through `#1325`

## 3. Pre-build reconciliation

Before Phase 1 coding resumes:

1. Verify the other agent's final PR, state, head SHA, merge SHA, checks, and changed files.
2. Verify the current live head of `integration-from-repair-20260628`.
3. Compare the planning branch with current integration.
4. Identify overlap in admin users, public profiles, campaign files, shared styles/scripts, migrations, workflows, and docs.
5. Prefer rebuilding Phase 1 from the newest integration head when overlap is meaningful.
6. Carry forward only reviewed Public Donations changes.
7. Confirm the Phase 1 branch is zero commits behind integration before opening the PR.

Do not open a PR from a stale branch merely because it is mergeable.

## 4. Phase merge order

1. Phase 1 — `#1316`
2. Phase 2 — `#1317`
3. Phase 3 — `#1318`
4. Phase 4 — `#1319`
5. Phase 5 — `#1320`
6. Phase 6 — `#1321`
7. Phase 7 — `#1322`
8. Phase 8 — `#1323`
9. Phase 9 — `#1324`
10. Phase 10 — `#1325`

Each new phase must start from the latest merged integration head, not from an older feature branch.

## 5. PR standard

Every phase PR must state:

- exact scope
- base branch and starting SHA
- changed files
- validation actually run
- GitHub workflow state
- SQL requirement
- configuration requirement
- deployment status
- known follow-up phases

Do not claim:

- checks passed before verification
- SQL imported before David confirms
- production code uploaded before David confirms
- deployment complete before smoke testing

No merge occurs without explicit instruction from David Evans.

## 6. SQL strategy

Master single-install migration:

`database/20260724_public_donations_community_v1_single_install.sql`

Introduced in Phase 1.

Expected contents:

- `community` role
- future-safe `public_donation` campaign/source type support
- campaign assignments
- donation operations
- donation batches
- individual reward attribution
- public-display state
- immutable reward snapshots
- idempotency and reporting indexes

The migration must:

- be idempotent
- preserve existing data
- preserve later ENUM values
- use `CREATE TABLE IF NOT EXISTS` where appropriate
- add missing indexes/columns safely
- avoid assigning Community to existing users
- avoid enabling the feature automatically

## 7. SQL import procedure

Before import:

1. Take a database backup or confirm the current backup process.
2. Confirm target database/environment.
3. Review live table/column definitions, especially ENUM columns.
4. Run the SQL validation contract.
5. Confirm the application code remains feature-disabled.

Import:

1. Import the single-install SQL once.
2. Capture import completion and errors.
3. Re-run the migration only if the idempotency contract has been tested.

Post-import verification:

```sql
SELECT id, slug, name FROM roles WHERE slug = 'community';
SHOW TABLES LIKE 'campaign_community_assignments';
SHOW TABLES LIKE 'campaign_donation_operations';
SHOW TABLES LIKE 'campaign_donation_batches';
SHOW TABLES LIKE 'campaign_donation_rewards';
```

Also verify required unique keys and indexes through `SHOW CREATE TABLE`.

SQL status remains `required/not confirmed` until David states the import succeeded.

## 8. Feature rollout

Feature state sequence:

```text
disabled
admin_only
selected_merchants
enabled
```

### Disabled

- schema may exist
- no merchant/public entry points
- endpoints deny access

### Admin only

- internal test fixture only
- no public campaign indexing

### Selected merchants

- one or more allowlisted merchant IDs
- controlled pilot
- monitor reconciliation and support activity

### Enabled

- normal permission and visibility rules
- public pages active for eligible campaigns

Never expose the public route before the non-transactional rendering contract is verified.

## 9. Recommended deployment sequence

### After Phase 1 merge

- upload code
- import master SQL
- confirm Community appears in Admin User Center
- keep Public Donations feature disabled

### After Phase 2 merge

- confirm campaign registry/builder while still admin-only or selected-merchants
- verify public route cannot transact

### After Phase 3 merge

- test Community search and assignments
- verify assignment notification
- confirm no rewards are issued by assignment

### After Phase 4 merge

- execute controlled one-unit allocation
- verify wallet, PPPM, Microgift, Inbox, attribution, inventory, and notification
- run reconciliation dry-run

### After Phase 5 merge

- test one untouched recall
- verify inventory restoration and active-wallet removal
- confirm regifted reward cannot be recalled

### After Phases 6–8

- verify merchant dashboard
- verify public campaign privacy
- verify profile Community tab and Active Campaign cards

### After Phase 9

- verify permissions, feature state, privacy erasure, role removal, campaign pause, and concurrency behavior

### After Phase 10

- run complete acceptance fixture
- review reconciliation report
- decide full release

## 10. Production smoke test

Use controlled merchant and user accounts.

1. Assign Community to an existing test user.
2. Confirm multi-role state and Community badge.
3. Create a low-value one-unit reward.
4. Create a Public Donations campaign.
5. Add the Community account.
6. Verify assignment notification.
7. Allocate one reward.
8. Verify one record across wallet, PPPM, Microgift, Inbox, attribution, campaign inventory, and reward-template inventory.
9. Verify allocation notification.
10. Create a second unit and regift it through the existing flow.
11. Confirm original campaign/Community attribution remains.
12. Recall the untouched first unit.
13. Confirm it is no longer available and inventory is restored.
14. Run reconciliation dry-run.
15. Verify public campaign contains no transaction controls.
16. Verify public campaign and merchant profile expose no private recipient data.

## 11. Reconciliation command

Expected:

```bash
php scripts/reconcile_public_donations.php --dry-run --limit=100
```

Campaign-specific:

```bash
php scripts/reconcile_public_donations.php --dry-run --campaign=<uuid>
```

Repair mode requires explicit authorization and backup confirmation:

```bash
php scripts/reconcile_public_donations.php --repair --campaign=<uuid> --limit=100
```

Capture the output and audit receipts.

## 12. Rollback principles

### Before any allocations

- return feature state to `disabled`
- roll back code through normal deployment process
- leave additive schema installed unless a verified schema problem requires a controlled rollback

### After allocations exist

Do not drop Public Donations tables or remove role/type values.

Instead:

- disable feature entry points
- block new assignments/allocations/recalls as appropriate
- preserve wallet, PPPM, Microgift, Inbox, attribution, audit, claim, and redemption evidence
- repair through reconciliation tooling
- deploy a corrective PR

Never delete already-issued rewards as a rollback shortcut.

## 13. SQL rollback

Because the migration is additive and later phases depend on attribution history, production SQL rollback should normally mean feature disablement, not table deletion.

Destructive rollback requires:

- no existing allocation records
- verified backup
- explicit approval
- a separately reviewed rollback script

## 14. Privacy rollback

Disabling public pages must not expose or delete private identity improperly.

If public rendering is incorrect:

- disable feature/public route
- retain aggregate internal records
- correct visibility logic
- re-enable only after privacy tests pass

## 15. Monitoring

Monitor:

- allocation failure rate
- idempotency conflicts
- inventory conflicts
- reconciliation drift
- orphan canonical records
- failed notifications
- role removals on active assignments
- public-route errors
- recall rejection reasons
- cross-merchant authorization failures

## 16. Deployment-status template

Use this exact structure in handoffs:

```text
Public Donations phase: <phase>
PR: <number and title>
PR state: <open/merged>
Integration merge commit: <sha or not merged>
Checks: <verified status>
SQL: <required/no SQL>
SQL import: <confirmed/not confirmed>
Configuration: <required/not required/status>
Code upload/deployment: <confirmed/not confirmed>
Production smoke test: <passed/not run/not confirmed>
Reconciliation: <clean/issues/not run>
```

## 17. Current status at document creation

```text
Public Donations implementation PR: none
Master SQL import: not performed
Feature state: not deployed
Production deployment: not performed
Production verification: not performed
```

The planning branch is not itself a deployable or approved feature release. Reconciliation with the other agent and current integration branch is mandatory before Phase 1 PR creation.
