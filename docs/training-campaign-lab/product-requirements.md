# Training Campaign Lab Product Requirements

## Purpose

Training Campaign Lab is a proof-of-action campaign module for Microgifter.

It extends the Loyalty Quest / Local Quest Rewards concept into a reusable system where organizations can create training, consistency, onboarding, wellness, creator, merchant, or community campaigns that reward verified action.

The product should prove one core idea:

```text
Verified action can become structured rewardable data.
```

## Product statement

Microgifter Training Campaigns let organizations create action-based challenges, assign them to participants or teams, collect proof of completion, review submissions, generate Action Receipts, and issue Microgifter rewards for completed sequences, streaks, and milestones.

## Primary users

### Participant

A person who joins a campaign, completes tasks, uploads proof, and earns rewards.

Examples:

```text
employee
customer
student
creator
fitness participant
merchant staff member
community member
```

### Reviewer

A person who reviews proof submissions and approves, rejects, or requests resubmission.

Examples:

```text
manager
coach
trainer
merchant owner
program admin
```

### Admin / Manager

A person who manages campaigns, participants, reward rules, receipts, and reporting.

### Owner

A top-level organization user who can manage settings, permissions, integrations, branding, and audit logs.

## MVP product goal

Build one complete vertical slice:

```text
Participant joins 5-Day Movement Challenge
Participant views sequence/tasks
Participant uploads proof
Reviewer approves proof
System creates Action Receipt
Reward rule evaluates eligibility
Microgifter reward issue is created
Participant sees reward status in wallet/rewards page
```

## MVP campaign

The first MVP campaign is:

```text
5-Day Movement Challenge
```

It should include:

```text
one campaign
one sequence
at least four tasks
photo/video proof requirement
manual reviewer approval
Action Receipt creation
one reward rule
one reward issue path
wallet/reward status display
```

## MVP scope

Included in MVP:

```text
Training Lab dashboard
Campaign list
Campaign detail
Participant join
Sequence/task view
Proof upload
Manual admin review
Approve/reject/request resubmission
Action Receipt creation
Reward rule evaluation
Reward issue record
Reward status display
Basic event logging
Basic validation script
Mobile-friendly layout
```

Not included in MVP:

```text
AI proof review
Computer vision scoring
Wearable integrations
Automated fraud scoring
Advanced notifications
Template marketplace
Billing/subscriptions
Sponsor marketplace
Public API endpoints
Leaderboard automation
White-label embeds
Advanced analytics
```

## Core product objects

```text
Campaign
Sequence
Task
Participant
Proof File
Task Submission
Review
Action Receipt
Reward Rule
Reward Issue
Streak
Event
```

## Core actions

### Participant actions

```text
View campaigns
Join campaign
View campaign detail
View sequence/tasks
Upload proof
Add participant note
View submission status
Resubmit proof if requested
View reward progress
View wallet/reward status
```

### Reviewer actions

```text
View pending submissions
Open proof detail
Approve proof
Reject proof
Request resubmission
Add reviewer note
View review history
```

### Admin actions

```text
View campaign progress
View participant progress
View Action Receipts
View reward issues
View audit events
Configure reward rules later
Create campaigns later
Use templates later
```

## Reward logic

Rewards must not issue from unverified action.

Correct reward path:

```text
Task submission pending_review
Reviewer approves proof
Task completion Action Receipt created
All required tasks approved
Sequence completion Action Receipt created
Reward rule evaluates eligible
Reward issue is created
Reward appears in participant reward/wallet view
```

## Proof requirements

MVP accepted proof types:

```text
jpg
jpeg
png
webp
mp4
mov
webm
```

Proof upload must support:

```text
safe stored filename
file metadata record
file size validation
file type validation
submission attempt number
participant note
status tracking
review linkage
```

## Status requirements

Submission statuses:

```text
draft
pending_review
approved
rejected
needs_resubmission
expired
```

Receipt statuses:

```text
created
verified
linked_to_reward
voided
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

## Success criteria

The MVP is successful when:

```text
A participant can complete the full vertical slice
A reviewer can approve proof
Approved proof creates durable receipts
Rewards do not issue before approval
Reward issue status is visible
Original Loyalty Quest still works
Validation script passes
Mobile layout is usable
```

## Business value

Training Campaign Lab creates a new Microgifter product layer:

```text
Proof-of-action campaigns
Verified action receipts
Rewardable training data
Participant consistency data
Campaign-based CRM engagement
Merchant/team/customer reward automation
```

## Key product principle

Do not build a generic task app.

Build a rewardable action-verification layer where completion, proof, review, receipt, and reward status are connected.
