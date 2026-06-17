# Stage 18 — Production Hardening and Launch Readiness

Stage 18 closes the Stage 1–18 build by adding production operations, release governance, retention execution, incident response records, deep readiness checks, and final architecture reconciliation.

## Release governance

Every staging or production release records:

- release version and exact Git commit SHA;
- target environment;
- validation evidence;
- deployment artifacts;
- mandatory rollback plan;
- approval and deployment actors;
- deployment, failure, and rollback timestamps.

Required gates are:

- Composer validation;
- PHP syntax validation;
- ordered migration generation;
- clean-install schema validation;
- security suite;
- full PHPUnit suite;
- browser smoke validation;
- verified backup;
- verified rollback plan;
- operational readiness checks.

Production gates cannot be waived. A release cannot be approved until every required gate passes.

## Incident operations

Operational incidents support SEV1 through SEV4 classification and the lifecycle:

`open → investigating → mitigated → resolved → closed`

Resolved or closed incidents may be reopened. Every transition creates an append-only incident event with the actor, note, prior state, new state, and structured payload.

## Retention

Retention policies are explicit, versioned operational records. The Stage 18 processor uses a strict table and timestamp-column allowlist and executes only approved delete policies in bounded batches.

Initial policies cover:

- security logs;
- delivery events;
- payment webhook events;
- Stage 16 agent execution events;
- Stage 17 swarm events.

Retention does not delete canonical financial truth, Microgift ownership, claims, redemptions, entitlements, subscriptions, tips, ledger entries, wallets, or incident/release records.

## Readiness checks

Deep readiness validates:

- supported PHP runtime;
- PDO MySQL availability;
- database connectivity;
- canonical Stage 1–18 tables;
- absence of unresolved SEV1 or SEV2 incidents;
- recent payment-webhook failures;
- stale agent workflow or swarm queues.

The public `/api/health.php` remains intentionally shallow and non-sensitive. Deep readiness is administrator-only.

## CI closure

The pull-request workflow now:

1. validates Composer and frontend contracts;
2. lints all PHP;
3. builds and uploads the complete ordered Stage 1–18 upgrade artifact;
4. applies the entire schema sequence through Stage 18;
5. runs every available stage smoke validator;
6. runs the launch-readiness validator;
7. runs security, PHPUnit, and browser smoke validation.

## Authority preservation

Stage 18 introduces no new business ownership or financial authority. All Stage 1–17 canonical boundaries remain intact. Operational records describe, validate, retain, deploy, and respond to the system; they do not replace domain services.

## Operational commands

```bash
php scripts/stage18.php
php scripts/stage18_smoke.php
php scripts/validate_launch_readiness.php
php scripts/run_retention.php
```

## Completion definition

Stage 18 is complete when:

- the complete Stage 1–18 schema applies cleanly;
- all smoke, security, PHPUnit, and browser checks pass;
- release gates are enforced;
- rollback and backup evidence are required;
- incident and retention workflows are operational;
- the final architecture reconciliation identifies no duplicate canonical authority.
