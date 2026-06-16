# Admin dashboard backend aggregation and UI foundation

## Scope

This phase replaces the placeholder list of administrative API links on `/account-admin.php` with a permission-aware, read-only operational dashboard. It aggregates existing platform authorities and does not create a parallel admin schema, financial authority, moderation system, or operational truth source.

## Canonical endpoint

`GET /api/admin/dashboard.php?window_days=30`

Accepted windows are bounded to 7–90 days. The endpoint uses the existing database-backed session and permission layer and inherits private, no-store API caching and security headers from `api/bootstrap.php`.

## Permission partitioning

The dashboard shell is available when the authenticated account has at least one existing administrative or operational permission. Each response section is independently gated:

- Platform identity and model counts: `admin.users.view` or `admin.users.manage`
- Audit activity: `admin.audit.view`
- Health, releases, incidents, and readiness checks: `admin.health.view`
- Security events: canonical `security.logs.view`, with compatibility for the previous `admin.security_logs.view` spelling
- Sessions: `admin.sessions.view`
- Operational alerts: `operational.alerts.view` or `admin.health.view`
- Demand orchestration aggregates: `demand.dashboard.view`, `intelligence.dashboard.view`, or `admin.health.view`
- Commerce aggregates: `merchant.payments.view`, `subscriptions.admin`, `microgift.operations.view`, or `tips.reverse`
- `super_admin` retains global access through the canonical Stage 1 permission helper

A permission grants visibility only to its corresponding section. It does not implicitly expose unrelated user, commerce, security, or operational records.

## Response contract

The read model contains:

- `meta`: generation time, bounded reporting window, health state, query count, and missing expected tables
- `access`: section capabilities for the current session
- `platform`: user, profile, model-request, storefront, product, and post aggregates
- `commerce`: paid order, volume, fulfillment, refund, dispute, subscription, tip, Microgift, claim, and redemption aggregates
- `operations`: alerts, incidents, security warnings, sessions, orchestration, and readiness-check aggregates
- `alerts`, `security`, `audit`, `checks`, `incidents`, and `release`: bounded recent projections
- `shortcuts`: links to existing protected administrative endpoints allowed for the session

The response does not expose raw metadata JSON, provider customer/payment references, password or session hashes, ledger entries, payment credentials, rollback plans, detailed security contexts, or unrestricted database rows.

## Read safety and query bounds

The service performs no inserts, updates, deletes, audit writes, events, alerts, session refreshes, financial postings, or moderation mutations. A complete super-admin read uses at most ten bounded database queries:

1. Existing-table capability lookup
2. Platform aggregate query
3. Commerce aggregate query
4. Operations aggregate query
5. Recent alerts
6. Recent security events
7. Recent audit activity
8. Recent readiness checks
9. Open incidents
10. Latest release

Missing optional tables degrade the relevant section without inventing substitute data.

## UI foundation

The existing `/account-admin.php` route remains canonical. The account shell now loads:

- `assets/css/admin-dashboard.css`
- `assets/js/admin-dashboard.js`
- `includes/account/admin-dashboard.php`

The UI provides responsive overview metrics, platform/commerce/operations sections, alerts, incidents, readiness checks, security events, audit activity, release state, reporting-window selection, refresh, and links to existing protected tools. It does not add admin mutation controls in this phase.

## Validation

The focused workflow validates:

- complete ordered schema application
- real-MySQL access partitioning and aggregation behavior
- no private metadata/provider leakage
- stable repeat reads and no write side effects
- bounded query count
- endpoint and UI contracts
- frontend contracts
- complete repository PHPUnit suite
- Playwright desktop and mobile browser boundaries

## Deferred

- User, role, and permission mutation UI
- Moderation queues and actions
- Alert acknowledgement or incident mutation UI
- Refund, dispute, subscription, tip, or Microgift mutation controls
- Custom dashboard layout persistence
- Long-range analytics warehouse or time-series charts
