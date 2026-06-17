# Microgifter Stage Plan Delta Register

This file tracks intentional differences between the original stage implementation plans and the actual repository build decisions.

The purpose is not to hide deviations. The purpose is to make each adjustment visible, explain why it happened, and carry the useful decisions forward into future stages.

## Status values

- `Accepted` — keep this change and carry it forward.
- `Needs Review` — useful change, but verify before future stages depend on it.
- `Temporary` — acceptable during staging, but should be replaced later.
- `Rejected` — do not carry forward.

## Delta table

| ID | Stage | Area | Plan expectation | Actual implementation | Reason | Status | Carry-forward rule |
|---|---:|---|---|---|---|---|---|
| DELTA-001 | 1 | Page architecture | Stage 1 primarily focuses on identity/auth/backend foundation. | Active root app moved toward PHP pages: `index.php`, `build.php`, `agent.php`, auth pages, and `account.php`. | No files are published yet, so using PHP now gives safer server-rendered auth, CSRF, and permission-aware UI. | Accepted | Future user-facing app pages should be PHP-rendered unless a page is intentionally static. |
| DELTA-002 | 1 | Prototype preservation | Existing `index.html`, `build.html`, and `agent.html` were not part of the strict identity foundation. | Kept root HTML prototypes while adding PHP active pages. | They contain useful UI/onboarding concepts and should remain reference material until PHP UI is fully accepted. | Temporary | Move old prototypes fully under `docs/reference/` after PHP UI is validated. |
| DELTA-003 | 1 | Builder/onboarding | Builder/product/agent features are later-stage concerns. | Added a light `build.php` onboarding shell and guest test/draft behavior. | User onboarding begins at builder/test-agent flow. | Needs Review | Keep onboarding lightweight until product persistence stages. |
| DELTA-004 | 1 | Agent workspace | Agent/inbox features are later-stage concerns. | Added a permission-aware `agent.php` shell. | Needed auth-aware UX and permission boundaries early. | Needs Review | Backend APIs must always enforce authorization. |
| DELTA-005 | 1 | CSS architecture | Stage 1 did not require a complete design-system split. | Added global and section stylesheets. | Prevents duplicated inline CSS. | Accepted | Keep global tokens in the shared design system and module styles in section files. |
| DELTA-006 | 1 | JavaScript architecture | Stage 1 did not require full JS modules. | Added shared and page-specific module structure. | Avoids inline scripts and supports growth. | Accepted | Load shared JS first and page-specific JS through the shared layout. |
| DELTA-007 | 1 | Naming | Future assets used mixed commerce/program naming. | Existing names were retained where referenced. | Avoid breaking active paths. | Needs Review | Rename only through a deliberate compatibility change. |
| DELTA-008 | 1 | Admin creation | Stage 1 requires roles/admin foundation. | First admin is promoted manually through SQL. | Safer initial posture. | Accepted | Do not expose public admin bootstrap endpoints. |
| DELTA-009 | 1 | Email delivery | Token structures exist. | Provider delivery is deferred. | Provider not selected. | Temporary | Complete before production launch. |
| DELTA-010 | 1 | Security hardening | Basic security was expected. | Added server hardening, sessions, rate limits, logs, tests, and CI. | Safer foundation before feature growth. | Accepted | Update security tests for every new protected surface. |
| DELTA-011 | 1 | Testing | Smoke validation was expected. | Added PHPUnit and CI coverage. | Repeatable gates are required. | Accepted | Add tests for every protected endpoint and ownership rule. |
| DELTA-012 | 1 | Rate limiting | Anti-abuse controls were expected later. | Added early DB-backed rate limiting. | Needed before traffic. | Accepted | High-risk writes require a rate-limit review. |
| DELTA-013 | 1 | Repo source of truth | Early workflow used ZIP thinking. | GitHub became canonical. | User confirmed repository-first workflow. | Accepted | Update GitHub first; deployment bundles are secondary. |
| DELTA-014 | 1 | High-volume foundation | Not strict Stage 1 scope. | Added ownership, outbox, idempotency, and read-model guidance. | Prevent scale-incompatible later tables. | Accepted | High-growth tables require ownership, lifecycle, index, idempotency, and event review. |
| DELTA-015 | 1 | AWS database direction | Production database direction was open. | Aurora MySQL-compatible was documented as the preferred target. | Matches existing MySQL patterns. | Accepted | Stay MySQL-compatible through the core build. |
| DELTA-016 | 4 | Product assets | Protected asset/media work was expected later. | Product version assets and media delivery foundations were built during Stage 4. | Builder and published product flows required media earlier. | Accepted | Stage 8 must reuse the existing asset catalog and storage policy. |
| DELTA-017 | 3/5 | PPPM ownership | Unit ownership and operations were expected to mature later. | PPPM issuance, item identity, lifecycle, merchant operations, and gift mappings were built during Stages 3 and 5. | PPPM is central to gifts and commerce. | Accepted | `pppm_items` remains the permanent owned-unit source for Stage 8. |
| DELTA-018 | 6 | Customer library | Owned asset/library behavior was expected in Stage 8. | Stage 6B added owned, purchased, sent, received, and redeemed PPPM account views. | Customer commerce integration needed a usable account area. | Accepted | Extend these views with entitlement data; do not replace them. |
| DELTA-019 | 5/7 | Paid-order fulfillment | Stage boundaries originally separated commerce, payment, and ownership more strictly. | Paid orders now issue PPPM items and post grouped ledger transactions in one controlled orchestration. | Keeps payment, ownership, and balances synchronized while preserving separate IDs. | Accepted | Stage 8 grants must begin from verified PPPM issuance, not payment IDs. |
| DELTA-020 | 7 | Payout system | Payout records and reconciliation existed before canonical wallets and ledger. | Stage 7 adapted the earlier records rather than duplicating them. | Preserved working payment foundations. | Accepted | Future stages must reuse canonical payout, webhook, and reconciliation records. |
| DELTA-021 | 7 | Ledger immutability | Database triggers were initially considered. | Hosted MySQL privilege limits led to append-only service design, reversal-only corrections, constraints, and tests. | Trigger DDL failed under hosted binary logging without elevated privileges. | Accepted | Add production database-role restrictions later; never add ledger update/delete application paths. |
| DELTA-022 | 8 | Entitlement architecture | A separate owned-library model could have been introduced. | Existing PPPM and account-library foundations already provide owned-unit identity and views. | Avoids competing ownership sources. | Accepted | Stage 8 adds entitlements linked to PPPM items and product assets, not a replacement owned-item table. |
| DELTA-023 | 8 | Future Demand signals | Asset access may become predictive data later. | Stage 8 will record reliable entitlement and access events only. | Scoring belongs to later intelligence stages. | Accepted | Capture source events now; defer scoring and prediction. |
| DELTA-024 | 8 | Protected delivery | Full binary delivery could require storage-provider integration. | Stage 8B authorizes access and issues short-lived metadata-only delivery grants while hiding storage keys. | Storage provider signing and proxy streaming are later infrastructure choices. | Accepted | Keep entitlement authorization separate from storage delivery; add signed URLs/proxy only through this grant layer. |
| DELTA-025 | 8 | Partial refund policy | Partial refunds could automatically revoke access. | Stage 8B creates review items for ambiguous partial refunds. | Unit-to-refund mapping may be unclear. | Accepted | Do not silently revoke ambiguous access; create admin review. |

## How to use this register

1. Add a new row whenever implementation differs from the stage plan.
2. Assign a status before moving to the next stage.
3. Promote `Needs Review` rows after testing.
4. Check this file before starting each new stage.
