# Training Campaign Lab UI Layout Specification

## Purpose

This build-ready UI specification translates the approved desktop and mobile mockups into implementation guidance.

Use this document as the source of truth when building page structure, reusable components, and responsive behavior.

## Global design direction

The app should feel:

```text
minimal
clean
light
structured
mobile-friendly
SaaS/product-ready
easy to scan
easy to build
```

Visual system:

```text
Background: white / very light blue-gray
Cards: white with subtle border and shadow
Primary accent: bright Microgifter blue
Text: dark navy headings, slate body text
Success: green
Warning/Pending: orange
Danger/Failure: red
Locked/Disabled: gray
Border radius: soft rounded cards
Icons: thin line icons or simple filled product icons
```

## App shell

### Desktop shell

Desktop pages should use:

```text
Persistent left sidebar
Top header
Main content area
Optional right detail panel
Reusable card grid system
```

### Mobile shell

Mobile pages should use:

```text
Compact top app bar
Hamburger menu
Optional breadcrumbs
Single-column content
Bottom nav on participant pages
Sticky bottom CTA when a page has one main action
```

## Page 1: App Landing / Dashboard

### Purpose

The signed-in app landing page gives a quick overview of active campaigns, tasks due, streak status, reward progress, and recent activity.

### Primary users

```text
Participant
Admin
Reviewer
```

### Desktop layout

```text
Header/sidebar shell
Welcome heading
KPI cards: Active Campaigns, Tasks Due, Current Streak, Rewards Issued
Active Campaigns panel
Today’s Tasks panel
Reward Progress card
Recent Activity / Proof Submissions panel
```

### Mobile layout

```text
Compact app header
Stacked KPI cards
Active campaign cards
Today’s tasks list
Reward progress card
Short recent activity feed
Bottom nav active on Dashboard/Overview
```

### Main components

```text
MetricCard
CampaignCard
TaskList
RewardProgressCard
ActivityFeed
MobileBottomNav
```

### Primary actions

```text
View Campaign
Continue Task
Open Proof Upload
View Rewards
```

### Empty states

```text
No active campaigns
No tasks due
No rewards yet
No recent activity
```

## Page 2: Campaigns

### Purpose

Browse active campaigns, filter by type, join campaigns, or open existing campaign details.

### Primary users

```text
Participant
Admin
```

### Desktop layout

```text
Page title and subtitle
Top summary cards
Search input
Filter chips
Campaign grid
Optional tips/summary card
```

### Mobile layout

```text
Title/subtitle
Full-width search
Horizontal filter chips
Sort control
Stacked summary cards
Stacked campaign cards
Bottom nav active on Campaigns
```

### Main components

```text
SearchInput
FilterChips
MetricCard
CampaignCard
StatusPill
```

### Required data

```text
campaign title
description
status
sequence count
participant count
reward preview
campaign image/icon
campaign type
CTA state
```

### Primary actions

```text
View Campaign
Join Campaign
Filter Campaigns
Search Campaigns
```

### Empty states

```text
No campaigns available
No campaigns match filter
Campaign expired
```

## Page 3: Campaign Detail

### Purpose

Show the selected campaign summary, progress, reward ladder, rules, sequence overview, and next action.

### Primary users

```text
Participant
Admin
```

### Desktop layout

```text
Campaign hero card
Right summary cards: Next Reward, Current Streak, Progress Summary, Campaign Details
Reward ladder full-width card
Sequence overview card
Rules & Requirements card
Bottom encouragement CTA banner
```

### Mobile layout

```text
Back link
Campaign summary stack
Compact metrics
Next Reward card
Streak card
Reward ladder horizontal strip
Sequence overview
Rules & Requirements
Sticky bottom Continue CTA
```

### Main components

```text
CampaignHero
RewardLadder
ProgressSummary
SequenceOverview
RequirementCard
StickyCTA
```

### Required data

```text
campaign title
description
status
start/end date
participant count
current step
next reward
streak count
sequence steps
rules
```

### Primary actions

```text
Start / Continue Campaign
Continue to Current Step
Share Campaign
View Campaign Info
```

### Empty/error states

```text
Campaign not found
Campaign expired
Participant not joined
Campaign locked
```

## Page 4: Sequence / Tasks

### Purpose

Guide participant through the current sequence and task execution flow.

### Primary user

```text
Participant
```

### Desktop layout

```text
Breadcrumbs
Title/subtitle
Horizontal step tracker
Current task card
Upload proof area or button
Progress summary card
Completion checklist
Completed steps
Tip card
```

### Mobile layout

```text
Compact top bar
Breadcrumbs optional
Horizontal step tracker
Current task full-width card
Progress summary card
Completion checklist
Completed steps
Sticky Open Proof Upload CTA
```

### Main components

```text
StepTracker
TaskCard
ProofRequirementList
ProgressRing
CompletionChecklist
CompletedStepRow
TipCard
StickyCTA
```

### Required data

```text
sequence title
current task
task instructions
proof requirements
due time
step statuses
streak count
next reward
completed steps
```

### Primary actions

```text
Open Proof Upload
View Proof
View Sequence Details
```

### Empty/error states

```text
No current task
Sequence completed
Task locked
Task expired
```

## Page 5: Proof Upload

### Purpose

Let participant upload photo/video proof, add notes, review file requirements, and submit proof for review.

### Primary user

```text
Participant
```

### Desktop layout

```text
Task summary card
Proof requirements
Example guidance card
Large upload drop zone
Selected file preview
Optional note field
Previous submissions
Submission checklist
Progress/streak/next reward cards
Submit Proof action
```

### Mobile layout

```text
Task summary stack
Upload drop zone
Selected file row
Notes textarea
Previous submission card
Submission checklist
Streak and next reward cards
Sticky Submit Proof CTA
```

### Main components

```text
TaskSummaryCard
UploadDropZone
SelectedFileRow
NotesField
PreviousSubmissionRow
SubmissionChecklist
StickyCTA
```

### Required data

```text
task title
due time
proof type
accepted file types
max file size
example guidance
selected file metadata
previous submissions
submission status
```

### Primary actions

```text
Choose Files
Remove File
Submit Proof
View Previous Submission
```

### Empty/error states

```text
No file selected
Unsupported file type
File too large
Upload failed
Microgifter/account session expired
Task already submitted
Task not available yet
```

## Page 6: Rewards & Progress

### Purpose

Show reward ladder progress, issued rewards, claimed rewards, current streak, and milestone activity.

### Primary user

```text
Participant
```

### Desktop layout

```text
Reward ladder hero card
Current Streak card
Progress Summary card
Issued Rewards card
Claimed Rewards card
Next Reward card
Recent Activity & Milestone History
Help card
```

### Mobile layout

```text
Title/subtitle
How Rewards Work button
Reward ladder horizontal card
Progress cards stacked/2-column
Recent activity list
Help card
Bottom nav active on Rewards
```

### Main components

```text
RewardLadder
StreakCard
ProgressRing
RewardSummaryCard
ActivityHistory
HelpCard
```

### Required data

```text
points earned
next reward threshold
reward levels
current streak
issued reward count
claimed reward count
activity history
milestone status
```

### Primary actions

```text
View Reward Details
View All Rewards
View Full History
How Rewards Work
```

### Empty/error states

```text
No rewards earned
No milestones yet
Reward failed
Reward expired
```

## Page 7: Review Queue

### Purpose

Let reviewers inspect proof submissions and approve, reject, or request resubmission.

### Primary users

```text
Reviewer
Admin
Manager
Coach
```

### Desktop layout

```text
KPI cards
Review status tabs
Search/filter controls
Queue list/table
Selected proof detail panel
Proof preview
Participant note
Reviewer note field
Approve / Reject / Request Resubmission actions
```

### Mobile layout

```text
KPI cards stacked or compact
Status tabs horizontal scroll
Search/filter row
Submission list rows
Selected proof expanded card/drawer
Large action buttons
Bottom nav active on Review Queue
```

### Main components

```text
MetricCard
StatusTabs
SearchInput
ReviewQueueRow
ReviewDetailPanel
ProofPreview
ReviewerNoteField
ActionButtons
```

### Required data

```text
submission ID
participant
campaign
task
submitted timestamp
status
proof file
participant note
reviewer note
review action history
```

### Primary actions

```text
Approve
Reject
Request Resubmission
Filter Queue
Open Proof
Message Participant
```

### Empty/error states

```text
No pending reviews
Proof file unavailable
Submission already reviewed
Reviewer permission denied
```

## Page 8: Participants & Teams

### Purpose

Track participant progress, team performance, invitations, statuses, and at-risk users.

### Primary users

```text
Admin
Manager
Coach
Reviewer
```

### Desktop layout

```text
Top summary cards
Invite/Add actions
Participants/Teams tabs
Search/filter row
Participant table/list
Segmentation strip
Team performance cards
Status explanation card
```

### Mobile layout

```text
Top metrics 2-column grid
Invite/Add buttons
Tabs
Search/filter
Participant rows
Segmentation card
Team performance carousel/cards
Status explanation card
```

### Main components

```text
MetricCard
ParticipantRow
TeamCard
SegmentationStrip
Tabs
SearchInput
StatusPill
```

### Required data

```text
participant name/email
team
campaign
progress percent
streak
status
invitation status
team completion rate
at-risk status
```

### Primary actions

```text
Invite Participants
Add Participant
Filter Participants
View Participant
View Team
```

### Empty/error states

```text
No participants yet
No teams yet
Invite failed
Participant at risk
```

## Page 9: Action Receipts & History

### Purpose

Review verified completions, reward issue status, receipt IDs, and event timeline for every verified action.

### Primary users

```text
Admin
Reviewer
Participant for own receipts
```

### Desktop layout

```text
KPI cards
Search/date/type/status filters
Receipts table
Right receipt details panel
Event timeline
Info card: What is an Action Receipt?
Recent system events
```

### Mobile layout

```text
KPI cards 2-column grid
Search and filters
Receipt list rows
Selected receipt expanded drawer/card
Event timeline
Load More action
```

### Main components

```text
MetricCard
ReceiptRow
ReceiptDetailPanel
EventTimeline
SearchInput
FilterDropdowns
InfoCard
```

### Required data

```text
receipt ID
participant
campaign
receipt type
points/value
reward status
proof/review info
event timeline
reward issue status
```

### Primary actions

```text
Open Receipt
Copy Receipt ID
View Proof
View Reward
View Full Audit Log
Export Receipts
```

### Empty/error states

```text
No receipts yet
Receipt not found
Reward issue failed
Audit event missing
```

## Page 10: Settings

### Purpose

Manage organization, team, notification, privacy, integration, branding, and security settings.

### Primary users

```text
Owner
Admin
```

### Desktop layout

```text
Settings title/subtitle
Organization Settings card
Team Permissions card
Notifications card
Privacy & Retention card
Integrations card
Branding / Theme card
Account Security card
Save Changes button
```

### Mobile layout

```text
Settings title/subtitle
Accordion cards
Organization Settings expanded by default
Integrations can expand inline
Save Changes sticky bottom button
```

### Main components

```text
SettingsSectionCard
SettingsAccordionRow
ToggleRow
DropdownField
TextInput
IntegrationRow
SaveButton
```

### Required data

```text
organization name
time zone
week start day
roles/permissions
notification preferences
retention policy
integration status
brand color
logo upload
security status
```

### Primary actions

```text
Save Changes
Connect Integration
Upload Logo
Change Password
Toggle 2FA
```

### Empty/error states

```text
Settings save failed
Integration disconnected
Permission denied
Invalid organization name
```

## Page 11: Templates

### Purpose

Browse reusable campaign templates and start a new campaign from a template.

### Primary users

```text
Admin
Owner
Manager
Coach
```

### Desktop layout

```text
Template search
Category filters
Sort dropdown
Template card grid
Selected template detail panel
Suggest template callout
```

### Mobile layout

```text
Compact header
Search field
Filter chips horizontal scroll
Stacked template cards
Use Template CTA on each card
```

### Main components

```text
TemplateCard
TemplateDetailPanel
SearchInput
FilterChips
SortDropdown
```

### Required data

```text
template name
description
category
tags
duration
difficulty
included features
preview sequence
```

### Primary actions

```text
Use Template
View Full Details
Suggest Template
Filter Templates
Search Templates
```

### Empty/error states

```text
No templates available
No templates match search
Template unavailable
```

## Page 12: Campaign Builder

### Purpose

Create and configure a new training campaign.

### Primary users

```text
Admin
Owner
Manager
Coach
```

### Desktop layout

```text
Builder stepper
Campaign Basics form
Team & Audience form
Campaign Sequence overview
Proof Requirements form
Right Live Preview panel
Sticky footer actions
```

### Mobile layout

```text
Horizontal builder stepper
Stacked form cards
Sequence overview list
Proof requirements card
Sticky Save Draft / Continue actions
```

### Main components

```text
BuilderStepper
BuilderFormCard
TextInput
Textarea
DropdownField
DateField
TeamSelector
SequenceStepList
LivePreviewPanel
StickyActions
```

### Required data

```text
campaign title
description
campaign type
visibility
start/end dates
team/audience
sequence steps
task counts
proof defaults
reward summary
```

### Primary actions

```text
Save Draft
Continue to Sequence
Add Step
Remove Step
Update Preview
```

### Empty/error states

```text
Missing required title
Invalid dates
No audience selected
Draft save failed
```

## Page 13: Reward Rules Builder

### Purpose

Configure reward ladder rules, eligibility, budgets, expiration, and linked Microgift templates.

### Primary users

```text
Admin
Owner
Manager
```

### Desktop layout

```text
Reward level cards
Rule editor grid
Logic preview list
Preview Eligibility button
Save Rules button
Info strip
```

### Mobile layout

```text
Reward level horizontal strip
Stacked rule fields
Logic preview card/carousel
Sticky Save Rules button
```

### Main components

```text
RewardRuleLevelCard
RewardRuleForm
NumberStepper
DropdownField
CurrencyField
LogicPreviewRow
StickyCTA
```

### Required data

```text
level name
points required
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

### Primary actions

```text
Select Level
Update Rule
Preview Eligibility
Save Rules
```

### Empty/error states

```text
Invalid reward value
Budget cap exceeded
Missing Microgift template
No eligible participants
```

## Page 14: User Profile / Wallet

### Purpose

Let users manage profile, linked Microgifter account, wallet/rewards, streaks, teams, and account preferences.

### Primary users

```text
Participant
Admin
Reviewer
```

### Desktop layout

```text
Profile summary card
Linked Microgifter account section
Streak summary
Rewards summary
Quick actions
Earned rewards grid
Recent activity
Claimed/redeemed history
Teams joined
Account preferences
```

### Mobile layout

```text
Profile summary card
Linked account card
Streak card
Wallet summary cards
Earned rewards horizontal scroll
Recent activity list
Bottom nav active on Profile
```

### Main components

```text
WalletProfileSummary
LinkedAccountStatus
StreakCard
WalletSummaryCard
RewardCard
ActivityRow
ClaimedHistoryTable
TeamList
PreferenceList
```

### Required data

```text
user profile
role
email
member since
wallet ID
linked status
available rewards
points earned
earned rewards
claimed history
recent activity
team membership
preferences
```

### Primary actions

```text
Manage Connection
Browse Rewards Catalog
Claim Reward
View Reward
Update Profile
Update Preferences
```

### Empty/error states

```text
Microgifter account not linked
No rewards available
No recent activity
Wallet sync failed
```

## Page 15: Audit Logs

### Purpose

Search and review all system events, user actions, proof changes, campaign updates, and reward events.

### Primary users

```text
Admin
Owner
Compliance reviewer
```

### Desktop layout

```text
Audit Logs title/subtitle
Search/date/filter toolbar
Advanced filter row
Audit event table
Right Event Details drawer
Payload summary
Related IDs
Metadata
```

### Mobile layout

```text
Compact title
Search field
Date/filter controls
Stacked event cards
Selected event bottom drawer
View Full Details CTA
```

### Main components

```text
AuditSearchBar
FilterDropdowns
AuditLogRow
AuditEventCard
AuditDetailDrawer
PayloadCodeBlock
StatusPill
```

### Required data

```text
timestamp
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

### Primary actions

```text
Search Events
Filter Events
Export Logs
Open Event Detail
View Full Details
```

### Empty/error states

```text
No events found
Export failed
Event unavailable
Permission denied
```

## Build note

The first implementation should focus on the MVP route priority while keeping this 15-page app map in mind. The admin creation pages can remain static/mock-driven until the participant proof/review/reward vertical slice works.
