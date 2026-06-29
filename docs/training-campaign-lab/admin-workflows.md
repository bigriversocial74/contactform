# Training Campaign Lab Admin Workflows

## Purpose

This document defines the administrator, manager, owner, and reviewer workflows for the Training Campaign Lab.

The goal is to document how campaign operations should work before implementation continues.

## Admin workflow map

```text
Review dashboard
Browse campaigns
Create or configure campaign
Configure sequence and tasks
Configure reward rules
Invite or monitor participants
Review proof submissions
Create/inspect Action Receipts
Monitor reward issues
Audit events
Adjust settings
```

## Workflow 1: Review Training Lab dashboard

Primary route:

```text
training-lab.php
```

Admin goal:

```text
Understand current training activity, pending proof, campaign health, and reward activity.
```

Required UI sections:

```text
active campaign count
pending proof count
participant count
reward issue status
campaign preview
review queue preview
receipt preview
```

MVP behavior:

```text
Static preview in Phase 1
Later reads from SQL activity records
```

## Workflow 2: Browse campaigns

Primary route:

```text
training-campaigns.php
```

Admin goal:

```text
Review all training campaigns, open details, and understand campaign configuration.
```

Required actions:

```text
search campaigns
filter campaigns
open campaign detail
view campaign status
view reward preview
```

Later actions:

```text
pause campaign
archive campaign
duplicate campaign
open builder
```

## Workflow 3: Create campaign from template

Primary routes:

```text
admin-training-templates.php
admin-training-builder.php
```

Admin goal:

```text
Start from a reusable campaign pattern instead of creating every campaign manually.
```

Steps:

```text
Open Templates
Search/filter templates
Preview template
Click Use Template
Open Campaign Builder with draft populated
Adjust title, dates, audience, tasks, proof requirements, and rewards
Save Draft or Continue
```

Required template data:

```text
template title
description
category
duration
difficulty
sequence preview
task count
reward pattern
```

MVP note:

```text
Templates can remain static until campaign builder persistence exists.
```

## Workflow 4: Configure campaign basics

Primary route:

```text
admin-training-builder.php
```

Admin goal:

```text
Define the campaign identity and who can join.
```

Required fields:

```text
campaign title
description
campaign type
visibility
start date
end date
time zone
participant audience
status draft/active
```

Validation rules:

```text
title required
description required
end date must be after start date when both are present
visibility must be valid
audience must be selected before publish if campaign is private/team-only
```

## Workflow 5: Configure sequence and tasks

Primary route:

```text
admin-training-builder.php
```

Admin goal:

```text
Create the ordered action flow that participants must complete.
```

Required sequence data:

```text
sequence title
description
sort order
required/optional status
```

Required task data:

```text
task title
description
instructions
proof type
points value
sort order
required/optional status
lock/unlock rules later
```

MVP sequence:

```text
Daily Movement Routine
Warm-Up
Movement Session
Cool Down
Reflection
```

## Workflow 6: Configure proof requirements

Primary route:

```text
admin-training-builder.php
```

Admin goal:

```text
Define what the participant must submit to prove completion.
```

Proof requirement options:

```text
photo
video
file
text note
checklist
manager approval later
QR/geolocation later
```

MVP allowed proof:

```text
jpg
jpeg
png
webp
mp4
mov
webm
```

Validation rules:

```text
at least one proof type required for proof-based tasks
max file size required
instructions should be visible on proof upload page
```

## Workflow 7: Configure reward rules

Primary route:

```text
admin-training-reward-rules.php
```

Admin goal:

```text
Define when rewards become eligible and what reward value/type should be issued.
```

Required rule fields:

```text
reward level
trigger type
required completions
required streak
milestone target
reward value
reward type
expiration
budget cap
linked Microgift template/program
status active/paused/draft
```

MVP rule:

```text
Trigger: sequence_completion
Requirement: 1 verified sequence
Reward: configured Microgift reward
Account requirement: linked Microgifter account
```

Acceptance criteria:

```text
Rule can be evaluated after Action Receipt creation
Duplicate reward issue is prevented
Missing linked account is handled clearly
```

## Workflow 8: Invite or monitor participants

Primary route:

```text
admin-training-participants.php
```

Admin goal:

```text
Track participant progress and identify who needs action.
```

Required UI sections:

```text
participant summary cards
participant list
team summary later
progress percent
status pill
streak count
pending review count
needs resubmission count
at-risk status later
```

MVP behavior:

```text
Participants are created when users join campaigns.
Admin can view participant progress after join/proof/review is implemented.
```

Later actions:

```text
invite participant
assign team
message participant
remove participant
export participants
```

## Workflow 9: Review proof submissions

Primary route:

```text
admin-training-review.php
```

Admin/reviewer goal:

```text
Approve valid proof, reject invalid proof, or request resubmission.
```

Steps:

```text
Open Review Queue
Filter pending submissions
Select submission
Inspect proof file and participant note
Add reviewer note
Approve, Reject, or Request Resubmission
Confirm status change
```

Approval result:

```text
review record created
submission status approved
task completion receipt created
sequence completion checked
reward rules evaluated if eligible
```

Reject result:

```text
review record created
submission status rejected
no receipt created
no reward evaluated
```

Resubmission result:

```text
review record created
submission status needs_resubmission
participant can upload another attempt
```

## Workflow 10: Inspect Action Receipts

Primary route:

```text
admin-training-receipts.php
```

Admin goal:

```text
Verify the durable record of approved action and reward status.
```

Required UI sections:

```text
receipt list
receipt detail panel
participant/campaign/task context
proof link
review link
reward issue status
event timeline
```

Receipt types:

```text
task_completion
sequence_completion
milestone_completion
streak_completion
reward_eligibility
```

## Workflow 11: Monitor reward issues

Primary routes:

```text
training-rewards.php
admin-training-receipts.php
training-profile-wallet.php
```

Admin goal:

```text
Confirm that eligible participants receive rewards or clear failure/link-account states.
```

States to show:

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

Admin actions later:

```text
retry failed issue
view provider response
link participant account support
void reward issue if needed later
```

## Workflow 12: Review audit logs

Primary route:

```text
admin-training-audit-logs.php
```

Admin/owner goal:

```text
Search and inspect important system/user events.
```

Required filters:

```text
event type
actor
participant
campaign
status
date range
```

Required detail fields:

```text
timestamp
actor
target type
target ID
status before/after
metadata summary
related receipt/reward IDs
```

## Workflow 13: Manage settings

Primary route:

```text
admin-training-settings.php
```

Owner/admin goal:

```text
Manage organization defaults, permissions, notification preferences, privacy/retention, integrations, branding, and account security.
```

Settings sections:

```text
Organization Settings
Team Permissions
Notifications
Privacy & Retention
Integrations
Branding / Theme
Account Security
```

MVP note:

```text
Settings can be visible as static/stub UI until training_settings exists.
```

## Admin workflow done definition

The admin side is MVP-complete when:

```text
Admin can view pending proof
Admin can approve proof
Admin can reject proof
Admin can request resubmission
Approved proof creates Action Receipt
Action Receipt is visible
Reward status is visible
Audit event is visible
Original Local Quest admin pages remain available
```
