# Profile moderation UI foundation

## Scope

This phase adds a permission-aware profile moderation system on top of the existing public-profile status authority. It does not create a competing profile, user, content, storefront, or audit authority.

The foundation includes:

1. Moderation queue and filters
2. Profile case review workspace
3. Atomic moderation actions and owner appeals
4. Durable history and admin-dashboard integration

## Canonical routes

Moderator workspace:

`/account-profile-moderation.php`

Moderator APIs:

- `GET /api/admin/profile-moderation/queue.php`
- `GET /api/admin/profile-moderation/case.php?case_id=<case-id>`
- `POST /api/admin/profile-moderation/open.php`
- `POST /api/admin/profile-moderation/action.php`

Owner APIs:

- `GET /api/profiles/moderation.php`
- `POST /api/profiles/moderation-appeal.php`

## Authority model

The existing `public_profiles.status` field remains the sole public-profile availability authority:

- `draft`
- `active`
- `hidden`
- `suspended`

Public profile reads already reject hidden and suspended profiles. Owner profile updates already preserve the suspended state, preventing self-restoration. Moderation actions update that canonical status inside the same transaction as the case and action history.

New permissions:

- `admin.profiles.moderation.view`
- `admin.profiles.moderation.manage`

`super_admin` retains complete access through the existing permission bypass. Admin and super-admin roles receive both permissions through the Stage 18D migration.

## Section 1 — Queue

The queue supports:

- active, open, in-review, actioned, appealed, resolved, dismissed, and all-status views
- urgent, high, normal, and low priority
- moderation category
- assignment to the current moderator or unassigned cases
- search by profile name, slug, profile public ID, or case public ID
- bounded pagination of 1–50 cases
- urgent and unassigned summaries

Queue ordering prioritizes urgent cases, then appealed/open/in-review cases, then oldest open work.

A moderator may open a case using a profile slug or public profile ID. Duplicate active cases for the same profile and category are rejected transactionally.

## Section 2 — Case review

The case workspace includes:

- case summary, details, source, category, priority, status, assignment, and evidence
- profile identity, status, visibility, completion, owner status, and safe public link
- profile links and ordered custom sections
- storefront, product, and post counts from existing domain tables
- owner appeals
- durable moderation action history

The response excludes owner email, password data, provider state, wallet data, ledger data, and raw unrelated metadata.

## Section 3 — Actions and appeals

Moderator actions:

- claim
- internal note
- warning
- hide
- suspend
- restore
- escalate priority
- dismiss
- accept appeal
- deny appeal

Every write requires:

- an active authenticated user
- `admin.profiles.moderation.manage` or `super_admin`
- a valid CSRF token
- an allowed action and reason code
- a bounded reason string

Profile, case, appeal, and action-history updates occur in one database transaction. Restore-to-active checks the existing profile publish readiness before returning the profile to public availability.

Owners see the latest applicable restriction in the profile editor. Hidden or suspended profiles may submit one appeal for the restricting case. The owner cannot assign, action, restore, or dismiss a case.

## Section 4 — Audit and admin integration

Each case creation, moderator action, and appeal submission writes:

- a durable row in `profile_moderation_actions`
- an existing `audit_logs` entry
- an existing platform event

The admin dashboard adds:

- suspended-profile count
- active moderation-case count
- appeal count
- urgent-case count
- profile moderation shortcut
- attention status when urgent profile cases exist

No additional dashboard query is introduced beyond the existing platform aggregation query.

## Data model

`profile_moderation_cases` stores queue and review state.

`profile_moderation_actions` stores immutable action history, actor type, reason, and before/after profile status.

`profile_moderation_appeals` stores the owner statement and final decision.

All tables use public identifiers for browser-facing references and foreign keys to the canonical profile and user records.

## Safety

- No owner email or credentials in moderation responses
- No raw provider, payment, ledger, or wallet state
- No direct user-account suspension from profile moderation
- No owner self-restoration from profile suspension
- No unauthenticated moderation reads or writes
- CSRF protection on all writes
- Bounded filters, pagination, evidence, reasons, and appeal statements
- Parameterized SQL
- Transactional profile/case/action/appeal changes
- Existing public-profile visibility and block behavior remains authoritative
- Safe DOM projection in the new moderation controllers

## Validation

The focused workflow validates:

- PHP and JavaScript syntax
- complete ordered schema application
- real-MySQL case creation, duplicate prevention, queue visibility, case detail, suspension, public suppression, owner lockout, appeal, restoration, history, audit, and cleanup
- focused PHPUnit contracts
- frontend repository contracts
- complete repository PHPUnit suite
- moderator queue, review, action, mobile, and owner-appeal Playwright behavior
- complete browser regression suite

## Deferred

- Public user-report submission UI
- Automated classification and evidence ingestion
- SLA and reviewer workload analytics
- Content-level post or product moderation
- Account-level enforcement
- Multi-reviewer approval policy for high-risk actions
- Legal takedown workflow
- Profile discovery and search
