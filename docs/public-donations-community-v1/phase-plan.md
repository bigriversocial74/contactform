# Public Donations + Community Role v1 — Scoped Phase Plan

Master tracker: `#1315`

Each phase starts from the latest merged `integration-from-repair-20260628` head and opens a separate scoped PR back into that branch.

## Phase 1 — Community multi-role foundation

Issue: `#1316`

Branch: `feature/community-user-role-v1-20260724`

Scope:

- register `community` role
- Admin User Center create/edit/filter support
- permit normal admin user managers to assign/remove Community while elevated roles remain protected
- preserve multi-role behavior
- shared `★ Community` badge
- eligible public-profile badge rendering
- master idempotent single-install schema for all later phases
- PHP 8.2/8.3, JS, SQL, and static validation

Acceptance:

- admin assigns Community to customer or merchant
- roles coexist
- normal users cannot self-assign
- Community filter and badge work
- existing account/wallet/PPPM behavior remains unchanged

SQL:

`database/20260724_public_donations_community_v1_single_install.sql`

## Phase 2 — Public Donations campaign foundation

Issue: `#1317`

Branch: `feature/public-donations-campaign-foundation-v1`

Scope:

- central `public_donation` campaign registration
- merchant builder support
- inventory-backed reward requirement
- normal title, description, dates, image, status, and inventory controls
- informational public route contract
- prevent generic public transaction controls
- feature-state gating
- Active Campaign card data contract

Acceptance:

- merchant creates draft/active campaign when enabled
- public slug and reward association work
- public purchase/join/request/claim are impossible

SQL: no additional migration expected

## Phase 3 — Community search and assignments

Issue: `#1318`

Branch: `feature/public-donations-community-assignment-v1`

Scope:

- search active Community users using normal identity/public-profile fields
- privacy-safe account cards
- add, pause, remove, reactivate
- one campaign/user relationship
- assignment notification
- assigned-user management list
- no reward issuance yet

Acceptance:

- only active Community users appear
- multi-role users appear once
- duplicate assignment blocked/idempotent
- assignment creates notification but no inventory

SQL: no additional migration expected

## Phase 4 — Allocation engine

Issue: `#1319`

Branch: `feature/public-donations-allocation-engine-v1`

Scope:

- one-user quantity
- multi-user same quantity
- multi-user custom quantities
- preview without reservation
- atomic transaction and deterministic locking
- parent operation and child batches
- request hashing and idempotency
- individual wallet/PPPM/Microgift/Inbox/attribution records
- inventory deduction
- reward snapshot data
- one notification per Community account
- elevated confirmation for large quantity/value

Acceptance:

- quantity equals canonical individual record count
- existing wallet/Inbox and regifting work
- failure rolls back all work
- duplicate/concurrent requests cannot over-issue

SQL: no additional migration expected

## Phase 5 — Recall controls

Issue: `#1320`

Branch: `feature/public-donations-recall-controls-v1`

Scope:

- recall preview by lifecycle state
- individual eligibility calculation
- partial recall
- canonical wallet/PPPM/Microgift/Inbox terminal mapping
- inventory restoration
- attribution and batch updates
- notification, audit, security, and lifecycle events

Acceptance:

- only untouched units still owned by original Community user are recalled
- regifted/claimed/redeemed units are protected
- downstream recipients unaffected
- inventory reconciles

SQL: no additional migration expected

## Phase 6 — Merchant Community Support dashboard

Issue: `#1321`

Branch: `feature/merchant-community-support-dashboard-v1`

Route: `/merchant-community-support.php`

Scope:

- merchant navigation/page integration
- summary metrics and stated value
- Campaigns, Community Accounts, Donation Batches, Activity tabs
- deduplicated account aggregation
- drill-down links
- attention panel
- merchant isolation and privacy

Acceptance:

- totals reconcile with attribution/PPPM
- same account across campaigns appears once
- gross, recalled, and net remain distinct
- no cross-merchant data

SQL: no additional migration expected

## Phase 7 — Public campaign landing page

Issue: `#1322`

Branch: `feature/public-donations-public-campaign-page-v1`

Scope:

- dedicated public route and endpoint
- merchant/campaign/reward content
- privacy-safe impact totals
- supported Community cards when publicly eligible
- anonymous aggregate fallback
- required non-cash/non-tax-deductible wording
- links to merchant profile, Community tab, and normal offers
- no transaction controls

Acceptance:

- shareable active campaign page
- cannot initiate a transaction
- no private recipient or ownership data

SQL: no additional migration expected

## Phase 8 — Merchant profile Community tab

Issue: `#1323`

Branch: `feature/merchant-profile-community-tab-v1`

Scope:

- Community public-profile tab
- aggregate campaigns, accounts, quantities, lifecycle, and stated value
- deduplicated Community account cards
- active and completed campaign history
- Public Donations variant in existing Active Campaigns
- campaign URL resolution through registry/dedicated route

Acceptance:

- profile totals reconcile with dashboard
- active campaigns display in Active Campaigns
- completed campaigns remain impact history only
- no transaction controls or private recipient data

SQL: no additional migration expected

## Phase 9 — Governance and lifecycle hardening

Issue: `#1324`

Branch: `feature/public-donations-governance-v1`

Scope:

- granular merchant permissions
- feature states: disabled, admin_only, selected_merchants, enabled
- disabled account and Community-role removal behavior
- campaign pause/archive and reward deactivation
- privacy erasure/anonymized evidence
- concurrency, replay, rate limits, request bounds
- security/audit coverage
- legal-positioning copy

Acceptance:

- unauthorized staff cannot allocate/recall
- role/campaign/account changes preserve issued ownership
- feature state applies consistently
- public data remains privacy-safe

SQL: only if live permission/configuration architecture requires a documented addition

## Phase 10 — Production QA and reconciliation

Issue: `#1325`

Branch: `feature/public-donations-production-qa-v1`

Scope:

- full PHP/JS/SQL workflow
- canonical database fixture
- reconciliation CLI and safe repair mode
- detect missing links, ownership mismatch, counter drift, active recalled items, and removed roles
- deployment, rollout, smoke test, rollback, and SQL verification documentation

Acceptance fixture:

- starting inventory 100
- allocations 10, 20, 25 = gross 55
- four regifted
- two claimed
- one redeemed
- six untouched recalled
- final net allocated 49
- final inventory 51

Acceptance:

- all workflows pass
- dry-run reports no unexplained drift
- all public/merchant/wallet/PPPM totals agree
- deployment status is reported accurately

SQL: no additional migration expected unless QA identifies a reviewed correction

## Merge and deployment rules

- never merge without explicit approval
- verify checks live before reporting
- SQL import is separate from code merge
- code deployment is separate from merge
- production verification requires smoke tests and reconciliation
- each phase must state `No SQL required` or the exact SQL file
