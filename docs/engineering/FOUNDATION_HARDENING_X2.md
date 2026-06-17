# Foundation Hardening X2

## Objective

Raise the existing Stage 1–11A foundation from a strong development baseline to a regression-resistant platform that can safely support Stages 12–18.

## Canonical ownership matrix

| Domain | Canonical owner | Non-authoritative consumers |
|---|---|---|
| Authentication and roles | Identity/auth services | Page templates and frontend state |
| Orders and payment truth | Commerce/payment services | Action Center, dashboards, notifications |
| Issued unit identity and ownership | PPPM | Inbox, Sent, Claimed, reports |
| Protected digital access | Entitlements | Product pages and Action Center |
| Gift lifecycle | Microgift template/instance services | UI projections |
| Merchant claim and redemption | Stage 10 claim services | Action Center and merchant history |
| Action Center | User-facing projection only | Must not create ownership or financial truth |
| Demo content | Super Admin presentation layer | Must never create transactional side effects |

## Stable contracts now enforced

- Cart uses `assets/js/cart.js` as the stable entrypoint.
- Public onboarding uses `assets/js/index-agentic-onboarding.js`.
- The Action Center uses `includes/gift-action-center.php` and `assets/js/gift-action-center.js`.
- Action Center demo content requires the `super_admin` role.
- Demo content covers Inbox, Sent, and Claimed but cannot create payments, transfers, claims, messages, tips, notifications, ledger entries, payouts, or webhooks.
- The LOAD drawer renders the content stack before the protected voucher.
- The authenticated application shell uses the shared Agent sidebar and header tabs.

## Duplicate and legacy classification rules

Every overlapping implementation must be classified before removal:

- `active` — current canonical implementation.
- `compatibility` — required temporarily by existing consumers.
- `migration-only` — retained solely for data upgrade or reconciliation.
- `deprecated` — no new callers permitted; removal scheduled.
- `safe-to-remove` — no callers, migrations, or runtime dependencies.
- `unknown` — requires investigation before modification.

## Known areas requiring continued inventory

1. Stage 5G claim helpers versus Stage 10 canonical redemption services.
2. Legacy Inbox and notification projections versus the Stage 11 Action Center.
3. Page-local headers, sidebars, modals, and drawers versus the shared application shell.
4. Early Tips, Feed, PSR, Agent, Admin, and reliability placeholders versus their official later stages.
5. Stage-specific migration and smoke scripts that remain necessary for upgrade compatibility.

## Regression gates

A change is incomplete until all applicable gates pass:

1. Composer metadata validation.
2. PHP syntax validation.
3. Stable frontend contract validation.
4. Clean-install database migration and stage smoke validation.
5. Security regression tests.
6. Complete PHPUnit suite.
7. Desktop and mobile browser smoke tests.

The browser workflow stores traces and screenshots when failures occur.

## Refactoring rule

A refactor may change private implementation details, but it may not silently alter accepted routes, DOM contracts, lifecycle states, ownership authority, redemption authority, API response shapes, or user-visible behavior. Contract changes require an explicit migration and updated consumers in the same pull request.

## Definition of done

The foundation-hardening pass is complete when both PR Validation and Browser Validation are green, the ownership matrix is current, rollback and restore procedures are documented, and no known canonical path has an unclassified competing implementation.
