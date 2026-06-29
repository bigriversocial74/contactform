# Training Campaign Lab Next Build Outline

## Purpose

This document explains exactly what should happen after the documentation, Phase 1 UI shell, and Phase 2 database foundation.

It is written for future build agents so they can continue with minimal guesswork.

## Current completed foundation

```text
Documentation package complete
UI mockup docs complete
Phase 1 static shell started
Phase 2 SQL schema added
Phase 2 SQL seed added
Validation script added
Agent handoff added
```

## Next phase

```text
Phase 3: SQL-backed campaign read model
```

## Phase 3 goal

Replace static-only campaign reads with a safe Training Lab storage layer that can read from SQL if the schema is installed, while keeping the PHP seed fallback available.

This phase should not implement uploads, reviews, receipts, or rewards yet.

## Phase 3 files to create/update

Create:

```text
examples/local-quest-rewards/training-storage.php
examples/local-quest-rewards/training-campaign-detail.php
```

Update:

```text
examples/local-quest-rewards/training-campaigns.php
examples/local-quest-rewards/training-lab.php
scripts/validate_training_campaign_lab.php
```

## Phase 3 acceptance criteria

```text
training-storage.php exists
Campaign list can load from SQL when tables exist
Campaign list can fall back to training-campaign-data.php when SQL unavailable
Campaign detail page loads by campaign slug
Campaign detail displays sequence, tasks, and reward ladder
No original Loyalty Quest files are changed
Validation script reports the new route/helper files
```

## Phase 3 non-goals

Do not build yet:

```text
proof upload
admin review
Action Receipt creation
reward issuing
settings builder
template builder
audit logs
```

## Recommended Phase 3 function list

```text
tcl_training_config()
tcl_training_pdo()
tcl_training_schema_available()
tcl_training_seed_fallback_campaigns()
tcl_training_get_campaigns()
tcl_training_get_campaign_by_slug(string $slug)
tcl_training_get_campaign_sequences(int $campaignId)
tcl_training_get_sequence_tasks(int $sequenceId)
tcl_training_get_reward_rules(int $campaignId)
tcl_training_normalize_campaign_row(array $row)
tcl_training_add_event(array $context)
```

## Phase 4 after Phase 3 passes

```text
Phase 4: participant join and sequence view
```

Files:

```text
training-campaign-detail.php
training-sequence.php
training-storage.php
```

Build:

```text
join campaign action
training_participants write
participant progress read model
sequence/task status display
current task CTA
```

## Phase 5 after Phase 4 passes

```text
Phase 5: proof upload
```

Files:

```text
training-proof-upload.php
training-storage.php
examples/local-quest-rewards/uploads/training-proof/.gitkeep
```

Build:

```text
file validation
safe stored filename
training_files record
training_task_submissions record
pending_review status
participant submission history
```

## Phase 6 after Phase 5 passes

```text
Phase 6: admin review queue
```

Files:

```text
admin-training-review.php
training-storage.php
```

Build:

```text
pending review list
proof detail panel
approve action
reject action
request resubmission action
reviewer notes
status changes
events
```

## Phase 7 after Phase 6 passes

```text
Phase 7: Action Receipts
```

Files:

```text
training-receipt-service.php
admin-training-receipts.php
training-storage.php
```

Build:

```text
task completion receipt after approval
sequence completion receipt after all required tasks approved
receipt detail view
receipt event timeline
```

## Phase 8 after Phase 7 passes

```text
Phase 8: reward rule evaluation and wallet display
```

Files:

```text
training-reward-service.php
training-rewards.php
training-profile-wallet.php
```

Build:

```text
reward rule evaluator
needs_linked_account status
pending/issued/failed reward issue records
reward ladder status
wallet/profile reward display
```

## Always run validation

After every phase:

```bash
php scripts/validate_training_campaign_lab.php
```

## Do not skip this rule

Never issue a reward unless this chain exists:

```text
approved proof -> Action Receipt -> reward rule match -> reward issue record
```
