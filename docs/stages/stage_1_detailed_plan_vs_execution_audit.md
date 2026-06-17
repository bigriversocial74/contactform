# Stage 1 Detailed Plan vs Execution Audit

This audit compares the Stage 1 implementation plan against the current repository execution after the 03A-03L build passes.

## Source Inputs

- Stage plan: `docs/stages/Microgifter_Stage_1_Backend_Implementation_Plan_v1.*`
- Execution manifest: `docs/stages/stage_1_build_manifest.md`
- Missing-items review: `docs/stages/stage_1_missing_items_review.md`
- Delta register: `docs/stages/stage_plan_delta_register.md`
- SQL schema: `database/stage_1_identity.sql`
- API bootstrap and auth/admin endpoints under `api/`
- Smoke checklist: `tests/stage_1_auth_smoke_checklist.md`

## Executive Result

Stage 1 is partially complete as a staged PHP/MySQL identity foundation and onboarding shell. It covers the highest-value identity foundations: registration, login, logout, current-user session refresh, baseline profile creation, default customer role assignment, role/permission catalog, permission-protected admin users/audit endpoints, audit/event helper functions, CSRF enforcement, shared PHP page structure, environment config, and install/security docs.

Stage 1 is not yet fully complete against the original Stage 1 plan because several planned items are missing, incomplete, or implemented differently:

- `user_sessions` table is listed in the manifest/checklist but is not present in `database/stage_1_identity.sql`.
- `system_actors` table is planned but not present.
- `platform_fee_settings` placeholder table/seed is planned but not present.
- Profile update endpoint `PATCH /api/me/profile` is planned but not present.
- Admin user detail, status update, role assign, and role remove endpoints are planned but not present.
- `/api/health` is planned but not present.
- Email verification resend endpoint is planned as `/api/auth/email/resend`, while the implementation has `verify-request.php` naming.
- Password reset and email verification token storage exists, but email delivery is intentionally not implemented.
- Rate limiting is required by the plan, but only documented as a follow-up.
- Automated acceptance tests are required by the plan, but only manual smoke docs and cURL examples currently exist.
- The SQL and audit endpoint are currently inconsistent: `audit_logs` defines `metadata_json`, but `api/admin/audit-logs.php` selects `metadata`; it also selects `entity_id`, which the SQL does not define.

## Plan vs Execution Matrix

| Plan Requirement | Execution Status | Evidence / Notes | Recommendation |
|---|---|---|---|
| User registration | Implemented | `api/auth/register.php` validates email/full name/password, hashes password, creates user, assigns default role, creates profile, sets session, audits/events. | Keep; add email verification token creation before production. |
| Login | Implemented | `api/auth/login.php` verifies password, blocks inactive users, loads roles/permissions, writes audit/event. | Keep; add rate limiting and update `last_login_at` if SQL supports it. |
| Logout | Implemented | `api/auth/logout.php` was hardened in 03H and clears session. | Keep; add user_sessions revocation if table is added. |
| Current user `/api/me` | Implemented as `/api/auth/me.php` | Current user endpoint refreshes roles/permissions from DB. | Decide whether to add canonical alias `/api/me.php` for plan compatibility. |
| Password reset | Partially implemented | Token table and endpoints exist. Email delivery not implemented. | Keep endpoints; add mail provider integration later. |
| Email verification | Partially implemented | Token table and verify endpoint exist. Resend naming differs from plan; email delivery not implemented. | Add `/api/auth/email/resend.php` or alias to `verify-request.php`. |
| Baseline profile creation | Implemented minimally | Registration creates `user_profiles` row. | Expand profile fields and update endpoint in Stage 2 or Stage 1 patch. |
| Profile update `PATCH /api/me/profile` | Missing | Plan requires it; current manifest does not list endpoint. | Add before final Stage 1 closure or explicitly move to Stage 2 with delta. |
| Roles | Implemented but simplified | SQL seeds `customer`, `merchant`, `admin`, `super_admin`; plan wanted `creator`, `merchant_owner`, `merchant_staff`, `location_manager`, `admin`, `super_admin`. | Add full planned role catalog or log narrowing as accepted delta. |
| Permissions | Implemented but renamed/simplified | SQL seeds `agent.*`, `messages.read`, `merchant.manage`, `admin.users.view`, `admin.audit.view`; plan expected `users.read.self`, `users.update.self`, `profiles.read`, `profiles.update.self`, `admin.users.read`, `admin.users.update_status`, `admin.roles.manage`, `admin.audit.read`. | Normalize names before Stage 2 depends on them. |
| Permission resolver | Implemented | `mg_load_user_auth`, `mg_require_api_user`, `mg_api_user_has_permission`, `mg_require_permission`. | Keep; consider not granting all permissions to every `admin` unless intended. |
| Auth middleware | Implemented as bootstrap helper | PHP functions protect API endpoints. | Accept for current non-framework PHP architecture. |
| Permission middleware | Implemented as bootstrap helper | `mg_require_permission()` blocks admin endpoints. | Accept for current architecture. |
| Audit logging service | Implemented as helper | `mg_audit()` inserts audit rows and does not break user-facing request. | Keep; fix schema/endpoint column mismatch. |
| Event service | Added early | `mg_event()` and `events` table exist, although original Stage 1 table list focused on audit and system actors. | Accept as useful cross-stage addition. |
| Admin user list | Implemented | `/api/admin/users.php` requires `admin.users.view`. | Keep; align permission naming. |
| Admin user detail | Missing | Plan requires `GET /api/admin/users/{id}`. | Add endpoint or log as deferred. |
| Admin user status update | Missing | Plan requires `PATCH /api/admin/users/{id}/status`. | Add before Stage 1 final or defer with explicit delta. |
| Admin role assign/remove | Missing | Plan requires assign/remove endpoints. | Add before Stage 1 final or defer with explicit delta. |
| Admin audit logs | Implemented but has likely SQL bug | Endpoint requires permission but selects columns that do not match current SQL. | Fix immediately before server smoke testing. |
| Health endpoint | Missing | Plan requires `GET /api/health`. | Add small endpoint before deployment testing. |
| `user_sessions` table | Missing | Manifest/checklist mention it, but SQL does not define it. | Add table or update manifest/checklist to accurately reflect session strategy. |
| `system_actors` table | Missing | Required by plan but not SQL. | Add table/seed or explicitly defer. |
| `platform_fee_settings` placeholder | Missing | Required by plan but not SQL. | Add placeholder table/seed or explicitly defer. |
| CSRF protection | Implemented | Write endpoints call `mg_require_csrf_for_write()`. | Keep; verify actual token rendering in forms. |
| Rate limiting | Missing | Plan requires 429 behavior; missing-items review lists rate limiting as follow-up. | Add before public traffic. |
| Hashed secrets | Mostly implemented | Passwords use `password_hash`; reset/verify token tables store `token_hash`. | Keep; verify endpoint implementation writes hash only. |
| Suspended/disabled account login block | Partially implemented | Login blocks non-active status. SQL has `active`, `disabled`, `pending`; plan wanted `pending_verification`, `active`, `suspended`, `disabled`. | Align enum/status values. |
| Tests | Not complete | Manual smoke checklist exists; automated tests do not. | Add automated tests once runtime confirmed. |
| Completion report | Partially implemented | Multiple build notes, manifest, missing-items review, and audit docs exist. | Keep; this audit strengthens Stage 1 reporting. |

## Key Scope Deviations

### Accepted deviations

1. PHP-first page architecture was introduced early because the platform is not published yet and server-rendered auth/CSRF/permissions are safer than long-term static HTML.
2. A shared CSS/JS architecture was added early to avoid duplicated inline code and prepare for future stages.
3. GitHub became the source of truth, with ZIPs treated as optional deployment artifacts only.
4. Server preflight/security docs and `.htaccess` protection were added early to support safer cPanel/Apache deployment.

### Needs-review deviations

1. Builder/onboarding shell was introduced early, even though Stage 1 is backend identity. Keep it lightweight until product/gift stages.
2. Agent workspace shell was introduced early. It must remain a permission-aware shell only; no inbox/product/gift/agent automation data should be built until later stages.
3. Role and permission names diverged from the plan. This is the most important future-stage compatibility risk.
4. The current SQL does not include some planned Stage 1 tables. Either add them or update source-of-truth deltas before Stage 2.

### Temporary deviations

1. Original HTML prototypes remain in root while PHP pages become active.
2. Email delivery is not connected.
3. Rate limiting is not implemented.
4. Automated tests are not implemented.

## Stage 1 Acceptance Test Review

| Acceptance Test | Current Status | Notes |
|---|---|---|
| ST1-001 Register User | Mostly satisfied | User/profile/user_role created, audit/event written. Email verification token may not be created at registration. |
| ST1-002 Duplicate Email Blocked | Satisfied | Registration checks existing email and returns 409. |
| ST1-003 Login Success | Partially satisfied | Session is created through PHP session; `last_login_at` is not present in SQL and not updated. |
| ST1-004 Login Failure | Mostly satisfied | Wrong password returns 401 and writes audit. No rate limit yet. |
| ST1-005 Logout | Partially satisfied | PHP session clears; `user_sessions.revoked_at` cannot be set because user_sessions table is missing. |
| ST1-006 Password Reset | Needs server verification | Token table exists; endpoint exists; email delivery not implemented. |
| ST1-007 Email Verification | Needs server verification | Token table and endpoint exist; delivery/resend flow not plan-matched. |
| ST1-008 Profile Update | Not satisfied | `PATCH /api/me/profile` is missing. |
| ST1-009 Admin User Status | Not satisfied | Status endpoint missing. |
| ST1-010 Role Assignment | Not satisfied | Assign/remove role endpoints missing. |
| ST1-011 Permission Enforcement | Satisfied for existing admin endpoints | Admin users/audit endpoints require permission. Need server smoke test. |
| ST1-012 Regression Smoke | Partially satisfied | Docs exist; not yet executed on server. |

## Immediate Fix List Before Stage 2

### Critical fixes

1. Fix `api/admin/audit-logs.php` to use current SQL columns: `metadata_json`, not `metadata`; remove or add `entity_id` consistently.
2. Add `GET /api/health`.
3. Add `PATCH /api/me/profile` or explicitly move it to Stage 2 with a documented delta.
4. Resolve the `user_sessions` strategy: add the table and write session rows, or update all docs/checklists to state PHP sessions are the temporary Stage 1 session strategy.
5. Normalize role/permission names or document the new naming standard before Stage 2.

### Important but can follow server smoke setup

1. Add admin user detail/status/role endpoints.
2. Add system actors and platform fee settings placeholders.
3. Add rate limiting.
4. Add email delivery integration.
5. Add automated tests.

## Audit Rating

Stage 1 current execution score against the original strict plan: **7.2 / 10**.

Breakdown:

- Scope control: 7/10 — No commerce/gift/wallet backend was built, but UI/onboarding/agent shells were added early.
- Security: 7/10 — Strong foundations exist, but rate limiting, session table revocation, and email delivery are incomplete.
- Data model: 6/10 — Core tables exist, but `user_sessions`, `system_actors`, and `platform_fee_settings` are missing; statuses/permissions diverge.
- API quality: 7/10 — Main auth endpoints exist; several admin/profile endpoints are missing; one audit endpoint likely has a SQL mismatch.
- Tests: 5/10 — Manual smoke docs exist, automated tests do not.
- Documentation: 9/10 — Strong manifest, install/security docs, delta register, and now detailed audit exist.

## Recommendation

Do not start Stage 2 implementation until a short Stage 1 repair pass is completed and then smoke-tested on the real server.

Recommended next pass:

`03M_microgifter_stage1_repair_before_stage2`

Scope:

1. Fix audit log endpoint SQL mismatch.
2. Add health endpoint.
3. Add profile update endpoint or explicitly defer it to Stage 2.
4. Add missing planned placeholder tables or update the delta register with a clear defer decision.
5. Add role/permission naming compatibility aliases or normalize names.
6. Update smoke checklist after repairs.
