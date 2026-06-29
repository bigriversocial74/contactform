# Training Campaign Lab Agent Build Handoff

## Purpose

This document is the handoff reference for any coding agent continuing the Training Campaign Lab build.

Read this before touching code.

## Branch

```text
local-quest-workspace
```

This branch is a duplicate/fork workspace of the Loyalty Quest / Local Quest Rewards example.

Do not merge into `main` and do not modify `main`.

## Product target

Build a proof-of-action Training Campaign Lab that can reward verified sequences, proof submissions, reviews, Action Receipts, and reward issue statuses.

First vertical slice:

```text
Join campaign -> upload proof -> approve proof -> complete sequence -> create Action Receipt -> evaluate reward -> issue reward -> show reward status
```

## Existing Phase 1 code

```text
examples/local-quest-rewards/training-lab.php
examples/local-quest-rewards/training-campaigns.php
examples/local-quest-rewards/training-campaign-data.php
examples/local-quest-rewards/assets/training-lab.css
examples/local-quest-rewards/assets/training-lab.js
```

## Existing Phase 2 foundation

```text
examples/local-quest-rewards/database/training_campaign_lab.sql
examples/local-quest-rewards/database/training_campaign_lab_seed.sql
scripts/validate_training_campaign_lab.php
```

## Protected existing Loyalty Quest files

Do not edit these unless the user explicitly approves a refactor:

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

Allowed reuse:

```text
require app.php for config/session/helpers
use lqr_h() for escaping
use lqr_config()
use lqr_load_state() only for existing user/session context if needed
use existing Microgifter API call patterns where safe
```

Preferred new Training Lab files:

```text
training-storage.php
training-campaign-detail.php
training-sequence.php
training-proof-upload.php
training-rewards.php
training-profile-wallet.php
admin-training-review.php
admin-training-receipts.php
admin-training-participants.php
admin-training-templates.php
admin-training-builder.php
admin-training-reward-rules.php
admin-training-settings.php
admin-training-audit-logs.php
training-receipt-service.php
training-reward-service.php
```

## Required docs to read first

```text
docs/training-campaign-lab/README.md
docs/training-campaign-lab/branch-strategy.md
docs/training-campaign-lab/product-requirements.md
docs/training-campaign-lab/route-map.md
docs/training-campaign-lab/ui-data-map.md
docs/training-campaign-lab/status-model.md
docs/training-campaign-lab/security-permissions.md
docs/training-campaign-lab/data-lifecycle.md
docs/training-campaign-lab/implementation-tickets.md
docs/training-campaign-lab/qa-test-script.md
```

## Next implementation phase

The next code phase should be:

```text
Phase 3: SQL-backed storage helper and campaign read model
```

Recommended files:

```text
examples/local-quest-rewards/training-storage.php
examples/local-quest-rewards/training-campaign-detail.php
```

Do this before proof upload.

## Recommended next coding order

```text
1. Build training-storage.php read helper
2. Read campaigns/sequences/tasks/reward rules from SQL when tables exist
3. Keep fallback to training-campaign-data.php if SQL is not installed
4. Build training-campaign-detail.php
5. Implement participant join in a small isolated action
6. Build training-sequence.php
7. Only then build training-proof-upload.php
```

## Storage helper expectations

`training-storage.php` should provide functions similar to:

```text
tcl_db_config()
tcl_pdo()
tcl_schema_available()
tcl_get_campaigns()
tcl_get_campaign_by_slug()
tcl_get_campaign_sequences()
tcl_get_sequence_tasks()
tcl_get_reward_rules()
tcl_get_or_create_participant()
tcl_get_participant_progress()
tcl_add_event()
```

Requirements:

```text
If SQL schema is missing, fail gracefully or fall back to PHP seed data
Do not alter storage-sql.php during first pass
Use prepared statements
Use public IDs/slugs for URLs
Never trust numeric IDs from query parameters
```

## Proof upload phase warning

Do not implement uploads before:

```text
schema exists
storage helper exists
participant join exists
task status model exists
permission checks exist
```

## Reward phase warning

Do not issue rewards before:

```text
approved review exists
Action Receipt exists
reward rule evaluation exists
duplicate issue prevention exists
linked account handling exists
```

## Validation command

Run after each build phase:

```bash
php scripts/validate_training_campaign_lab.php
```

## Local dev command

```bash
php -S 127.0.0.1:8090 -t examples/local-quest-rewards
```

Open:

```text
http://127.0.0.1:8090/training-lab.php
http://127.0.0.1:8090/training-campaigns.php
```

## Database import commands

```bash
mysql -u USER -p DATABASE_NAME < examples/local-quest-rewards/database/training_campaign_lab.sql
mysql -u USER -p DATABASE_NAME < examples/local-quest-rewards/database/training_campaign_lab_seed.sql
```

## Build quality rules

```text
One phase at a time
Keep code isolated under training-* files
Do not redesign UI while building backend
Do not change original Local Quest behavior
Every POST action must have auth/permission/CSRF plan
Every status transition should write an event
Every reward issue must be tied to an Action Receipt
Every agent should update docs if behavior changes
```

## Immediate next output expected from an agent

A strong next agent should produce:

```text
examples/local-quest-rewards/training-storage.php
examples/local-quest-rewards/training-campaign-detail.php
updated scripts/validate_training_campaign_lab.php checks for the new files
small README update noting Phase 3 started
```

Nothing else until that passes.
