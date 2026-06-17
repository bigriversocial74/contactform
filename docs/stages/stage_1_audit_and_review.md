# Microgifter Stage 1 Audit and Review

This audit compares the current Stage 1 repository state against the intended Stage 1 foundation work and records where the implementation expanded or adjusted the plan.

## Audit source files

Primary Stage 1 source files:

- `docs/stages/Microgifter_Stage_1_Backend_Implementation_Plan_v1.docx`
- `docs/stages/stage_1_build_manifest.md`
- `docs/stages/stage_1_missing_items_review.md`
- `docs/stages/stage_plan_delta_register.md`

Important note: the Stage 1 plan is currently stored as DOCX. The GitHub connector can fetch the DOCX file by path, but audit readability will improve if the plan is also exported to Markdown later.

## Executive summary

Stage 1 has been executed as a broader identity/onboarding foundation than a narrow backend-only auth pass.

The core Stage 1 identity goals are represented in the repository:

- Users
- User profiles
- Roles
- Permissions
- Role permissions
- User roles
- Sessions
- Password reset token structure
- Email verification token structure
- Audit logs
- Events
- Auth endpoints
- Role and permission endpoints
- Admin users and audit log endpoints

The implementation also added several architectural decisions earlier than the strict stage plan:

- Active PHP pages instead of HTML-only pages
- Shared PHP includes
- Shared CSS design system
- Section stylesheets
- JS module split
- Onboarding shell for builder/test-agent flow
- Permission-aware agent workspace shell
- Server preflight/security documentation
- Sensitive file access policy

These additions are useful and should be carried forward, but they are now formally tracked in `stage_plan_delta_register.md` so future stages do not lose context.

## Planned Stage 1 scope status

| Planned area | Current status | Notes |
|---|---|---|
| Users | Implemented | SQL and auth endpoints include user creation/sign-in flow. |
| User profiles | Implemented | Registration creates a baseline profile row. |
| Roles | Implemented | Roles table and seeded roles exist. |
| Permissions | Implemented | Permissions table and permission checks exist. |
| Role permissions | Implemented | Permission resolver uses role-permission assignments. |
| User roles | Implemented | Registration assigns default customer role. |
| Sessions | Implemented | PHP session-based auth is active. |
| Password reset token structure | Implemented | Endpoint and schema are present; email delivery still pending. |
| Email verification token structure | Implemented | Endpoint and schema are present; email delivery still pending. |
| Audit logs | Implemented | Audit helper and admin audit endpoint exist. |
| Events | Implemented | Event helper and events table exist. |
| Auth API endpoints | Implemented | Register, login, logout, me, password, and email endpoints exist. |
| Admin users endpoint | Implemented | Permission-gated admin users endpoint exists. |
| Admin audit endpoint | Implemented | Permission-gated audit log endpoint exists. |
| Smoke checklist | Implemented | Manual checklist and cURL examples exist. |
| Install documentation | Implemented | Installation, first-run admin, preflight, and security docs exist. |

## Intentional deviations from the strict plan

The following changes were intentional and should be preserved unless later review says otherwise:

1. **PHP-first active app**
   - Active app pages were created as `.php` instead of continuing with `.html`.
   - Reason: server-side auth state, CSRF, permissions, includes, and future secure rendering.

2. **GitHub as source of truth**
   - The workflow moved away from ZIP-first builds.
   - Reason: repository state must be canonical.

3. **Universal CSS and section CSS**
   - A global `microgifter.css` plus section CSS files were created early.
   - Reason: avoid scattered inline styles and prepare for larger modules.

4. **JS module structure**
   - Shared JS modules were split by responsibility.
   - Reason: avoid inline scripts and reduce future refactor cost.

5. **Builder/agent onboarding shells**
   - A lightweight builder page and agent workspace shell were included.
   - Reason: onboarding depends on test builder/test agent flow.
   - Boundary: no full product/gift/claim backend has been implemented yet.

6. **Manual first-run admin promotion**
   - No public admin bootstrap endpoint was added.
   - Reason: safer security model.

7. **Deployment preflight/security docs**
   - Added before Stage 2.
   - Reason: server/database testing is the next blocker.

## Open follow-up items before Stage 2

These are not all blockers, but should be addressed before real users or public launch:

1. Run the Stage 1 smoke checklist on the target server/database.
2. Confirm `database/stage_1_identity.sql` imports cleanly.
3. Confirm `MG_` environment values load correctly.
4. Confirm secure PHP session behavior on HTTPS.
5. Confirm `.htaccess` blocks sensitive folders/files on the target host.
6. Add rate limiting for login, register, password reset, and email verification.
7. Add email delivery for password reset and email verification.
8. Add automated tests after runtime is confirmed.
9. Decide when to move old `index.html`, `build.html`, and `agent.html` fully under `docs/reference/`.
10. Revisit `commerce.js` / `programs.js` naming when connector restrictions are no longer relevant.
11. Add a Content Security Policy once external scripts, fonts, payment integrations, and maps are known.

## Audit conclusion

Stage 1 is not just a backend identity pass anymore. It is now a secure identity, onboarding, layout, and deployment-preflight foundation.

That adjustment is acceptable because the project is not published yet and the repo is now being formatted correctly from the start.

The key requirement going forward is discipline:

- Keep feature implementation inside the correct stage.
- Track every deviation in the delta register.
- Do not allow frontend UI gates to replace backend permission checks.
- Do not start Stage 2 until Stage 1 smoke tests pass on the real server/database.
