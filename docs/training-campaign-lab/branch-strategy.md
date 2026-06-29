# Training Campaign Lab Branch Strategy

## Purpose

This document defines how the Training Campaign Lab work should be handled in Git so the original Loyalty Quest / Local Quest Rewards example remains protected.

## Branch rule

```text
local-quest-workspace = duplicate/fork workspace for Loyalty Quest evolution
main = production-safe / original repo branch
```

Training Campaign Lab must stay isolated on `local-quest-workspace` until it is intentionally reviewed and approved for another branch.

## Strategic intent

The Training Campaign Lab is not a replacement for Loyalty Quest.

It is a separate product experiment that duplicates and extends the Loyalty Quest concept into a broader proof-of-action training campaign platform.

The branch should preserve the existing quest flow while allowing a new module to evolve beside it.

## Branch responsibilities

### main

```text
Original project branch
Production-safe baseline
No Training Campaign Lab experiments unless intentionally merged later
No accidental overwrites from the duplicate workspace
```

### local-quest-workspace

```text
Duplicate Loyalty Quest workspace
Training Campaign Lab documentation
Training Campaign Lab UI planning
Training Campaign Lab static shell
Future Training Campaign Lab schema and MVP build
Experimental proof-of-action reward campaign work
```

## Non-destructive build rules

Do not:

```text
Replace the existing Local Quest files
Rename the existing Local Quest app
Delete quest files
Rewrite shared quest storage/auth/config casually
Merge into main without review
Pull unrelated main changes into this branch without approval
```

Allowed:

```text
Add new training-* files
Add docs under docs/training-campaign-lab/
Add assets under examples/local-quest-rewards/assets/training-lab.*
Reuse existing quest helper functions when safe
Add SQL files under examples/local-quest-rewards/database/
Add validation scripts under scripts/
```

## Naming rules

New Training Campaign Lab files should use the `training-*` or `admin-training-*` naming pattern.

Examples:

```text
training-lab.php
training-campaigns.php
training-campaign-detail.php
training-sequence.php
training-proof-upload.php
training-rewards.php
training-profile-wallet.php
admin-training-review.php
admin-training-participants.php
admin-training-receipts.php
admin-training-settings.php
admin-training-templates.php
admin-training-builder.php
admin-training-reward-rules.php
admin-training-audit-logs.php
training-campaign-data.php
assets/training-lab.css
assets/training-lab.js
database/training_campaign_lab.sql
```

Avoid ambiguous names such as:

```text
new-app.php
campaign.php
admin-new.php
style2.css
script-new.js
```

## Shared code policy

Existing shared code can be reused when it avoids duplication and does not change existing behavior.

Safe reuse examples:

```text
app.php config loader
session boot
HTML escaping helper
current user lookup
existing Local Quest user/wallet identity concepts
existing Microgifter API call pattern
```

Higher-risk reuse examples that require extra caution:

```text
quest completion logic
reward issue logic
webhook handling
storage save/load behavior
admin authentication behavior
install scripts
```

## Preferred pattern

When extending behavior, prefer this order:

```text
1. Add a new Training Lab wrapper/helper file
2. Reuse existing helper functions where safe
3. Keep existing quest pages unchanged
4. Add Training Lab-specific SQL/schema later
5. Only refactor shared code after both flows are protected by validation
```

## Documentation-first rule

Before new build phases, document:

```text
route map
data dependencies
status transitions
security concerns
validation checklist
acceptance criteria
```

A build phase should not start unless its expected files, data model, actions, and acceptance tests are described.

## Current protected areas

These should not be modified unless the user explicitly asks:

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

## Current Training Lab areas

These are safe for Training Campaign Lab work:

```text
docs/training-campaign-lab/
examples/local-quest-rewards/training-*.php
examples/local-quest-rewards/admin-training-*.php
examples/local-quest-rewards/assets/training-lab.css
examples/local-quest-rewards/assets/training-lab.js
examples/local-quest-rewards/database/training_campaign_lab.sql
scripts/validate_training_campaign_lab.php
```

## Review checkpoint before implementation resumes

Before writing more code after Phase 1, confirm:

```text
Branch is local-quest-workspace
Main is untouched
No protected quest file will be edited
The phase has a written acceptance test
The phase can be validated independently
```

## Merge policy

This branch should remain separate until the Training Campaign Lab reaches at least one complete working vertical slice:

```text
Join campaign -> upload proof -> approve proof -> create Action Receipt -> evaluate reward -> issue reward -> show reward status
```

Only after that should the branch be considered for review, PR, or selective cherry-pick.
