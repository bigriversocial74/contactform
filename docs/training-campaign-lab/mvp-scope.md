# Training Campaign Lab MVP Scope

## MVP goal

Build the smallest complete version that proves Microgifter can reward a verified training sequence.

The MVP must prove this loop:

```text
Join campaign -> complete task sequence -> upload proof -> review proof -> create Action Receipt -> issue reward -> track reward status
```

## MVP positioning

```text
Manual proof upload + manual review + action receipt + reward release.
```

Do not start with AI review, motion tracking, or complex sponsor billing.

## Primary MVP campaign

```text
5-Day Movement Challenge
```

### Sequence

```text
Daily Movement Routine
```

### Tasks

1. Upload warmup photo/video.
2. Upload squat video.
3. Upload plank video.
4. Upload cooldown proof.

### Reward ladder

```text
Complete first full routine -> entry badge/status
Complete 3 verified routines -> $5 Microgift
Complete 5-day streak -> $15 Microgift
```

For the first vertical slice, issue only one reward after one verified sequence. Add repeat/streak rewards after the single-sequence loop works.

## Secondary demo campaign

```text
Coffee Shop Opening Routine
```

### Sequence

```text
Daily Opening Checklist
```

### Tasks

1. Upload clean counter photo.
2. Upload stocked pastry case photo.
3. Upload espresso machine startup video.
4. Upload QR/table tent placement photo.
5. Manager approval.

### Reward ladder

```text
Complete one opening sequence -> $2 reward
Complete 5 days in a row -> $10 bonus
Perfect month -> $50 team reward
```

This campaign is useful for merchant/staff training demos, but does not need to be fully functional before the fitness vertical slice is complete.

## MVP included features

### Campaign shell

- Training Lab landing page.
- Campaign list page.
- Static seed campaign definitions.
- Links to participant sequence and admin review.

### Participant flow

- Participant signs in using existing Local Quest auth where practical.
- Participant joins a campaign.
- Participant sees sequence tasks.
- Participant sees current task status.
- Participant uploads proof for each task.
- Participant sees pending/approved/rejected states.

### Proof upload

- Allow image/video upload.
- Store file metadata.
- Store proof submission.
- Show submitted proof in admin review queue.
- Allow participant notes.

Allowed MVP file types:

```text
jpg, jpeg, png, webp, mp4, mov, webm
```

### Admin review

- Show pending proof submissions.
- Show campaign, participant, sequence, task, proof file, and notes.
- Approve proof.
- Reject proof.
- Request resubmission.
- Store reviewer notes.

### Completion logic

- Approved task counts toward sequence.
- Required tasks must be approved.
- Sequence becomes verified complete when all required tasks are approved.
- Create sequence Action Receipt.

### Reward logic

- Reward rule evaluates after sequence Action Receipt.
- Require Microgifter account link before issue.
- Issue reward through existing reward distribution flow where practical.
- Store reward issue response.
- Show reward issue status.

### Consistency tracking

MVP minimum:

- total verified sequence completions
- current streak count
- last completed date

Not required for first reward loop:

- advanced streak recovery
- grace days
- team streaks
- sponsor pool match

## MVP excluded features

Do not build these in the first pass:

- AI video review
- biometric identity detection
- real-time motion tracking
- rep counting
- full certification system
- sponsor billing
- paid seat billing
- full notification engine
- public API endpoints
- mobile app
- full team leaderboards
- external fitness/wearable integrations
- complex multi-location overrides
- formal compliance exports

## MVP file targets

First code files:

```text
examples/local-quest-rewards/training-lab.php
examples/local-quest-rewards/training-campaigns.php
examples/local-quest-rewards/training-sequence.php
examples/local-quest-rewards/training-upload.php
examples/local-quest-rewards/admin-training-review.php
examples/local-quest-rewards/training-rewards.php
examples/local-quest-rewards/training-consistency.php
examples/local-quest-rewards/assets/training-lab.css
examples/local-quest-rewards/assets/training-lab.js
examples/local-quest-rewards/database/training_campaign_lab.sql
```

Optional first validator:

```text
scripts/validate_training_campaign_lab.php
```

## MVP data tables

First implementation should include:

```text
training_campaigns
training_sequences
training_tasks
training_participants
training_files
training_task_submissions
training_reviews
training_action_receipts
training_reward_rules
training_reward_issues
training_streaks
training_events
```

Defer until later:

```text
training_organizations
training_locations
training_teams
training_team_members
training_skills
training_badges
training_sponsor_pools
training_notifications
training_external_events
training_impact_reports
```

## MVP state support

Minimum statuses:

```text
submission_status: pending_review, approved, rejected, needs_resubmission
sequence_status: active, partially_complete, verified_complete
reward_status: locked, eligible, pending_issue, issued, failed
participant_status: joined, active, completed, reward_issued
```

## MVP success criteria

The MVP is successful when:

1. A participant can join the 5-Day Movement Challenge.
2. The participant can see the Daily Movement Routine tasks.
3. The participant can upload proof for each required task.
4. Admin can review each proof submission.
5. Admin can approve or reject each submission.
6. Approved tasks update sequence progress.
7. All approved tasks mark the sequence as verified complete.
8. A sequence Action Receipt is created.
9. A reward rule becomes eligible.
10. A Microgifter reward issue is created/stored.
11. Participant can see reward status.
12. A basic streak/completion count updates.

## Recommended implementation rule

Build one vertical slice before building extra UI.

Do not create a large dashboard until this works:

```text
one campaign
one participant
four proof submissions
one reviewer
one verified sequence
one Action Receipt
one reward issue
```
