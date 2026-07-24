# Public Donations + Community Role v1

## New-chat handoff

Repository: `bigriversocial74/contactform`

Integration/deploy branch: `integration-from-repair-20260628`

Isolated planning branch: `feature/community-user-role-v1-20260724`

Master tracker: GitHub issue `#1315`

Phase trackers: `#1316` through `#1325`

Coordination guard: `#1326`

Documentation tracker: `#1327`

Architecture review: `#1328`

## Critical workflow

Before any Public Donations implementation PR is opened:

1. Verify the newest live head of `integration-from-repair-20260628`.
2. Identify the other agent's final branch, PR, merge commit, and changed files.
3. Compare that work against `feature/community-user-role-v1-20260724`.
4. Rebuild or rebase Phase 1 from the newest integration head.
5. Remove stale or overlapping file revisions.
6. Run PHP 8.2, PHP 8.3, JavaScript, SQL, and focused static validation.
7. Open a scoped PR back into `integration-from-repair-20260628`.
8. Do not merge without David Evans's explicit request.
9. Do not claim SQL import, deployment, or production verification until David confirms each separately.

## Current status

- Product specification: complete
- Technical architecture: complete
- API contracts: complete
- Scoped phase plan: complete
- QA and reconciliation plan: complete
- Deployment runbook: complete
- Phase 1 isolated branch: exists
- Public Donations implementation PR: none
- SQL import: not performed
- Deployment: not performed
- Production verification: not performed

Some preparatory Phase 1 files may exist on the isolated branch. They are not approved for PR until reconciliation with the other agent's completed work and the newest integration head.

## Locked product contract

### Community role

`community` is an admin-controlled role in the existing multi-role user system.

- One normal Microgifter user owns each Community account.
- Community can coexist with customer, merchant, creator, admin-permitted non-elevated roles, and future roles.
- Community users appear in normal user search with a visible `★ Community` badge.
- The badge means role status only; it is not verification or endorsement.
- Community has no dedicated workspace in v1.
- Existing Wallet, Inbox, PPPM, regift, claim, and redemption flows remain authoritative.
- Removing Community stops future assignments and allocations but does not invalidate rewards already issued.

### Public Donations campaign

Internal campaign key: `public_donation`

Display name: `Public Donations`

- Merchant-created and inventory-backed.
- Publicly viewable but not publicly transactional.
- Not a cash donation, fundraising page, or tax-deductible charitable contribution.
- Public landing page shows the reward offer, merchant, supported Community accounts, and privacy-safe impact totals.
- Visitors cannot purchase, join, request, claim, or check out from the page.
- Public Donations campaigns appear in the merchant profile's existing Active Campaigns section.
- Merchant profiles receive a new Community tab aggregating campaigns and supported accounts.

### Merchant operations

- Search active users holding the Community role.
- Add, pause, remove, or reactivate a Community account on a campaign.
- Send one account a quantity.
- Send several accounts the same quantity.
- Send several accounts custom quantities.
- Bulk allocation is atomic and idempotent.
- Every quantity creates one individually owned canonical Microgift lifecycle.
- Merchant may recall only untouched rewards still owned by the original Community account.
- Existing in-app notifications are reused.
- Merchant Community Support dashboard route: `/merchant-community-support.php`.

## Canonical lifecycle

```text
Merchant reward inventory
→ Public Donations allocation operation
→ per-Community-user batch
→ individual wallet item
→ PPPM ownership item
→ Microgift instance
→ Community Inbox
→ existing regift flow
→ final recipient Inbox
→ claim
→ merchant redemption
```

## Implementation phases

1. `#1316` — Community multi-role foundation and master schema
2. `#1317` — Public Donations campaign foundation
3. `#1318` — Community search and campaign assignments
4. `#1319` — Single and bulk allocation engine
5. `#1320` — Untouched-reward recall controls
6. `#1321` — Merchant Community Support dashboard
7. `#1322` — Public campaign landing page
8. `#1323` — Merchant-profile Community tab and campaign cards
9. `#1324` — Governance and lifecycle hardening
10. `#1325` — Production QA, reconciliation, and deployment documentation

Each phase starts from the preceding merged integration head and should be a separate scoped PR.

## Documentation set

- `technical-blueprint.md` — architecture, schema, lifecycle, files, UI, permissions, reporting, privacy, and technical invariants
- `api-contracts.md` — endpoint request/response contracts, validation, idempotency, error rules, and notification contracts
- `phase-plan.md` — exact ten-phase scopes, branches, acceptance criteria, dependencies, and SQL expectations
- `qa-reconciliation.md` — acceptance fixture, lifecycle metrics, test matrix, concurrency tests, and repair requirements
- `deployment-runbook.md` — merge order, SQL import, feature rollout, smoke tests, rollback, and deployment-status rules

## First action in the new chat

Use this exact instruction:

> Take over Public Donations + Community Role v1 for `bigriversocial74/contactform`. Read every file in `docs/public-donations-community-v1/` from branch `feature/community-user-role-v1-20260724`. Verify the other agent's final work and the newest `integration-from-repair-20260628` head before changing code. Reconcile or rebuild Phase 1 from the latest integration head, then implement issue #1316 as a scoped PR. Do not merge without explicit approval. Clearly report SQL and deployment status.
