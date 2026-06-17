# Production Integration and Behavioral Validation

This phase adds database-backed behavioral tests that exercise canonical services against the same MySQL schema used by PR validation. These tests complement source-contract PHPUnit coverage by proving transaction behavior, idempotency, replay handling, reversals, and cleanup.

## Phase 1 — Stage 7 financial truth

Run:

```bash
composer test-money-behavior
```

The suite verifies:

- balanced debit and credit posting through `mg_ledger_post()`;
- exact replay returns the original transaction group;
- exact replay does not duplicate ledger entries;
- conflicting use of an existing idempotency key is rejected;
- unbalanced entries are rejected before persistence;
- reversal creates a separate balanced transaction group;
- the original transaction is marked reversed;
- one reversal link is persisted;
- reversal replay is idempotent;
- all fixtures are rolled back after validation.

The PHPUnit suite executes this behavioral command automatically when database environment variables are available. Local source-only PHPUnit runs skip the database-backed case rather than failing.

## Fixture policy

Behavioral suites must:

- use unique run identifiers;
- run inside an owning transaction whenever possible;
- roll back all fixtures;
- assert cleanup after rollback;
- call canonical domain services rather than reproducing business SQL;
- avoid provider network calls unless the suite is explicitly a provider sandbox test.

## Planned vertical suites

1. Checkout payment capture → Stage 7 ledger → PPPM issuance → entitlement grant.
2. Microgift issuance → send → claim → redemption → Action Center projection.
3. Refund → ledger reversal → entitlement review or revocation policy.
4. Subscription initial funding → activation → renewal → dunning.
5. Agent approval → action execution → failure reconciliation.
6. Swarm routing → Stage 16 delegation → review → completion.
