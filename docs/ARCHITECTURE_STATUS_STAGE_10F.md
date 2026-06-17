# Microgifter Architecture Status — Stage 10F

## Purpose

This document is the current repository-level architecture index. It supersedes stale stage-status summaries but does not replace the original stage plans.

## Canonical systems

| Domain | Canonical authority | Must not be duplicated by |
|---|---|---|
| Identity and access | users, sessions, roles, permissions | page-local authorization or browser state |
| Commerce | carts, checkout drafts, orders, payment records | Microgift or Action Center records |
| Financial accounting | grouped double-entry ledger and payout records | UI totals or gift lifecycle tables |
| Issued-unit ownership | PPPM items and ownership events | inbox rows, claims, or entitlements |
| Protected access | entitlements and delivery grants | product pages or gift records |
| Gift lifecycle | Microgift templates, versions, and instances | legacy gifts or Action Center folders |
| Merchant redemption | merchant locations, claim codes, attempts, and redemptions | scanner output or client state |
| Customer activity | Action Center read model | ownership, payment, or redemption mutation |
| Asynchronous delivery | operational outbox | best-effort post-commit calls |

## Stage 10F corrections

- one canonical `mg_claim_execute_operation()` path;
- configurable rate policies resolved inside that path;
- operational outbox insertion inside the redemption transaction;
- typed lifecycle failure codes instead of exception-message parsing;
- explicit authorization for paid and non-commerce funding sources;
- immutable attempt audit rows separated from expiring security metadata;
- durable failed-attempt logging with server-side fallback logging;
- Action Center folder model aligned to INBOX, SENT, and CLAIMED;
- ordered Stage 10 migration runner and full-upgrade artifact builder;
- database-backed transaction and audit smoke tests in CI;
- repository README updated to the current stage.

## Quality scorecard

| Area | Before Stage 10F | Stage 10F target | Evidence |
|---|---:|---:|---|
| Architectural direction | 8.0 | 9.0 | canonical ownership map, single claim path, atomic outbox |
| Feature breadth | 8.5 | 8.8 | Action Center read contract and Stage 10 operational completion |
| Documentation discipline | 7.0 | 8.5 | current README, architecture index, state model, handoff |
| Runtime confidence | 5.5 | 7.5 | database integration tests, transactional smoke checks, typed errors |
| Deployment readiness | 5.0 | 7.5 | ordered migration runner, generated upgrade artifact, checksums, CI execution |

These are readiness targets, not production certification. Production readiness still requires successful CI, a staging deployment, backup/restore rehearsal, and end-to-end user acceptance testing.

## Remaining gates before production

1. Run Stage 10F CI successfully on a clean database.
2. Run the generated upgrade against a copy of the existing hosted database.
3. Verify rollback and restore procedures.
4. Complete the shared UI template work.
5. Build Stage 11 Action Center endpoints and pages without introducing another state engine.
6. Run end-to-end purchase/free pickup, send, receive, merchant redeem, and claimed-history flows.
