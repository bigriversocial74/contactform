# Training Campaign Lab SQL Schema Design

## Purpose

This document describes the future SQL schema for the Training Campaign Lab.

This is documentation only. It is not an executable SQL migration.

## Current status

```text
Schema design only
No .sql implementation file is approved yet
Do not create database files until implementation is approved
```

## Schema principles

```text
Use additive tables only
Use training_* table names
Do not alter existing Local Quest tables
Do not drop existing tables
Use public_id for external references
Use numeric id for internal joins
Use created_at and updated_at on mutable objects
Use events for auditability
```

## Core tables

Future MVP tables:

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

## Table design summary

### training_campaigns

Purpose:

```text
Top-level campaign/challenge container.
```

Core fields:

```text
id
public_id
slug
title
subtitle
description
campaign_type
visibility
status
difficulty
sponsor_name
starts_at
ends_at
created_by_user_id
metadata_json
created_at
updated_at
```

### training_sequences

Purpose:

```text
Ordered groups of tasks inside a campaign.
```

Core fields:

```text
id
public_id
campaign_id
slug
title
description
sort_order
is_required
status
metadata_json
created_at
updated_at
```

### training_tasks

Purpose:

```text
Individual proof-ready participant actions.
```

Core fields:

```text
id
public_id
campaign_id
sequence_id
slug
title
description
instructions
proof_type
accepted_extensions
max_file_size_mb
points
sort_order
is_required
status
metadata_json
created_at
updated_at
```

Proof type values:

```text
photo
video
file
text
checklist
manager_approval
qr_geo
```

### training_participants

Purpose:

```text
Participant enrollment in a campaign.
```

Core fields:

```text
id
public_id
campaign_id
user_id
external_user_id
display_name
email
role
status
joined_at
completed_at
metadata_json
created_at
updated_at
```

Participant status values:

```text
invited
active
completed
inactive
removed
```

### training_files

Purpose:

```text
Private proof file metadata.
```

Core fields:

```text
id
public_id
uploaded_by_user_id
participant_id
original_filename
stored_filename
storage_path
file_extension
mime_type
file_size_bytes
file_hash
status
metadata_json
created_at
updated_at
```

File status values:

```text
uploaded
attached
archived
removed
```

### training_task_submissions

Purpose:

```text
Participant proof submission for a task.
```

Core fields:

```text
id
public_id
campaign_id
sequence_id
task_id
participant_id
file_id
attempt_number
participant_note
status
submitted_at
reviewed_at
metadata_json
created_at
updated_at
```

Submission status values:

```text
draft
pending_review
approved
rejected
needs_resubmission
expired
withdrawn
```

### training_reviews

Purpose:

```text
Reviewer decision records for submissions.
```

Core fields:

```text
id
public_id
submission_id
campaign_id
participant_id
reviewer_user_id
reviewer_role
decision
reviewer_note
reviewed_at
metadata_json
created_at
updated_at
```

Review decisions:

```text
approved
rejected
resubmission_requested
```

### training_action_receipts

Purpose:

```text
Durable record that a verified action occurred.
```

Core fields:

```text
id
public_id
receipt_type
campaign_id
participant_id
sequence_id
task_id
submission_id
review_id
reward_rule_id
points_awarded
status
receipt_payload_json
created_at
updated_at
```

Receipt types:

```text
task_completion
sequence_completion
milestone_completion
streak_completion
reward_eligibility
```

Receipt status values:

```text
created
verified
linked_to_reward
voided
```

### training_reward_rules

Purpose:

```text
Rules that determine when verified progress becomes reward-eligible.
```

Core fields:

```text
id
public_id
campaign_id
title
trigger_type
required_completions
required_streak
milestone_target
reward_label
reward_type
reward_value_cents
budget_cap_cents
expires_after_days
linked_microgifter_program_id
linked_microgifter_template_id
status
sort_order
metadata_json
created_at
updated_at
```

Trigger types:

```text
task_completion
sequence_completion
milestone_completion
streak_completion
manual
```

Reward types:

```text
microgift
points
badge
manual
```

### training_reward_issues

Purpose:

```text
Reward issue state created from Action Receipts and reward rules.
```

Core fields:

```text
id
public_id
campaign_id
participant_id
receipt_id
reward_rule_id
linked_account_id
microgifter_reward_id
microgifter_claim_code
status
failure_reason
provider_response_json
issued_at
claimed_at
redeemed_at
expires_at
created_at
updated_at
```

Reward issue statuses:

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

### training_streaks

Purpose:

```text
Track participant consistency across repeated verified sequences.
```

Core fields:

```text
id
public_id
campaign_id
participant_id
current_streak
best_streak
total_verified_sequences
last_verified_at
status
metadata_json
created_at
updated_at
```

### training_events

Purpose:

```text
Append-only audit trail for campaign, participant, proof, review, receipt, and reward activity.
```

Core fields:

```text
id
public_id
event_type
actor_user_id
actor_role
campaign_id
participant_id
target_type
target_id
status_before
status_after
metadata_json
created_at
```

## Required indexes

Future implementation should include indexes for:

```text
public_id unique keys
campaign slugs
campaign status
sequence campaign_id
task campaign_id and sequence_id
participant campaign_id + user_id duplicate prevention
submission participant/task/status
review submission_id
receipt participant/campaign/type
reward issue participant/receipt/rule duplicate prevention
event created_at
```

## Duplicate prevention rules

```text
One participant record per campaign + user.
One reward issue per participant + receipt + reward rule.
One sequence completion receipt per participant + sequence unless business rules allow repeats.
Task submissions may have multiple attempts.
Reviews are append-only records.
```

## Deletion rules

Recommended future behavior:

```text
Campaign delete should be restricted in production.
Archive campaigns instead of deleting.
Proof files should be soft-removed when possible.
Action Receipts should not be hard-deleted except test data reset.
Reward issue records should not be hard-deleted once issued.
Events should be append-only.
```

## Future migration order

```text
training_campaigns
training_sequences
training_tasks
training_participants
training_files
training_task_submissions
training_reviews
training_reward_rules
training_action_receipts
training_reward_issues
training_streaks
training_events
```

## Rollback design

Rollback should be documented separately during implementation.

Minimum rollback plan:

```text
Drop child tables before parent tables
Do not touch existing Local Quest tables
Preserve proof files unless explicitly resetting local demo data
```

## Implementation note

This document is the source for a future `.sql` file. Do not create the executable SQL file until the build phase is approved.
