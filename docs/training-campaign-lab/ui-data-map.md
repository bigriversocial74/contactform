# Training Campaign Lab UI Data Map

## Purpose

This document maps each UI page to the data it needs, the tables or seed sources it should read from, the records it may write, and the states it must display.

The goal is to keep the UI build tied to real data instead of drifting into disconnected mockup-only screens.

## Data-source phases

### Phase 1: PHP seed data

Phase 1 reads from:

```text
examples/local-quest-rewards/training-campaign-data.php
```

This is intentionally static.

### Phase 2+: SQL data

Later phases read/write these planned tables:

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
training_milestones
training_events
```

Optional later tables:

```text
training_organizations
training_locations
training_teams
training_team_members
training_templates
training_integrations
training_settings
training_notifications
```

## Shared user/account data

The Training Lab can reuse existing Local Quest identity concepts where safe:

```text
lqr_current_user_id()
lqr_get_user()
lqr_is_authenticated()
linked_account_id
external_user_id
email
display_name
```

Do not rewrite the existing Local Quest user flow without a separate refactor plan.

---

# Page data maps

## 1. Dashboard / App Landing

Route:

```text
training-lab.php
```

### Phase 1 source

```text
training-campaign-data.php
```

### Later reads

```text
training_campaigns
training_participants
training_task_submissions
training_reviews
training_action_receipts
training_reward_rules
training_reward_issues
training_streaks
training_events
```

### UI needs

```text
active campaign count
total participant count
total task count
average progress
featured campaign cards
current sequence preview
proof upload preview
review queue preview
action receipt preview
reward ladder preview
```

### Writes

```text
None in Phase 1
Later: dashboard itself should not write data except tracking optional UI events
```

### Important states

```text
No campaigns
No active campaign
No pending proof
No reward activity
Microgifter not linked
```

---

## 2. Campaigns

Route:

```text
training-campaigns.php
```

### Phase 1 source

```text
training-campaign-data.php
```

### Later reads

```text
training_campaigns
training_sequences
training_tasks
training_participants
training_reward_rules
training_streaks
```

### UI needs

```text
campaign public_id / slug
campaign title
campaign description
campaign type
campaign status
campaign visibility
campaign duration
campaign tags
sequence count
task count
participant count
reward preview
progress preview
current user joined status
primary CTA state
```

### Writes

```text
None in Phase 1
Later: training_participants when user joins a campaign
training_events for campaign joined/opened events
```

### Important states

```text
Active campaign
Draft campaign
Paused campaign
Expired campaign
Joined campaign
Not joined campaign
No matching search results
```

---

## 3. Campaign Detail

Route:

```text
training-campaign-detail.php
```

### Later reads

```text
training_campaigns
training_sequences
training_tasks
training_participants
training_task_submissions
training_reviews
training_reward_rules
training_reward_issues
training_streaks
training_events
```

### UI needs

```text
campaign title
campaign description
campaign status
campaign category/type
campaign visibility
start/end dates
participant count
organizer/sponsor
joined status
current progress percent
current sequence
next task
next reward
current streak
reward ladder levels
sequence overview
rules/requirements
campaign details
```

### Writes

```text
training_participants when user joins
training_events for campaign joined / campaign viewed / campaign continued
```

### Important states

```text
User not joined
User joined
Campaign active
Campaign expired
Campaign full/capped
Reward available
Reward locked
Sequence complete
```

### Derived values

```text
progress_percent = approved_required_tasks / total_required_tasks
next_task = first required task not approved
next_reward = first reward rule not unlocked
current_streak = latest training_streaks value for participant/campaign
```

---

## 4. Sequence / Tasks

Route:

```text
training-sequence.php
```

### Later reads

```text
training_campaigns
training_sequences
training_tasks
training_participants
training_task_submissions
training_reviews
training_files
training_reward_rules
training_streaks
```

### UI needs

```text
campaign context
sequence title
sequence description
ordered task list
current task
completed tasks
locked tasks
pending review tasks
needs resubmission tasks
proof requirements per task
due date/time if present
participant progress
streak count
next reward preview
completed step proof links
```

### Writes

```text
training_events for task viewed/started later
```

### Important states

```text
Task locked
Task available
Task in progress
Proof pending review
Proof approved
Proof rejected
Proof needs resubmission
Sequence partially complete
Sequence verified complete
```

### Derived values

```text
current_task = first required task without approved submission
sequence_status = verified_complete only when all required tasks are approved
```

---

## 5. Proof Upload

Route:

```text
training-proof-upload.php
```

### Later reads

```text
training_campaigns
training_sequences
training_tasks
training_participants
training_task_submissions
training_files
training_reviews
training_reward_rules
training_streaks
```

### Writes

```text
training_files
training_task_submissions
training_events
```

### UI needs

```text
campaign title
sequence title
task title
task instructions
proof type
accepted file types
max file size
due date/time
example guidance
selected file metadata
previous submission history
current submission status
participant note
submission checklist
streak summary
next reward preview
```

### Upload fields

```text
file original name
safe stored filename
mime type
file extension
file size
storage path
file hash optional later
uploaded_by_user_id
uploaded_at
```

### Submission fields

```text
participant_id
campaign_id
sequence_id
task_id
file_id
attempt_number
participant_note
status = pending_review
submitted_at
```

### Important states

```text
No file selected
File ready
Uploading
Upload failed
Unsupported file type
File too large
Pending review
Approved
Rejected
Needs resubmission
Task locked
Task already approved
```

---

## 6. Rewards & Progress

Route:

```text
training-rewards.php
```

### Later reads

```text
training_reward_rules
training_reward_issues
training_action_receipts
training_streaks
training_milestones
training_events
existing Local Quest reward/wallet data where useful
```

### UI needs

```text
points earned
points to next reward
reward ladder levels
current streak
issued rewards count
claimed rewards count
available rewards
next reward
milestone history
recent activity
reward issue status
```

### Writes

```text
training_events for reward viewed/status checked later
Potential reward claim/check actions may call existing wallet/reward flow
```

### Important states

```text
Locked reward
Eligible reward
Pending issue
Issued reward
Claimed reward
Redeemed reward
Expired reward
Failed issue
Microgifter account not linked
```

### Derived values

```text
points_to_next_reward = next_reward_threshold - current_points
reward_ladder_status = based on action receipts + reward issues
```

---

## 7. Review Queue

Route:

```text
admin-training-review.php
```

### Later reads

```text
training_task_submissions
training_files
training_campaigns
training_sequences
training_tasks
training_participants
training_reviews
training_events
```

### Writes

```text
training_reviews
training_task_submissions.status
training_action_receipts on approval
training_events
```

### UI needs

```text
pending review count
average review time
approved today count
status tabs
submission list
participant name/email
campaign title
task title
submission timestamp
proof preview
file metadata
participant note
reviewer note
available review actions
```

### Review writes

Approve:

```text
training_reviews.status = approved
training_task_submissions.status = approved
create task_completion Action Receipt
check sequence completion
create sequence_completion Action Receipt if eligible
training_events.review.approved
```

Reject:

```text
training_reviews.status = rejected
training_task_submissions.status = rejected
training_events.review.rejected
```

Request resubmission:

```text
training_reviews.status = resubmission_requested
training_task_submissions.status = needs_resubmission
training_events.review.resubmission_requested
```

### Important states

```text
Pending review
Approved
Rejected
Needs resubmission
Proof missing
Reviewer permission denied
Already reviewed
```

---

## 8. Participants & Teams

Route:

```text
admin-training-participants.php
```

### Later reads

```text
training_participants
training_campaigns
training_task_submissions
training_reviews
training_action_receipts
training_reward_issues
training_streaks
training_teams later
training_team_members later
```

### UI needs

```text
active participant count
active team count
average completion rate
at-risk participant count
participant avatar/name/email
team badge
campaign title
progress percent
streak count
participant status
invitation status
team completion rates
segmentation counts
```

### Writes

```text
training_participants later for invite/add flows
training_events for invite/add/status updates
```

### Important states

```text
Active
Invited
At Risk
Completed
Inactive
No team
No campaign progress
```

### Derived values

```text
participant_progress = approved_required_tasks / total_required_tasks
at_risk = low activity, missed due dates, or no recent submissions
team_completion_rate = completed_participants / active_team_participants
```

---

## 9. Action Receipts & History

Route:

```text
admin-training-receipts.php
```

### Later reads

```text
training_action_receipts
training_task_submissions
training_reviews
training_files
training_reward_rules
training_reward_issues
training_events
training_campaigns
training_sequences
training_tasks
training_participants
```

### UI needs

```text
total receipts
verified actions this week
rewards issued this week
failed issues this week
receipt list
receipt type
points/reward value
reward status
receipt ID
participant details
campaign/task/sequence context
proof link
reviewer
review timestamp
reward rule
reward issue status
event timeline
```

### Writes

```text
No direct writes in MVP except optional view/export events
```

### Important states

```text
Task completion receipt
Sequence completion receipt
Reward issued receipt
Verified-only receipt
Reward pending
Reward issued
Reward failed
Receipt missing linked reward
```

---

## 10. Settings

Route:

```text
admin-training-settings.php
```

### Later reads/writes

```text
training_organizations
training_settings
training_integrations
training_roles / permissions later
```

### UI needs

```text
organization name
default timezone
week start day
default member role
role permission summaries
notification toggles
data retention policy
privacy toggles
integration statuses
brand color
logo upload status
theme mode
password/2FA status
```

### Important states

```text
Saved
Unsaved changes
Save failed
Integration connected
Integration not connected
Permission denied
```

---

## 11. Templates

Route:

```text
admin-training-templates.php
```

### Phase 1/later source

```text
Could begin from PHP arrays similar to training-campaign-data.php
Later: training_templates and template child tables
```

### UI needs

```text
template title
description
category
tags
duration
difficulty
included sequence count
included task count
reward pattern
selected template detail
```

### Writes

```text
Later: create campaign draft from template
training_events.template.used
```

### Important states

```text
No templates
No search results
Template selected
Template unavailable
```

---

## 12. Campaign Builder

Route:

```text
admin-training-builder.php
```

### Later reads/writes

```text
training_campaigns
training_sequences
training_tasks
training_reward_rules
training_participants / team assignment later
training_events
```

### UI needs

```text
builder step
campaign title
description
type
visibility
start/end dates
team/audience assignment
sequence list
task list
proof requirements
reward summary
live preview
publish readiness
```

### Writes

```text
training_campaigns draft
training_sequences
training_tasks
training_reward_rules later
training_events.campaign.draft_saved
training_events.campaign.published
```

### Important states

```text
Draft
Unsaved changes
Validation error
Ready to publish
Published
Missing required title
Invalid date range
No tasks added
```

---

## 13. Reward Rules Builder

Route:

```text
admin-training-reward-rules.php
```

### Later reads/writes

```text
training_reward_rules
training_campaigns
training_action_receipts for eligibility preview
training_reward_issues for budget/duplicate checks
Microgifter program/template data where configured
```

### UI needs

```text
reward level
trigger type
required completions
streak count
milestone target
reward value
reward type
expiration
budget cap
linked Microgift template
eligibility preview
```

### Writes

```text
training_reward_rules
training_events.reward_rule.updated
```

### Important states

```text
Rule valid
Rule invalid
Missing Microgift template
Budget cap reached
No eligible participants
Eligible participant preview
```

---

## 14. User Profile / Wallet

Route:

```text
training-profile-wallet.php
```

### Later reads

```text
existing Local Quest user identity/wallet data
training_participants
training_reward_issues
training_action_receipts
training_streaks
training_events
training_teams later
```

### UI needs

```text
user avatar/name/email/role
linked Microgifter account status
wallet ID
current streak
available rewards
total points earned
earned reward cards
claimed/redeemed history
recent activity
teams joined
account preference links
```

### Writes

```text
Profile updates later
Microgifter link/manage actions through existing Local Quest patterns
training_events.profile.viewed optional
```

### Important states

```text
Microgifter linked
Microgifter not linked
No rewards
Reward available
Reward redeemed
Wallet sync failed
```

---

## 15. Audit Logs

Route:

```text
admin-training-audit-logs.php
```

### Later reads

```text
training_events
training_action_receipts
training_task_submissions
training_reviews
training_reward_issues
training_campaigns
training_participants
```

### UI needs

```text
event timestamp
actor
event type
campaign
participant
status
payload summary
related IDs
metadata
source
IP/user agent where available
```

### Writes

```text
Optional: export event / audit log viewed event
```

### Important states

```text
No events
Filtered empty state
Event success
Event failed
Payload unavailable
Permission denied
```

---

# Cross-page data rules

## Status values must be centralized

Do not hardcode different status names per page.

Use one canonical set from `status-model.md`.

## Receipt creation is downstream from review

Proof upload should not directly create rewards.

Correct path:

```text
submission pending_review
review approved
task completion receipt created
sequence completion checked
sequence completion receipt created if all required tasks approved
reward rules evaluated
reward issue created
wallet/rewards display updated
```

## Reward issue is separate from reward eligibility

Display both when available:

```text
eligibility status
issue status
claim/redeem status
```

## File storage is separate from submission status

A file can exist even when a submission is rejected or resubmitted.

Keep:

```text
training_files = file metadata
training_task_submissions = proof submission attempt/status
training_reviews = reviewer decision
```

## Events should be written for meaningful state changes

Minimum event types:

```text
training.campaign.joined
training.task.started
training.proof.uploaded
training.submission.pending_review
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
