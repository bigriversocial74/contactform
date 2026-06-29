# Training Campaign Lab UI Mockup Index

## Purpose

This document indexes the approved UI mockups and explains the intended role of each screen.

Mockup image files are intended to live in:

```text
docs/training-campaign-lab/ui/mockups/
```

## Image naming convention

```text
NN-page-name-desktop.png
NN-page-name-mobile.png
NN-page-name-responsive.png
```

Use `responsive` when a single board includes both desktop and mobile versions.

## Core page mockups

## 1. App Landing / Dashboard

Image:

```text
ui/mockups/01-dashboard-desktop.png
```

Purpose:

```text
Signed-in app landing page with active campaign overview, today's tasks, reward progress, streak status, and recent activity.
```

Key UI sections:

```text
KPI cards
Active Campaigns
Today’s Tasks
Reward Progress
Recent Activity
```

Build notes:

```text
Use this as the default internal landing page after sign-in.
The dashboard should summarize participant and admin activity without becoming a dense analytics page.
```

## 2. Campaigns

Images:

```text
ui/mockups/02-campaigns-desktop.png
ui/mockups/02-campaigns-mobile.png
```

Purpose:

```text
Browse active campaigns, search/filter campaigns, and open or join a campaign.
```

Key UI sections:

```text
Search
Filter chips
Summary cards
Campaign cards
Campaign CTA
```

Build notes:

```text
Campaign cards should be reusable across landing, campaigns, templates, and participant flows.
Mobile uses stacked campaign cards and bottom navigation.
```

## 3. Campaign Detail

Images:

```text
ui/mockups/03-campaign-detail-desktop.png
ui/mockups/03-campaign-detail-mobile.png
```

Purpose:

```text
Show a campaign overview, reward ladder, progress, current streak, campaign rules, and next action.
```

Key UI sections:

```text
Campaign hero
Next reward
Current streak
Reward ladder
Sequence overview
Rules & Requirements
Sticky CTA on mobile
```

Build notes:

```text
The campaign detail page is the main bridge between discovery and task execution.
The primary CTA should always route the participant to the next incomplete step.
```

## 4. Sequence / Tasks

Images:

```text
ui/mockups/04-sequence-tasks-desktop.png
ui/mockups/04-sequence-tasks-mobile.png
```

Purpose:

```text
Guide participants through the current sequence and task flow.
```

Key UI sections:

```text
Step tracker
Current task
Proof requirements
Progress summary
Completion checklist
Completed steps
Tip card
Sticky Open Proof Upload CTA
```

Build notes:

```text
This page should be simple and action-oriented.
Do not overload it with admin controls.
```

## 5. Proof Upload

Images:

```text
ui/mockups/05-proof-upload-desktop.png
ui/mockups/05-proof-upload-mobile.png
```

Purpose:

```text
Allow photo/video proof upload, optional notes, selected file preview, and proof submission.
```

Key UI sections:

```text
Task context
Proof requirements
Upload area
Selected file preview
Notes field
Previous submissions
Submission checklist
Streak and next reward
Sticky Submit Proof CTA on mobile
```

Build notes:

```text
This is one of the most important MVP screens.
Make upload validation, file status, and errors extremely clear.
```

## 6. Rewards & Progress

Images:

```text
ui/mockups/06-rewards-progress-desktop.png
ui/mockups/06-rewards-progress-mobile.png
```

Purpose:

```text
Show reward ladder, current streak, progress summary, issued/claimed rewards, next reward, and milestone history.
```

Key UI sections:

```text
Reward Ladder
Current Streak
Progress Summary
Issued Rewards
Claimed Rewards
Next Reward
Recent Activity & Milestone History
```

Build notes:

```text
Reward state should be clear: locked, in progress, eligible, issued, claimed, redeemed, expired, or failed.
```

## 7. Review Queue

Images:

```text
ui/mockups/07-review-queue-desktop.png
ui/mockups/07-review-queue-mobile.png
```

Purpose:

```text
Operational reviewer page for approving, rejecting, or requesting resubmission of proof.
```

Key UI sections:

```text
Pending review metrics
Status tabs
Search/filter
Submission queue
Proof detail panel
Participant note
Reviewer note
Approve / Reject / Request Resubmission actions
```

Build notes:

```text
Desktop can use a list + side detail panel.
Mobile should use an expanded detail card or bottom drawer.
```

## 8. Participants & Teams

Images:

```text
ui/mockups/08-participants-teams-desktop.png
ui/mockups/08-participants-teams-mobile.png
```

Purpose:

```text
Track participants, team grouping, progress, status, invitations, and team completion rates.
```

Key UI sections:

```text
Participant metrics
Invite/Add buttons
Participants/Teams tabs
Participant rows
Segmentation summary
Team performance cards
Status explanation
```

Build notes:

```text
Participant status and progress should become the admin's main coaching/management view.
```

## 9. Action Receipts & History

Images:

```text
ui/mockups/09-action-receipts-desktop.png
ui/mockups/09-action-receipts-mobile.png
```

Purpose:

```text
Show verified completions, proof/review/reward linkage, receipt IDs, reward issue status, and event timeline.
```

Key UI sections:

```text
Receipt metrics
Search/filter controls
Receipt list/table
Receipt detail panel
Event timeline
Recent system events
```

Build notes:

```text
This page is key to the verified-action data layer.
Receipts should be durable and independent from changing reward status.
```

## Additional admin/builder page mockups

## 10. Settings

Image:

```text
ui/mockups/10-settings-responsive.png
```

Purpose:

```text
Manage organization settings, roles, notifications, privacy/retention, integrations, branding, and account security.
```

Key UI sections:

```text
Organization Settings
Team Permissions
Notifications
Privacy & Retention
Integrations
Branding / Theme
Account Security
```

Build notes:

```text
Mobile settings should use accordions and a sticky Save Changes button.
```

## 11. Templates

Image:

```text
ui/mockups/11-templates-responsive.png
```

Purpose:

```text
Browse reusable campaign templates and start a campaign from a proven structure.
```

Key UI sections:

```text
Template search
Category chips
Template grid/list
Selected template preview
Use Template CTA
```

Build notes:

```text
Templates should speed up campaign creation and reduce setup friction.
```

## 12. Campaign Builder

Image:

```text
ui/mockups/12-campaign-builder-responsive.png
```

Purpose:

```text
Create and configure campaign basics, sequence steps, tasks, proof rules, rewards, and publishing settings.
```

Key UI sections:

```text
Builder stepper
Campaign basics form
Team/audience selector
Sequence overview
Proof requirement defaults
Live preview
Save Draft / Continue actions
```

Build notes:

```text
Start with static form sections and a live preview panel.
Wire up persistence after the MVP proof/review/reward loop works.
```

## 13. Reward Rules Builder

Image:

```text
ui/mockups/13-reward-rules-builder-responsive.png
```

Purpose:

```text
Configure reward ladder levels, eligibility triggers, budgets, expiration, and linked Microgift templates.
```

Key UI sections:

```text
Reward level selector
Trigger type
Required completions
Streak requirement
Milestone target
Reward value
Reward type
Expiration
Budget cap
Linked Microgift template
Logic preview
```

Build notes:

```text
Rules should evaluate in order and participants should receive the highest level they qualify for.
```

## 14. User Profile / Wallet

Image:

```text
ui/mockups/14-user-profile-wallet-responsive.png
```

Purpose:

```text
Show user profile, linked Microgifter account, wallet status, earned rewards, claimed/redeemed rewards, streaks, teams, and account preferences.
```

Key UI sections:

```text
Profile summary
Linked Microgifter account
Streak summary
Rewards summary
Earned Rewards
Recent Activity
Claimed / Redeemed History
Teams
Account Preferences
```

Build notes:

```text
This page connects Training Campaign progress to the user's reward wallet and Microgifter identity.
```

## 15. Audit Logs

Image:

```text
ui/mockups/15-audit-logs-responsive.png
```

Purpose:

```text
Search and review system events, user actions, proof changes, campaign updates, and reward events.
```

Key UI sections:

```text
Search/filter toolbar
Date range
Event table/list
Event details drawer
Payload summary
Related IDs
Metadata
```

Build notes:

```text
Audit Logs should help admins debug proof, review, receipt, reward, and webhook events.
```

## Implementation note

The mockups are visual guides, not exact code requirements. Build should prioritize:

```text
clear structure
consistent components
responsive behavior
proof/review/reward logic
minimal visual drift
```
