# 03M — Stage 1 Repair Before Stage 2

## Purpose

This pass repairs the highest-priority gaps identified by the detailed Stage 1 plan-vs-execution audit before moving into Stage 2.

## Completed repairs

### 1. Audit log endpoint SQL mismatch fixed

Updated:

```text
api/admin/audit-logs.php
```

The endpoint now selects the real Stage 1 SQL columns:

```text
id
user_id
action
entity_type
metadata_json
ip_address
user_agent
created_at
```

It decodes `metadata_json` into a response field named `metadata` and no longer references missing `entity_id` or `metadata` columns.

### 2. Health endpoint added

Added:

```text
api/health.php
```

The endpoint checks the PHP runtime and database connectivity, then returns JSON status for install/server smoke testing.

### 3. Current-user profile endpoint added

Added:

```text
api/me/profile.php
```

The endpoint supports:

```text
GET   /api/me/profile.php
PATCH /api/me/profile.php
```

The PATCH flow requires authentication and CSRF, validates safe profile fields, updates `users.display_name` and `user_profiles`, and records audit/event entries.

### 4. Missing Stage 1 planned placeholder tables added through repair migration

Added:

```text
database/stage_1_repair_03M.sql
```

The migration adds:

```text
user_sessions
system_actors
platform_fee_settings
```

It also adds compatibility permissions required by the plan/audit:

```text
user.profile.update
admin.users.manage
admin.roles.manage
system.health.view
```

### 5. Smoke checklist updated

Updated:

```text
tests/stage_1_auth_smoke_checklist.md
```

The checklist now includes repair migration import, `/api/health.php`, `/api/me/profile.php`, audit endpoint schema validation, and explicit known deferrals.

## Remaining known deferrals

These remain intentionally deferred unless we run another Stage 1 repair pass:

```text
- Automated PHPUnit/browser tests
- Public email delivery
- Full DB-backed session persistence runtime
- Admin user detail endpoint
- Admin user status update endpoint
- Admin role assign/remove endpoints
- Formal rate limiting middleware
```

## Readiness recommendation

After importing both SQL files and passing the updated smoke checklist, Stage 1 should be ready to support Stage 2 planning.

Do not start Stage 2 until the real server/database smoke test passes.
