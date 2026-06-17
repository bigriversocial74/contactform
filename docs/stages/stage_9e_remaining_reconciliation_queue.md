# Remaining Reconciliation Queue Before Stage 10

Stage 9E-1 fixes the highest-risk source-of-truth issue: PPPM ownership and redemption.

The following items remain queued but should be smaller after this PR:

1. **Stage 9E-2 — Event registry enforcement**
   - Add a lightweight test that scans emitted `mg_event()` names against `docs/contracts/event_catalog_stage1_9.yaml`.
   - Classify audit events, domain events, entity lifecycle events, and analytics source facts.

2. **Stage 9E-3 — API contract enforcement**
   - Add a route inventory test for Stage 1–9 endpoints.
   - Move high-risk request/response examples into the API contract registry.

3. **Stage 9E-4 — Initial install migration manifest**
   - Since no production install exists yet, document the one clean installation command sequence and make CI match it.
   - Avoid destructive legacy migration complexity unless real imported data appears.

4. **Stage 9E-5 — Behavioral workflow tests**
   - Add database-backed tests for paid order → PPPM → Microgift claim → entitlement transfer → redemption.
   - Add concurrency tests for duplicate claim and redemption idempotency.

5. **Stage 9E-6 — Stage 1–9 final closeout score**
   - Re-score database, API, event, security, PPPM, entitlement, commerce, and Future Demand readiness after the reconciliation PRs.

Stage 10 should begin only after the critical source-of-truth and contract baselines are green.
