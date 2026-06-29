# Training Campaign Lab Build Readiness Checklist

## Purpose

This checklist determines whether the Training Campaign Lab documentation package is ready for a future implementation phase.

This is a documentation-only checklist. It does not approve code creation by itself.

## Current mode

```text
Documentation only
No PHP implementation
No SQL implementation files
No CSS or JavaScript implementation files
No runtime folders
No validation scripts
No changes to existing Local Quest behavior
```

## Required before any future build

A future build may start only after all items below are true.

### 1. User approval

```text
[ ] User explicitly says to build code.
[ ] User confirms the target branch.
[ ] User confirms whether the build remains isolated from main.
[ ] User confirms whether SQL files may be created as executable artifacts.
[ ] User confirms whether UI files may be created.
```

Acceptable approval examples:

```text
Now build the code.
Start implementation.
Create the PHP files.
Build Stage 1.
```

Not approval:

```text
What else do we need?
Write the docs.
Make the plan.
Outline the stages.
What should the agents do next?
```

### 2. Documentation package complete

```text
[ ] Product requirements are complete.
[ ] MVP scope is complete.
[ ] Stage build plan is complete.
[ ] Route map is complete.
[ ] UI layout spec is complete.
[ ] Component inventory is complete.
[ ] Responsive rules are complete.
[ ] UI/data map is complete.
[ ] Schema design is complete.
[ ] Status model is complete.
[ ] Data lifecycle is complete.
[ ] Security and permissions are complete.
[ ] Admin workflows are complete.
[ ] Participant workflows are complete.
[ ] QA test script is complete.
[ ] Open questions are reviewed.
```

### 3. Open questions resolved or deferred

Before implementation begins, every open question must be marked as one of:

```text
Decided
Deferred
Blocked
Not required for MVP
```

No implementation should begin while important decisions are still marked:

```text
Open
Proposed
Needs owner decision
```

### 4. Protected files confirmed

The build agent must verify the protected Local Quest files list before starting code.

Protected files must not be modified unless the user explicitly approves it:

```text
examples/local-quest-rewards/index.php
examples/local-quest-rewards/wallet.php
examples/local-quest-rewards/quests.php
examples/local-quest-rewards/admin.php
examples/local-quest-rewards/admin-portal.php
examples/local-quest-rewards/admin-quest-controls.php
examples/local-quest-rewards/quest-controls.php
examples/local-quest-rewards/storage-sql.php
examples/local-quest-rewards/webhook.php
```

### 5. Future build scope confirmed

The first future code phase should be limited to the smallest safe vertical foundation:

```text
Stage 1: Static Training Lab shell only
No database writes
No proof uploads
No reward issuing
No changes to existing Local Quest pages
```

### 6. QA gate agreed

Each future stage must include a QA gate before the next stage begins.

Minimum QA gate:

```text
[ ] New routes load.
[ ] Existing Local Quest routes still load.
[ ] No protected files changed.
[ ] No production keys or secrets added.
[ ] No real reward issuing is triggered by demo/test flows.
[ ] Docs are updated if behavior changes.
```

## Build readiness result

Current status:

```text
Not ready to build code.
Docs-only planning package is still the approved scope.
```

Build can begin only when the user explicitly changes the scope from documentation to implementation.
