# Admin Hardening Phase 1

Branch: `admin-hardening-phase-1`

## Scope

This phase focuses on low-overlap admin security hardening while other UI and feature work is being merged.

## Changes

- Added `includes/admin-auth.php` as the canonical refreshed page guard for server-rendered admin pages.
- Updated high-risk admin pages to use DB-refreshed session and permission checks before rendering:
  - `admin/users.php`
  - `admin/sessions.php`
  - `admin/audit-logs.php`
  - `admin/security-logs.php`
  - `admin-ai.php`
  - `admin-payments.php`
- Routed `api/admin/sessions.php` DELETE requests through the safer account-management controls:
  - CSRF required
  - rate limit required
  - reason required
  - self-management blocked
  - elevated-account protection enforced
  - transaction/audit/event/security logging applied
- Hardened admin settings APIs:
  - `api/admin/payment-settings.php` no longer returns raw exception messages on 500 errors.
  - `api/admin/ai-settings.php` now rate-limits reads/writes and validates provider/model payloads strictly.
- Added rate limits and no-store headers to audit/security log APIs.
- Added security-log filter validation.
- Added `microgift_claim_attempts` to commerce operations schema readiness because the summary query reads it.

## Follow-up phases

- Split commerce permissions by domain instead of using the broad legacy view gate.
- Deprecate old commerce endpoints after canonical queue/detail/action APIs are fully verified.
- Add full PHPUnit coverage for admin page guards and session revocation safety once the other active branches are merged.
