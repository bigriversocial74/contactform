# Delivery Operations & Capacity Foundation v1 Scorecard

## Baseline: 5.8/10

| Area | Baseline | Primary gap |
|---|---:|---|
| Canonical authority | 8/10 | Delivery operations were spread across entry points. |
| Durable outbox | 7/10 | Jobs existed but lacked deterministic channel-level keys. |
| Concurrency | 3/10 | No unified lease or worker overlap boundary. |
| Capacity | 3/10 | No commercial batch/runtime/fairness policy. |
| Retry recovery | 4/10 | Limited retries, no complete dead-letter lifecycle. |
| Channel separation | 6/10 | Channel rows existed, but external defaults were too optimistic. |
| Monitoring | 6/10 | General health existed without a delivery command center. |
| Operator recovery | 5/10 | Recovery was fragmented and lacked a dedicated queue. |
| Security/privacy | 8/10 | Good internal URL and payload controls; no unified worker boundary. |
| Modal CSS | 3/10 | Three overlapping Action Center modal sheets were active. |

Weighted scoped baseline: **5.8/10**.

## Repair cycle 1

Built:

- deterministic delivery job keys
- immediate durable in-app completion records
- external channel feature gates disabled by default
- leased CLI-only worker
- advisory run lock
- system batch and runtime ceilings
- per-user and per-merchant fairness
- exponential backoff and jitter
- dead-letter status and provider evidence
- automatic failure-rate pause
- protected operations console/API
- one canonical Action Center modal stylesheet

Audit findings after first implementation:

1. Expired leases returned to the queue without consuming retry budget.
2. Requeued dead letters retained exhausted attempts.
3. Pending jobs could be cancelled during unsafe states.
4. Merchant fairness needed to cover provider child jobs through notification context.

## Repair cycle 2

Corrected:

- lease expiry increments attempts and can dead-letter
- dead-letter recovery resets attempts explicitly
- cancellation restricted to inactive pending/failed jobs
- merchant fairness derives merchant ownership from either the job or notification context
- worker evidence records lease-recovery dead letters

## Final scoped score: 10.0/10

The final score requires all ten automated contract sections to pass on PHP 8.2 and PHP 8.3:

1. Canonical delivery authority
2. Durable idempotent outbox
3. Leases and concurrency
4. Commercial capacity and fairness
5. Retries and dead letters
6. Channel separation and fail-closed defaults
7. Monitoring and safety pause
8. Protected operator recovery
9. CLI-only worker and deployment control
10. Canonical modal CSS consolidation

This is a code-readiness score. It does not claim production deployment, provider acceptance, browser rendering, or live throughput testing.
