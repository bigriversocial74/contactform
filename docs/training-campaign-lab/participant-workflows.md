# Training Campaign Lab Participant Workflows

## Purpose

This document defines the participant journey for the Training Campaign Lab.

The goal is to make the user-facing flow clear before additional implementation continues.

## Participant journey summary

```text
Open Training Lab
Browse campaigns
Open campaign detail
Join campaign
View sequence/tasks
Complete task
Upload proof
Wait for review
Respond to rejection/resubmission if needed
Complete all required tasks
Earn Action Receipt
Unlock reward eligibility
View reward status
View wallet/profile
```

## Participant roles

## Guest visitor

A visitor who has not signed in.

Can do in Phase 1:

```text
View static Training Lab dashboard
View static Campaigns page
Understand the product concept
```

Cannot do:

```text
Join campaign
Upload proof
Earn reward
View wallet
View private proof/review status
```

## Signed-in participant

A user who can join campaigns and complete tasks.

Can do:

```text
Join eligible campaigns
View own campaign progress
View own sequence/tasks
Upload proof
View submission status
Resubmit proof when requested
View earned rewards
View own receipts/reward history
```

## Workflow 1: Discover Training Lab

Primary route:

```text
training-lab.php
```

Participant goal:

```text
Understand what training campaigns are and decide where to begin.
```

Expected page sections:

```text
hero/product statement
active campaign preview
KPI preview
today/current sequence preview
reward preview
proof/review/receipt concept preview
```

Primary actions:

```text
Explore Campaigns
Continue Current Task later
View Rewards later
```

## Workflow 2: Browse campaigns

Primary route:

```text
training-campaigns.php
```

Participant goal:

```text
Find a campaign that is available and relevant.
```

Expected page sections:

```text
search
filter chips
campaign cards
campaign status
reward preview
campaign duration
participant count
```

Primary actions:

```text
Search campaign
Filter campaign
Open campaign detail
```

Important states:

```text
available campaign
joined campaign
locked/private campaign later
expired campaign
no matching results
```

## Workflow 3: Open campaign detail

Primary route:

```text
training-campaign-detail.php?campaign=5-day-movement-challenge
```

Participant goal:

```text
Understand the campaign requirements and decide to join or continue.
```

Expected page sections:

```text
campaign hero
campaign description
rules/requirements
sequence overview
reward ladder
current progress if joined
next reward
primary CTA
```

Primary actions:

```text
Join Campaign
Continue Campaign
Open Sequence
View Reward Ladder
```

Important states:

```text
not joined
joined
already completed
campaign paused
campaign expired
```

## Workflow 4: Join campaign

Route/action:

```text
POST join action on training-campaign-detail.php
```

Participant goal:

```text
Enter the campaign and begin tracking progress.
```

Expected system result:

```text
training_participants record created
joined_at timestamp recorded
participant status active
training.campaign.joined event logged
```

User-visible result:

```text
Joined state appears
CTA changes to Continue Campaign or Start Sequence
Progress starts at 0 approved tasks
```

Error states:

```text
must sign in
already joined
campaign not available
campaign full/capped later
```

## Workflow 5: View sequence/tasks

Primary route:

```text
training-sequence.php?campaign=5-day-movement-challenge
```

Participant goal:

```text
Know exactly what to do next.
```

Expected page sections:

```text
campaign context
sequence title
step tracker
current task card
proof requirements
progress summary
completed steps
next reward preview
```

Primary actions:

```text
Open Proof Upload
View Previous Submission
View Campaign Details
```

Task statuses:

```text
locked
available
current
pending_review
approved
rejected
needs_resubmission
completed
```

## Workflow 6: Complete task and upload proof

Primary route:

```text
training-proof-upload.php?campaign=5-day-movement-challenge&task=movement-session
```

Participant goal:

```text
Submit evidence that the task was completed.
```

Expected page sections:

```text
task summary
proof instructions
accepted file types
upload field/drop zone
selected file preview
participant note
previous submissions
submission checklist
streak/next reward preview
```

Primary actions:

```text
Choose File
Remove File before submit
Submit Proof
Return to Sequence
```

Expected system result:

```text
training_files record created
training_task_submissions record created
status pending_review
attempt number set
training.proof.uploaded event logged
```

Important validation states:

```text
no file selected
unsupported file type
file too large
upload failed
pending review
```

## Workflow 7: Wait for review

Participant goal:

```text
Understand what happens after proof is submitted.
```

Expected UI locations:

```text
Sequence / Tasks page
Proof Upload page
Rewards & Progress page later
Profile / Wallet page later
```

Visible status:

```text
Pending Review
```

Message:

```text
Your proof has been submitted and is waiting for review.
```

Expected behavior:

```text
Pending proof does not count as approved completion
Pending proof does not unlock reward
Participant can view submission history
```

## Workflow 8: Approved proof

Participant goal:

```text
See that the task was accepted and progress increased.
```

Expected system result:

```text
review approved
submission status approved
task completion Action Receipt created
progress updates
sequence completion checked
reward rules evaluate if sequence completed
```

Expected UI result:

```text
task status approved/completed
progress bar increases
receipt appears in history later
next task becomes current
```

## Workflow 9: Rejected proof

Participant goal:

```text
Understand why proof was rejected and what, if anything, to do next.
```

Expected system result:

```text
review rejected
submission status rejected
reviewer note stored
no receipt created
progress does not increase
```

Expected UI result:

```text
Rejected status shown
Reviewer note visible where appropriate
Participant can see prior attempt
Resubmission may or may not be allowed depending action used
```

## Workflow 10: Resubmission requested

Participant goal:

```text
Submit a corrected proof attempt.
```

Expected system result:

```text
submission status needs_resubmission
reviewer note stored
participant can submit another attempt
new attempt number increments
prior attempt remains visible
```

Expected UI result:

```text
Needs Resubmission status shown
Reviewer note explains request
Upload action is available again
```

## Workflow 11: Complete sequence

Participant goal:

```text
Finish all required tasks and unlock reward eligibility.
```

Required condition:

```text
All required tasks have approved proof
```

Expected system result:

```text
sequence completion Action Receipt created
streak data updates
reward rules evaluate
reward eligibility event is logged
```

Expected UI result:

```text
Sequence Complete status
Reward eligibility or next step visible
Action Receipt visible in history later
```

## Workflow 12: View rewards and progress

Primary route:

```text
training-rewards.php
```

Participant goal:

```text
Understand rewards earned, next rewards, current streak, and issue/claim status.
```

Expected page sections:

```text
reward ladder
current streak
points/progress summary
available rewards
earned rewards
claimed/redeemed history
recent activity
```

Reward states shown:

```text
locked
eligible
needs_linked_account
pending_issue
issued
failed
claimed
redeemed
expired
```

## Workflow 13: View profile/wallet

Primary route:

```text
training-profile-wallet.php
```

Participant goal:

```text
See account connection, wallet status, rewards, receipts, teams, and preferences.
```

Expected page sections:

```text
profile summary
linked Microgifter account status
wallet summary
available rewards
earned rewards
recent activity
claimed history
team membership later
```

Primary actions:

```text
Manage Microgifter Connection
View Reward
Open Existing Local Quest Wallet
Update Preferences later
```

## Participant MVP done definition

The participant side is MVP-complete when a signed-in user can:

```text
Open Training Lab
Browse campaigns
Join 5-Day Movement Challenge
Open sequence
Upload proof
See pending review
See approved task after admin approval
Complete all required tasks
See sequence complete
See reward eligibility/status
Open rewards/wallet view
```

## Participant failure states that must be clear

```text
Not signed in
Campaign unavailable
Already joined
Task locked
No file selected
Unsupported file type
File too large
Upload failed
Proof pending review
Proof rejected
Resubmission required
Reward needs linked account
Reward issue failed
No rewards yet
```

## Participant UX rules

```text
Always show the next action clearly
Do not hide rejected/resubmission state
Do not mark pending proof as completed
Do not unlock rewards from pending proof
Use large mobile CTAs on task/proof pages
Keep proof instructions close to upload control
Show reviewer notes when resubmission is requested
```
