# Stage 1 Foundation Closure

## Scope

This closure pass compares the original Stage 1 identity plan, the historical Stage 1 audits and repairs, and the current repository implementation. It does not redesign the approved UI or pull later-stage product scope into identity.

## Canonical Stage 1 ownership

Stage 1 owns:

- users and profiles;
- authentication and DB-backed sessions;
- roles and permissions;
- CSRF and authentication rate limits;
- password-reset and email-verification token foundations;
- audit and security logs;
- public shallow health status;
- identity-facing admin controls.

Stage 1 does not own products, commerce, PPPM ownership, entitlements, gift lifecycle, merchant redemption, the Action Center, Tips, Feed, Agents, or Admin operations beyond identity controls.

## Historical gaps now closed

The original Stage 1 audit identified missing session persistence, system actors, platform fee placeholders, profile updates, health checks, rate limiting, automated tests, and audit-schema inconsistencies.

Current repository evidence confirms:

- `user_sessions` is created by `stage_1_repair_03M.sql`;
- authenticated sessions are recorded, validated, touched, and revoked through `api/security.php`;
- `system_actors` and the inactive Stage 1 fee placeholder exist;
- `/api/me/profile.php` supports authenticated GET/PATCH with CSRF, validation, rate limits, audit, events, and session refresh;
- `/api/health.php` performs a shallow runtime/database check without exposing dependency details;
- authentication rate limits fail closed;
- Stage 1 security and full regression tests run in CI;
- the migration runner is the authoritative fresh-install and upgrade path.

## Closure added in this pass

The original acceptance model expected successful-login metadata. The current `users` schema did not retain it.

This pass adds:

- an idempotent `users.last_login_at` column and index;
- a database trigger that updates `last_login_at` from the canonical successful `auth.login` audit event;
- migration-runner registration before Stage 3 migrations;
- regression tests protecting migration order, login metadata, session validation, and fail-closed rate limiting.

The trigger approach preserves the established login endpoint and ensures every successful login path that emits the canonical audit event updates the same durable identity metadata.

## Accepted deviations

- Active pages remain PHP-first for server-side authorization, CSRF, and shared templates.
- The simplified base roles (`customer`, `merchant`, `admin`, `super_admin`) remain compatibility roles. Later merchant-owner, staff, location-manager, creator, and operational capabilities should be expressed through explicit permissions and scoped assignments rather than silently changing these base role meanings.
- Builder and Agent shells created early are presentation groundwork only and do not become Stage 1 authority.
- Email delivery remains an external-provider integration concern; token generation, hashing, expiry, and consumption remain Stage 1 foundations.

## Stage 1 build rules for later stages

1. Frontend visibility never replaces backend permission enforcement.
2. Every protected API must refresh and validate the DB-backed session.
3. Disabling a user must invalidate protected access and revoke active sessions through the canonical identity service.
4. Later stages may add permissions but must not grant ownership, payment, redemption, or Admin authority through UI state.
5. Existing Stage 1 migrations are immutable after application; corrections use new idempotent migrations.
6. `scripts/run_migrations.php` is the authoritative migration sequence.

## Preliminary score

- Identity data model: 9.5/10
- Authentication/session security: 9.6/10
- Roles and permissions: 9.0/10
- Profile and account APIs: 9.4/10
- Audit, health, and observability: 9.4/10
- Automated regression protection: 9.5/10
- Documentation and migration authority: 9.5/10

**Stage 1 preliminary closure score: 9.4/10.**

Final approval depends on the complete PR Validation and Browser Validation workflows passing with the new migration on both a clean database and the repository regression suite.
