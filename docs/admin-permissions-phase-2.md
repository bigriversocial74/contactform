# Admin Permissions Phase 2

Branch: `admin-permissions-phase-2`
Base: `admin-hardening-phase-1`

## Goal

Tighten admin authorization without touching the sidebar/template work happening in parallel.

## Canonical helper

`includes/admin-permission-matrix.php` now defines the shared permission matrix for:

- Admin page permission expectations.
- Admin commerce domain read permissions.
- Admin commerce action permissions.
- Transitional aliases for legacy permission slugs.

This file is UI-neutral. Sidebar/template code can consume it later, but this phase only uses it for backend/API gates and tests.

## Commerce read split

The commerce queue and inspect APIs now use domain-aware gates:

| Domain | Permission |
| --- | --- |
| all | `admin.commerce.view` |
| order | `admin.commerce.orders.view` |
| refund | `admin.commerce.refunds.view` |
| dispute | `admin.commerce.disputes.view` |
| subscription | `admin.commerce.subscriptions.view` |
| tip | `admin.commerce.tips.view` |
| microgift | `admin.commerce.microgifts.view` |
| case | `admin.commerce.cases.view` |

`all` remains intentionally broad. A user with only one domain permission must request that domain instead of seeing the full queue.

## Commerce action split

| Action | Permission |
| --- | --- |
| open/assign/note/resolve/dismiss/reopen case | `admin.commerce.cases.manage` |
| reverse tip | `admin.commerce.tips.reverse` |

Legacy aliases remain in the matrix during the transition:

- `admin.commerce.manage` can still satisfy case-management checks.
- `tips.reverse` can still satisfy tip reversal checks.
- legacy read permissions map only to their related domains.

## Migration

`database/stage_18l2_admin_commerce_permission_split.sql` seeds the new permission slugs and assigns them to `admin` and `super_admin` roles. It is registered immediately after Stage 18L in `config/migrations.php`.

## Follow-up

- Once sidebar/template work lands, update visible nav checks to read from the same matrix.
- After production roles are fully migrated, remove or narrow legacy aliases.
- Add runtime integration tests for users with only a single commerce-domain permission.
