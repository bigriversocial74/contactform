# Training Campaign Lab

This folder contains the build package for the Microgifter Training Campaign Lab on the `local-quest-workspace` branch.

The goal is to extend the Local Quest example into a new proof-of-action training module without changing the original quest script on `main`.

## Product statement

Microgifter Training Campaigns let organizations create action-based challenges, assign them to teams or public participants, collect proof of completion, verify progress, and issue rewards for completed sequences, streaks, and milestones.

## Core loop

```text
Organization creates campaign
Participant joins campaign
Participant completes task sequence
Participant uploads photo/video proof
Reviewer approves proof
System creates Action Receipt
Reward rules evaluate
Microgifter reward is issued
Wallet and claim status are tracked
Consistency/streak data updates
```

## Build docs

- [Build Plan](./build-plan.md)
- [Schema Outline](./schema.md)
- [Status Model](./status-model.md)
- [Demo Script](./demo-script.md)
- [Acceptance Checklist](./acceptance-checklist.md)

## First MVP target

Build one complete vertical slice:

```text
Participant joins 5-Day Movement Challenge
Participant uploads proof for each required task
Admin approves each submission
Sequence becomes verified complete
Action Receipt is created
Reward rule becomes eligible
Microgifter reward is issued
Wallet displays reward status
```

## Recommended first files

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

## Build principle

Start with manual proof upload and manual review. Add AI-assisted review, computer vision, motion scoring, and agentic coaching after the basic proof/reward loop works.
