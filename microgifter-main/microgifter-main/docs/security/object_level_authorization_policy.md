# Object-Level Authorization Policy

Every future table that contains user, merchant, store, agent, gift, product, order, inbox, claim, payment, or program data must enforce object-level authorization.

Role checks are not enough.

## Required endpoint pattern

1. Authenticate the session.
2. Load the requested record by ID.
3. Confirm the record exists.
4. Confirm ownership, membership, merchant scope, or explicit permission.
5. Reject with 403 before returning private record data.
6. Write audit/security logs for denied access.

## Helper

Use `mg_require_owner_or_permission()` from `includes/authorization.php` for owner-or-permission checks.

## Required tests

Each future module must test:

- owner allowed
- unrelated user denied
- scoped manager allowed
- missing record does not leak private details
- denied access writes a security signal

## Carry-forward modules

This policy applies before implementing products, gifts, orders, claims, inbox, agents, stores, and PPPM/program workflows.
