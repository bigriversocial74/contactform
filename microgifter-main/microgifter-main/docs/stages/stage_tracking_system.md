# Microgifter Stage Tracking System

This file defines the tracking system for comparing planned stage scope against actual implementation.

Use this system at the end of every stage and whenever an implementation decision changes the original plan.

## Core tracking documents

| File | Purpose |
|---|---|
| `docs/stages/stage_1_build_manifest.md` | Records what exists after Stage 1. |
| `docs/stages/stage_1_missing_items_review.md` | Records what still needs verification before Stage 2. |
| `docs/stages/stage_1_audit_and_review.md` | Audits Stage 1 against intended scope. |
| `docs/stages/stage_plan_delta_register.md` | Tracks differences between original plans and actual implementation decisions. |
| `docs/stages/stage_tracking_system.md` | Defines the process for future stages. |

## Required end-of-stage review process

At the end of each stage:

1. Read the stage implementation plan.
2. Review the actual repository files created or changed.
3. Update or create the stage build manifest.
4. Update or create the stage missing-items review.
5. Add any deviations to the delta register.
6. Mark every deviation as `Accepted`, `Needs Review`, `Temporary`, or `Rejected`.
7. Confirm whether the next stage can begin.

## Stage review template

For future stages, create:

```text
/docs/stages/stage_N_build_manifest.md
/docs/stages/stage_N_missing_items_review.md
/docs/stages/stage_N_audit_and_review.md
```

Each stage audit should include:

```text
1. Planned scope summary
2. Implemented scope summary
3. Exact files created/updated
4. Plan-aligned work
5. Intentional deviations
6. Accidental deviations or mistakes
7. Security impact
8. Database impact
9. API impact
10. UI/UX impact
11. Testing status
12. Carry-forward decisions
13. Stage readiness recommendation
```

## Deviation ID rules

Use IDs like:

```text
DELTA-001
DELTA-002
DELTA-003
```

Never reuse IDs.

If a deviation is resolved, keep the row and update the status rather than deleting it.

## Carry-forward decision rules

A deviation should be carried forward only when it improves one of these:

- Security
- Maintainability
- User onboarding
- Production readiness
- Code organization
- Stage continuity
- Developer clarity

A deviation should be rejected when it creates:

- Client-side-only security
- Duplicated code
- Unclear ownership
- Hidden technical debt
- Future-stage feature leakage
- Untracked schema changes

## Stage-gate rule

A stage is not considered complete until:

1. The code exists in GitHub.
2. The manifest is updated.
3. The missing-items review is updated.
4. Deviations are logged.
5. Smoke tests or review criteria are documented.
6. The next-stage readiness recommendation is written.

## Current Stage 1 carry-forward decisions

The following decisions are currently accepted for future stages:

- GitHub repo is the source of truth.
- PHP pages are the active app layer.
- Old HTML pages are prototypes until retired or moved to `docs/reference/`.
- `assets/css/microgifter.css` is the global design system.
- Large modules may use section CSS files.
- JS must be split by responsibility.
- `$page_scripts` should load page-specific JS from the shared footer.
- Permission-only UI can be hidden/shown client-side for UX, but backend APIs must enforce real authorization.
- First-run admin promotion should remain manual SQL until a secured admin bootstrap process is designed.

## Current Stage 1 stage-gate result

Stage 1 is ready for server/database smoke testing.

Stage 1 is not approved for public production launch until the smoke checklist passes on the target server and the open follow-up items are reviewed.
