# Stage 1–18 Full Forensic Audit

## Scope

This audit reviews the merged Stage 1–18 codebase by complete business flow rather than by isolated stage. The review covers identity and sessions, products and commerce, payments and ledger posting, entitlements, PPPM and Microgift lifecycle, claims and redemption, Action Center projections and messaging, merchant operations, tips and subscriptions, social and demand systems, agent execution and swarms, migrations, workers, and launch-readiness controls.

## Confirmed findings repaired in this audit

### F-001 — API bootstrap was not CLI-safe

`api/bootstrap.php` read `$_SERVER['REQUEST_METHOD']` directly while many CLI workers load domain helpers that transitively include the API bootstrap. In CLI execution the key may be absent, producing warnings and making validation scripts dependent on manually seeding the server variable.

Repair: bootstrap now normalizes a missing request method to `GET` before handling OPTIONS requests. Individual CLI scripts no longer need to patch the environment merely to load shared services.

Severity: medium.

### F-002 — Registration persisted the regenerated session twice

`mg_set_session_user()` regenerates the PHP session ID and records the resulting database-backed session. Registration called `mg_record_user_session()` again immediately afterward. The unique session hash prevented duplicate rows, but the second write duplicated security-sensitive work and obscured the single session-establishment authority.

Repair: registration delegates session persistence only to `mg_set_session_user()`.

Severity: low.

### F-003 — Legacy payment helper exposed a second ledger-writing authority

`api/payments/_payments.php` retained `mg_ledger_pair()`, which wrote directly to the legacy `financial_ledger_entries` table. The canonical Stage 7 authority posts balanced, idempotent transaction groups through `mg_ledger_post()` and the adapters in `api/finance/_posting.php`. Leaving the legacy writer callable created a future bypass around transaction-group idempotency, wallet accounts, reversals, and reconciliation.

Repair: the compatibility symbol remains available but fails closed with a clear exception. It no longer writes financial records. All payment and refund posting must use Stage 7.

Severity: high.

## Verified canonical boundaries

### Identity and access

- Protected APIs refresh the DB-backed session and reload current roles, permissions, and model assignments.
- Inactive accounts are rejected after session refresh.
- Write endpoints use CSRF verification.
- Login and registration are protected by IP and identifier rate limits.
- Session creation is centralized in `mg_set_session_user()` and session revocation is DB-backed.

### Commerce, payments, and financial truth

- Paid-order capture locks the order before transitioning payment state.
- Canonical paid-order and refund posting use Stage 7 transaction groups with stable idempotency keys.
- Payment webhook events detect conflicting signed payload replays.
- Fulfillment and PPPM issuance occur in the owning payment transaction.
- Entitlements are granted through PPPM issuance and are revoked or reviewed through refund policy.

### PPPM, Microgift, claim, redemption, and Action Center

- Microgift issuance, claim, and lifecycle projection occur inside the owning transaction.
- Stage 11 Action Center mutations delegate to canonical ownership, claim, and messaging services.
- Durable Action Center messaging uses canonical threads, participants, messages, notifications, and delivery jobs.
- Claim and redemption do not introduce alternate ownership authorities.

### Tips, subscriptions, social, and demand

- Tips delegate money movement to the canonical ledger authority.
- Stage 13B requires initial subscription funding before activation and distinguishes activation from renewal.
- Social visibility checks block, follow, and active subscription state on the backend.
- Stage 15B uses deterministic UTC half-open demand windows and excludes canceled and expired signals.

### Agents and swarms

- Stage 16 remains the sole action planning, approval, and execution authority.
- Stage 17 delegates executable work into Stage 16 and coordinates routing, dependencies, budgets, and review only.
- Failed actions reconcile parent workflow state.
- Review-required swarm tasks have an owner-scoped completion path.

### Operations and deployment

- The complete upgrade builder maintains explicit migration order and rejects unregistered SQL files.
- PR validation applies migrations to clean MySQL, runs smoke checks, lints all PHP, and executes security and PHPUnit suites.
- Stage 18 launch-readiness checks record operational results and fail on required readiness conditions.

## Residual risks and deferred work

These items require environment or product-policy decisions and are not silently changed by this audit:

1. Database-backed concurrency and replay tests should be expanded for payment capture, refunds, claims, subscriptions, and swarm workers. Existing coverage is strong on contracts but still includes many source-string assertions.
2. The legacy `financial_ledger_entries` table remains for historical compatibility. A future data-retention and migration decision should determine when it can be archived or removed.
3. Registration currently creates active accounts before email verification. This matches current product behavior but should be revisited if verified-email access becomes a launch requirement.
4. Partial refunds intentionally create entitlement review items instead of automatically calculating partial asset revocation. That is a declared policy boundary, not an implementation defect.
5. Production validation still requires real provider sandbox credentials, worker scheduling, email/SMS delivery verification, backup restoration, and operational runbook drills.

## Regression coverage added

`Stage1To18ForensicAuditTest` protects:

- CLI-safe bootstrap loading;
- single session persistence during registration;
- fail-closed legacy ledger behavior;
- canonical Stage 7 posting adapters;
- transaction-owned Microgift lifecycle projection.

## Conclusion

The merged Stage 1–18 foundation has coherent canonical authorities after the focused Stage 11–18 repairs and the findings corrected here. The most important remaining work is no longer foundational stage coding; it is deeper database-backed behavioral testing, production-provider integration, operational rehearsal, and product-level policy completion.
