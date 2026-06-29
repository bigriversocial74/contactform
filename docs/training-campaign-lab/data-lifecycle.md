# Training Campaign Lab Data Lifecycle

## Purpose

This document defines how Training Campaign Lab data moves from campaign creation to participant action, proof submission, review, Action Receipt creation, reward evaluation, reward issue, wallet display, event logging, and retention.

The goal is to make the verified-action data layer clear before implementing SQL, uploads, review actions, and rewards.

## Core lifecycle

```text
Campaign created
Sequence and tasks configured
Reward rules configured
Participant joins campaign
Participant starts sequence
Participant completes task
Participant uploads proof
Submission enters pending_review
Reviewer approves/rejects/requests resubmission
Approved task creates Action Receipt
All required tasks approved creates sequence Action Receipt
Reward rules evaluate
Reward issue is created
Reward status is displayed in rewards/wallet
Events are logged throughout
Data is retained, exported, archived, or removed according to policy
```

## Object lifecycle summary

| Object | Created when | Updated when | Final/terminal states |
|---|---|---|---|
| Campaign | Admin creates campaign | Draft/publish/pause/archive | archived |
| Sequence | Campaign sequence configured | Tasks/order change before launch | archived with campaign |
| Task | Task configured | Proof rules/status change before launch | archived with campaign |
| Participant | User joins campaign | Progress/streak/status changes | completed/removed/inactive |
| Proof File | File uploaded | Metadata adjusted by system only | retained/removed per policy |
| Task Submission | Proof submitted | Review decision/resubmission | approved/rejected/expired |
| Review | Reviewer acts on submission | Usually immutable after creation | approved/rejected/resubmission_requested |
| Action Receipt | Verified action occurs | Linked to reward issue | verified/linked_to_reward/voided |
| Reward Rule | Admin configures rule | Rule edited/paused/archived | archived |
| Reward Issue | Rule eligibility creates issue | Microgifter status/claim status updates | issued/failed/redeemed/expired |
| Streak | Participant completes verified actions | New verified activity changes streak | reset/archived |
| Event | Meaningful state change occurs | Should be append-only | retained/archived |

## Campaign lifecycle

### Draft

Required data:

```text
public_id
title
description
campaign_type
visibility
status = draft
created_by_user_id
created_at
updated_at
```

Allowed actions:

```text
edit campaign
add sequence
add tasks
configure proof requirements
configure reward rules
preview campaign
```

### Active

Required preconditions:

```text
At least one sequence
At least one required task
Proof requirements defined
Reward rules optional but recommended
Start/end date valid if present
```

Allowed actions:

```text
participants join
participants complete tasks
proof upload
review proof
create receipts
issue rewards
```

### Paused

Expected behavior:

```text
New joins may be blocked
New proof uploads may be blocked or allowed depending setting
Reviewers can continue reviewing prior submissions
Receipts and reward issues can continue for already-approved work if allowed
```

### Archived

Expected behavior:

```text
No new joins
No new proof uploads
Historical receipts remain visible
Audit logs remain visible
Rewards already issued keep their status
```

## Participant lifecycle

### Eligible / not joined

User can see campaign if public or allowed.

### Joined

A `training_participants` record is created.

Required data:

```text
public_id
campaign_id
user_id or external_user_id
status = active
joined_at
created_at
updated_at
```

Event:

```text
training.campaign.joined
```

### Active progress

Derived data:

```text
approved task count
pending review count
needs resubmission count
progress percent
current task
current streak
next reward
```

### Completed

Expected:

```text
sequence completion receipt exists
reward eligibility is evaluated
progress is 100% for required tasks
```

## Task and proof lifecycle

### Task available

Task is visible and can accept proof if unlocked.

### Proof uploaded

Writes:

```text
training_files
training_task_submissions
training_events
```

Submission status:

```text
pending_review
```

Events:

```text
training.proof.uploaded
training.submission.pending_review
```

### Pending review

Allowed actions:

```text
approve
reject
request_resubmission
```

Not allowed:

```text
reward issue
sequence completion
receipt creation unless approved
```

### Approved

Writes:

```text
training_reviews
training_task_submissions.status = approved
training_action_receipts task_completion
training_events
```

Events:

```text
training.review.approved
training.task.approved
training.receipt.created
```

### Rejected

Writes:

```text
training_reviews
training_task_submissions.status = rejected
training_events
```

Effect:

```text
Does not count toward progress
Does not create receipt
Does not unlock reward
```

### Needs resubmission

Writes:

```text
training_reviews
training_task_submissions.status = needs_resubmission
training_events
```

Effect:

```text
Participant can upload another attempt
Prior attempt remains in history
Latest pending attempt becomes reviewable
```

## Sequence completion lifecycle

Required condition:

```text
All required tasks in the sequence have approved submissions
```

On completion:

```text
Create sequence_completion Action Receipt
Update participant progress
Update streak/milestone data
Evaluate reward rules
Log event
```

Events:

```text
training.sequence.verified_complete
training.receipt.created
training.streak.updated
```

## Action Receipt lifecycle

Receipt types:

```text
task_completion
sequence_completion
milestone_completion
streak_completion
reward_eligibility
```

Required fields:

```text
public_id
receipt_type
participant_id
campaign_id
sequence_id optional
task_id optional
submission_id optional
review_id optional
reward_rule_id optional
status
created_at
```

Receipt states:

```text
created
verified
linked_to_reward
voided
```

Voiding a receipt later must:

```text
require admin/owner permission
write an event
keep the historical record visible
record reason
```

## Reward rule lifecycle

Rule fields:

```text
campaign_id
trigger_type
required_completions
required_streak
milestone_target
reward_value
reward_type
budget_cap
expires_after_days
linked_microgifter_template_id
status
```

Rule states:

```text
draft
active
paused
archived
```

When matched:

```text
participant becomes eligible
reward issue can be created
training.reward.eligible event is logged
```

## Reward issue lifecycle

States:

```text
not_eligible
eligible
needs_linked_account
pending_issue
issued
failed
claimed
redeemed
expired
```

Failed issue should store:

```text
failure reason
provider response if available
retry eligibility
created_at
updated_at
```

## Event lifecycle

Events should be append-only where practical.

Minimum fields:

```text
public_id
event_type
actor_user_id optional
actor_role optional
campaign_id optional
participant_id optional
target_type
target_id
status_before optional
status_after optional
metadata_json optional
created_at
```

Events should be written for:

```text
joins
uploads
review decisions
receipt creation
reward eligibility
reward issue attempts
reward issue success/failure
streak updates
settings changes later
permission changes later
```

## Retention lifecycle

MVP default:

```text
Keep proof metadata
Keep submission records
Keep reviews
Keep receipts
Keep reward issues
Keep events
```

Later retention options:

```text
proof file retention by organization setting
receipt retention by business setting
event log retention by admin policy
export before removal
soft removal over destructive removal where practical
```

## Data integrity rules

```text
Rejected proof does not count toward progress
Needs resubmission proof does not count toward progress
Only approved proof can create receipt
Only receipts can unlock reward eligibility
Only one reward issue should exist per participant + receipt + reward rule unless explicitly configured otherwise
Receipt records should outlive reward issue failures
Events should preserve state transitions
```
