# Training Campaign Lab Component Inventory

## Purpose

This document defines reusable UI components for the Training Campaign Lab pages.

The goal is to avoid building every page with different markup, spacing, status styles, and responsive behavior.

## App shell components

### App header

Used on desktop and mobile.

Required elements:

```text
Microgifter Training Campaign Lab logo/title
Search control on desktop where useful
Notification icon
User avatar / role menu
Mobile hamburger menu
```

### Desktop sidebar

Used on admin and desktop participant views.

Required states:

```text
Default item
Active item
Nested item
Collapsed item
Help/support card
User/account footer
```

### Mobile bottom navigation

Used for participant-first pages.

Recommended items:

```text
Overview
Campaigns
Sequences
Rewards
Profile
```

Reviewer pages may use:

```text
Overview
Review Queue
Rewards
Messages
More
```

### Breadcrumbs

Used on desktop and mobile when page depth is important.

Examples:

```text
Campaigns > New Member Onboarding > Sequence / Tasks
Campaigns > New Member Onboarding > Proof Upload
Campaigns > New Member Onboarding > Action Receipts
```

## Core data components

### Metric card

Used for high-level stats.

Data fields:

```text
icon
label
value
subtext
trend value
trend direction
status color
```

### Status pill

Reusable statuses:

```text
Active
Pending
Approved
Rejected
Needs Resubmission
In Progress
Locked
Issued
Failed
Eligible
Connected
Not Connected
At Risk
Invited
Completed
```

### Progress bar

Used for campaign progress, reward progress, participant progress, and upload progress.

### Progress ring

Used for sequence percentage, team completion rate, and overall progress.

### Step tracker

Used for sequence task progress and campaign builder steps.

Required states:

```text
completed
current
pending
locked
failed
```

### Reward ladder

Used on Campaign Detail, Rewards & Progress, and Reward Rules Builder.

Required elements:

```text
level number
milestone name
points required
reward preview
status
unlock requirement
```

### Event timeline

Used on Action Receipts and Audit Logs.

Required elements:

```text
event icon
event title
description
timestamp
actor
status
```

## Campaign components

### Campaign card

Used on Campaigns and Templates pages.

Required elements:

```text
campaign image or icon
title
description
status pill
sequence/task count
participant count
reward preview
primary CTA
```

### Campaign hero card

Used on Campaign Detail.

Required elements:

```text
campaign icon/image
title
status
summary
participants
start/end date
primary action
```

### Sequence overview card

Used on Campaign Detail and Sequence / Tasks.

Required elements:

```text
step number
step title
description
status
points/reward
chevron/action
```

### Rule / requirement card

Used on Campaign Detail and Sequence pages.

Required elements:

```text
rule icon
rule title
rule description
```

## Proof components

### Proof upload card

Used on Proof Upload and Sequence / Tasks.

Required elements:

```text
proof instructions
accepted file types
max file size
drag/drop upload area
choose files button
selected file preview
upload progress
optional note field
submit button
```

### Selected file row

Required elements:

```text
thumbnail
filename
file size
file type
upload progress/status
remove button
```

### Submission checklist

Required elements:

```text
criteria title
criteria description
checked/unchecked state
```

### Previous submission row

Required elements:

```text
thumbnail
submission title
submitted date/time
review status
view details action
```

## Review components

### Review queue row

Used on Review Queue.

Required elements:

```text
participant avatar
participant name
campaign
sequence/task
submitted time
status pill
chevron/action
```

### Review detail panel

Desktop: right panel or side panel.

Mobile: expanded card or bottom drawer.

Required elements:

```text
participant summary
campaign and task context
proof preview
file metadata
participant note
reviewer note field
approve button
reject button
request resubmission button
```

## Participant and team components

### Participant row

Used on Participants & Teams.

Required elements:

```text
avatar
name
email
team badge
progress percentage
progress bar
status pill
actions menu
```

### Team card

Required elements:

```text
team name
member count
completion rate
trend
view action
```

### Segmentation strip

Required elements:

```text
segment label
count
status color
chevron/action
```

## Receipt and audit components

### Action receipt row

Required elements:

```text
date/time
participant
campaign
receipt type
points/value
reward status
receipt ID
chevron/action
```

### Receipt detail panel

Required elements:

```text
participant
email
campaign
linked task/sequence
proof type
reviewed by
reviewed at
points awarded
reward rule
reward status
receipt ID
copy action
event timeline
```

### Audit log row

Required elements:

```text
timestamp
actor
event type
campaign
participant
status
```

### Audit detail drawer

Required elements:

```text
event title
status
timestamp
actor
campaign
participant
payload summary
related IDs
metadata
```

## Builder components

### Builder stepper

Used on Campaign Builder.

Steps:

```text
Basics
Sequence
Tasks
Proof
Rewards
Publish
```

### Builder form card

Reusable for each builder section.

Required elements:

```text
section title
section description
form fields
validation messages
save/continue actions
```

### Live preview panel

Used on Campaign Builder.

Required elements:

```text
campaign summary
sequence overview
next-up tip
total steps
total tasks
reward points
```

### Template card

Used on Templates.

Required elements:

```text
icon
name
description
tags
duration
difficulty
use template CTA
```

### Template detail panel

Required elements:

```text
template title
category
description
duration
difficulty
best for
included features
preview sequence
use template CTA
view details CTA
```

### Reward rule card

Used on Reward Rules Builder.

Required elements:

```text
level name
reward label
points
selected state
```

### Reward rule form

Required elements:

```text
trigger type
required completions
streak count
milestone target
reward value
reward type
expiration
budget cap
linked Microgift template
```

## Settings components

### Settings section card

Used on desktop.

Required elements:

```text
section icon
section title
fields/toggles/actions
```

### Settings accordion row

Used on mobile.

Required elements:

```text
icon
title
expanded/collapsed chevron
content when expanded
```

### Toggle row

Required elements:

```text
label
description
toggle state
```

## Wallet components

### Wallet profile summary

Required elements:

```text
avatar
name
role
email
member since
linked Microgifter status
wallet ID
streak summary
```

### Reward card

Required elements:

```text
reward logo/icon
reward name
reward amount
points required/status
available/redeemed status
```

### Activity row

Required elements:

```text
activity icon
title
description
date/time
points/status
```

## Mobile-specific components

### Sticky mobile CTA

Used on:

```text
Campaign Detail
Sequence / Tasks
Proof Upload
Campaign Builder
Reward Rules Builder
Settings
```

### Mobile drawer / detail panel

Used on:

```text
Review Queue
Action Receipts
Audit Logs
```

### Horizontal scroll strip

Used for:

```text
Reward ladder
Template categories
Builder steps
Reward rule levels
Earned rewards
```
