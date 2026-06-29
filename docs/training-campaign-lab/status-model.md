# Training Campaign Lab Status Model

## Purpose

This document defines the expected states and transitions for Training Campaign Lab.

The goal is to prevent reward issues before proof is approved, avoid inconsistent sequence progress, and make review/resubmission behavior predictable.

## Status principles

- Rewards should never issue from an unverified task.
- Sequence completion should depend on approved required tasks.
- Rejected proof should not count toward completion.
- Resubmission should create a new attempt or update the submission attempt state.
- Action Receipts should be created only after verification.
- Reward status should be separate from proof/review status.
- Streak status should update only after verified sequence completion.

## Participant campaign status

```text
invited
joined
active
paused
completed
reward_eligible
reward_issued
expired
removed
```

### Recommended transitions

```text
invited -> joined
joined -> active
active -> paused
paused -> active
active -> completed
completed -> reward_eligible
reward_eligible -> reward_issued
active -> expired
joined -> removed
active -> removed
```

## Task status

Task status describes the participant's progress on a required task.

```text
not_started
in_progress
submitted
under_review
approved
rejected
needs_resubmission
expired
```

### Recommended transitions

```text
not_started -> in_progress
in_progress -> submitted
submitted -> under_review
under_review -> approved
under_review -> rejected
under_review -> needs_resubmission
rejected -> needs_resubmission
needs_resubmission -> submitted
not_started -> expired
in_progress -> expired
submitted -> expired
```

## Submission status

Submission status describes the proof upload object.

```text
created
uploaded
pending_review
under_review
approved
rejected
needs_resubmission
archived
```

### Recommended transitions

```text
created -> uploaded
uploaded -> pending_review
pending_review -> under_review
under_review -> approved
under_review -> rejected
under_review -> needs_resubmission
needs_resubmission -> uploaded
rejected -> archived
approved -> archived
```

## Review status

Review status describes the review decision.

```text
pending
approved
rejected
needs_resubmission
flagged
escalated
```

### Recommended transitions

```text
pending -> approved
pending -> rejected
pending -> needs_resubmission
pending -> flagged
flagged -> escalated
escalated -> approved
escalated -> rejected
```

## Sequence status

Sequence status describes a participant's progress through a group of required tasks.

```text
not_started
active
partially_complete
complete_pending_review
verified_complete
failed
expired
```

### Recommended transitions

```text
not_started -> active
active -> partially_complete
partially_complete -> complete_pending_review
complete_pending_review -> verified_complete
active -> expired
partially_complete -> expired
complete_pending_review -> failed
```

### Sequence completion rule

A sequence becomes `verified_complete` when:

```text
all required tasks are approved
no required task is pending review
no required task is rejected without accepted resubmission
campaign is active
participant is active
```

Optional later rules:

```text
minimum passing score reached
manager final approval completed
second review completed
proof submitted inside required time window
```

## Action Receipt status

Action Receipts should be durable and should not be deleted when the reward state changes.

Receipt types:

```text
task_completion
sequence_completion
streak_completion
milestone_completion
team_completion
sponsor_pool_completion
```

Receipt status:

```text
created
linked_to_reward_rule
reward_pending
reward_issued
reward_failed
archived
```

### Recommended transitions

```text
created -> linked_to_reward_rule
linked_to_reward_rule -> reward_pending
reward_pending -> reward_issued
reward_pending -> reward_failed
created -> archived
```

## Reward eligibility status

Reward status should not be mixed with task status.

```text
locked
eligible
pending_issue
issued
claim_pending
claimed
redeemed
failed
expired
```

### Recommended transitions

```text
locked -> eligible
eligible -> pending_issue
pending_issue -> issued
pending_issue -> failed
issued -> claim_pending
claim_pending -> claimed
claimed -> redeemed
issued -> expired
claim_pending -> expired
failed -> pending_issue
```

## Streak status

Streaks should update only after verified sequence completion.

```text
not_started
active
at_risk
frozen
broken
completed
```

### Recommended transitions

```text
not_started -> active
active -> at_risk
at_risk -> active
at_risk -> broken
active -> frozen
frozen -> active
active -> completed
```

## Milestone status

```text
locked
in_progress
complete
reward_eligible
reward_issued
expired
```

### Recommended transitions

```text
locked -> in_progress
in_progress -> complete
complete -> reward_eligible
reward_eligible -> reward_issued
in_progress -> expired
```

## Campaign status

```text
draft
active
paused
completed
archived
```

### Recommended transitions

```text
draft -> active
active -> paused
paused -> active
active -> completed
completed -> archived
paused -> archived
```

## Review and reward release flow

Basic MVP flow:

```text
participant submits proof
submission becomes pending_review
reviewer opens submission
submission becomes under_review
reviewer approves submission
submission becomes approved
task becomes approved
system checks required tasks
if all required tasks approved:
  sequence becomes verified_complete
  sequence Action Receipt is created
  reward rules are evaluated
  eligible reward becomes pending_issue
  reward is issued through Microgifter
  reward status becomes issued
```

## Resubmission flow

```text
participant submits proof
reviewer rejects or requests resubmission
submission becomes needs_resubmission
task becomes needs_resubmission
participant uploads new proof
attempt_number increments
submission becomes pending_review
reviewer approves
```

## Reward guardrails

Reward issue should be blocked when:

```text
participant is not active
campaign is not active
sequence is not verified_complete
required Microgifter account link is missing
reward rule is paused
reward budget is exhausted
max rewards per user reached
cooldown period still active
duplicate Action Receipt already triggered the same reward rule
```

## Event log requirements

Every state transition should create a `training_events` row.

Required events:

```text
training.campaign.joined
training.task.started
training.proof.uploaded
training.submission.pending_review
training.review.opened
training.review.approved
training.review.rejected
training.review.resubmission_requested
training.task.approved
training.sequence.verified_complete
training.receipt.created
training.reward.eligible
training.reward.pending_issue
training.reward.issued
training.reward.failed
training.streak.updated
```

## MVP status list

For first implementation, support these minimum statuses:

```text
submission_status: pending_review, approved, rejected, needs_resubmission
sequence_status: active, partially_complete, verified_complete
reward_status: locked, eligible, pending_issue, issued, failed
participant_status: joined, active, completed, reward_issued
```
