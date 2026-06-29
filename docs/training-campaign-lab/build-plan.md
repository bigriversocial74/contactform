# Training Campaign Lab Build Plan

## Goal

Create a new Training Campaign Lab module inside `examples/local-quest-rewards/` that proves Microgifter can reward verified action sequences, streaks, milestones, and consistency.

This is a vertical-slice build. Do not attempt to build every future feature at once.

## Branch

```text
local-quest-workspace
```

## Non-destructive rule

Do not replace the current Local Quest Rewards script. Add Training Campaign Lab as a new module alongside the existing quest demo.

## Phase 1: Product shell

### Files

```text
examples/local-quest-rewards/training-lab.php
examples/local-quest-rewards/assets/training-lab.css
```

### Requirements

- Add a Training Campaign Lab landing page.
- Show the product concept.
- Show 3 demo campaign cards:
  - 5-Day Movement Challenge
  - Coffee Shop Opening Routine
  - 14-Day Creator Practice Streak
- Link to participant campaign flow.
- Link to admin proof review.
- Reuse the existing Local Quest app style where practical.

### Done when

- Page loads without database changes.
- Page does not break existing quest flow.
- Navigation clearly explains the new module.

## Phase 2: SQL schema

### Files

```text
examples/local-quest-rewards/database/training_campaign_lab.sql
```

### Requirements

Create the first tables:

```text
training_campaigns
training_sequences
training_tasks
training_participants
training_task_submissions
training_reviews
training_action_receipts
training_reward_rules
training_reward_issues
training_streaks
training_files
training_events
```

### Done when

- SQL imports cleanly on MySQL/MariaDB.
- Tables use `BIGINT UNSIGNED` primary keys.
- Each table has `public_id`, `created_at`, and `updated_at` where practical.
- Foreign keys are ordered so imports do not fail.

## Phase 3: Demo campaign seed

### Files

```text
examples/local-quest-rewards/training-campaigns.php
```

Optional later:

```text
examples/local-quest-rewards/database/training_campaign_lab_seed.sql
```

### Requirements

Start with seed definitions in PHP for faster demo progress.

Campaigns:

1. 5-Day Movement Challenge
2. Coffee Shop Opening Routine
3. 14-Day Creator Practice Streak

### Done when

- Campaign list page renders the seed campaigns.
- Each campaign has sequences, tasks, and reward ladder metadata.

## Phase 4: Participant join and sequence view

### Files

```text
examples/local-quest-rewards/training-sequence.php
```

### Requirements

- Require participant sign-in.
- Let participant join selected training campaign.
- Show campaign details.
- Show required sequence tasks.
- Show progress status.
- Show reward ladder preview.

### Done when

- User can join a campaign.
- User can see tasks.
- Progress starts at 0 approved tasks.

## Phase 5: Proof upload

### Files

```text
examples/local-quest-rewards/training-upload.php
examples/local-quest-rewards/uploads/training-proof/.gitkeep
```

### Requirements

- Allow photo/video upload for a task.
- Store file metadata in `training_files`.
- Store submission in `training_task_submissions`.
- Use safe file names.
- Enforce allowed extensions.
- Enforce max file size.

### Allowed MVP file types

```text
jpg
jpeg
png
webp
mp4
mov
webm
```

### Done when

- Participant can upload proof for each task.
- Submission enters `pending_review` status.
- Upload metadata is visible in admin review queue.

## Phase 6: Admin proof review

### Files

```text
examples/local-quest-rewards/admin-training-review.php
```

### Requirements

- Show pending submissions.
- Show participant, campaign, sequence, task, proof file, and notes.
- Approve submission.
- Reject submission.
- Request resubmission.
- Store reviewer notes.

### Done when

- Reviewer can approve all task proofs.
- Rejected proof can be resubmitted.
- Approved tasks count toward sequence completion.

## Phase 7: Action Receipts

### Files

```text
examples/local-quest-rewards/training-receipts.php
```

### Requirements

- Create `task_completion` receipt when a task is approved.
- Create `sequence_completion` receipt when all required tasks are approved.
- Show receipt log to admins.

### Done when

- Sequence completion creates a durable Action Receipt.
- Receipt links user, campaign, sequence, tasks, proof, review, and reward status.

## Phase 8: Reward release

### Files

```text
examples/local-quest-rewards/training-rewards.php
```

### Requirements

- Add reward rules for sequence completion.
- Evaluate reward rules after Action Receipt creation.
- Require Microgifter account link before reward issue.
- Issue reward through existing distribution flow where practical.
- Store Microgifter response.
- Show reward issue status.

### Done when

- Approved sequence unlocks reward eligibility.
- Linked participant receives reward.
- Reward status appears in wallet/reward view.

## Phase 9: Consistency and reward ladders

### Files

```text
examples/local-quest-rewards/training-consistency.php
```

### Requirements

- Track sequence completion count.
- Track daily streak.
- Track weekly completion count.
- Track milestone completion.
- Evaluate ladder rewards.

### Done when

- Completing multiple verified sequences updates streak and milestones.
- Reward ladder can unlock more than one reward level.

## Phase 10: QA validation

### Files

```text
scripts/validate_training_campaign_lab.php
```

### Requirements

Validate:

- required files exist
- SQL file exists
- seed campaigns exist
- status values exist
- upload path is documented
- admin review page exists
- receipt creation path exists
- reward issue path exists

### Done when

- Validation script runs without fatal errors.
- Missing required pieces are reported clearly.

## First vertical slice acceptance

The first working build must prove:

```text
Join campaign -> upload proof -> approve proof -> complete sequence -> create Action Receipt -> issue reward -> view reward status
```

## Build order summary

1. Static Training Lab shell
2. SQL schema
3. Seed campaign definitions
4. Participant join flow
5. Sequence/task view
6. Proof upload
7. Admin review
8. Action Receipt creation
9. Reward rule evaluation
10. Reward issue
11. Consistency/streak tracking
12. Validation script
