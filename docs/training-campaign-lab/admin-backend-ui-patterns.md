# Admin Backend UI Patterns

## Purpose

This document defines the minimal admin backend design set for the future Training Lab by Microgifter build.

The admin backend does not need a custom mockup for every section. The final visual direction can be established with three core admin screens and reused across the remaining backend pages.

This is a planning document only. It does not approve implementation or changes to existing Local Quest files.

## Core admin mockups

The required admin backend mockup set is:

```text
01-admin-overview.png
02-admin-campaigns.png
03-admin-review-queue.png
```

These three screens define the backend UI language for the admin area.

## What the core mockups establish

The admin backend design should consistently use:

```text
left sidebar navigation
top search/header
admin account menu
KPI summary cards
clean data tables
filters and sorting controls
approval/review actions
status pills
right-side helper panels where useful
pagination
dark green primary actions
soft mint secondary states
minimal white background
rounded cards
clear spacing
```

## Admin Overview pattern

Use the Admin Overview screen for dashboard-style pages.

Primary uses:

```text
admin dashboard
reports overview
reward performance overview
program health overview
```

Reusable components:

```text
KPI cards
line chart / activity chart
recent activity feed
pending approvals panel
top campaigns summary
right or lower summary cards
```

Guidance:

```text
Keep the overview scannable.
Use 4 primary metrics max in the top row.
Avoid overloading the first screen with every admin stat.
Make pending action areas easy to find.
```

## Admin Campaigns pattern

Use the Admin Campaigns screen for management/list pages.

Primary uses:

```text
campaign management
participants list
teams list
rewards list
templates list when displayed as a table
audit logs when a simple table is enough
```

Reusable components:

```text
summary KPI cards
search bar
filter dropdowns
sort dropdown
primary action button
large table
status pills
row action menu
pagination
```

Guidance:

```text
Tables should prioritize the few fields that help an admin make decisions.
Secondary details can move into detail views, drawers, or row actions.
Do not make every table dense by default.
```

## Admin Review Queue pattern

Use the Review Queue screen for approval, moderation, and verification workflows.

Primary uses:

```text
proof review queue
reward approval queue
flagged submissions
changes requested queue
manual verification queue
```

Reusable components:

```text
status tabs
queue summary
review table
proof thumbnail
approve action
request resubmission action
quick filters
review guidelines helper card
status badges
```

Guidance:

```text
Review pages should focus on the action the admin needs to take.
Proof preview, participant, campaign, task, and status should be visible without opening a detail page.
Keep approve/request changes actions visually clear.
```

## Backend pages that do not need unique mockups

The following sections should inherit from the core admin patterns instead of receiving separate custom designs:

```text
Participants
Teams
Rewards
Reports
Settings
Templates
Audit Logs
```

## Pattern mapping

```text
Participants -> Admin Campaigns table pattern
Teams -> Admin Campaigns table pattern
Rewards -> Admin Overview plus Admin Campaigns table/card pattern
Reports -> Admin Overview chart/KPI pattern
Settings -> Existing app settings form pattern
Templates -> Existing templates card-grid pattern or Admin Campaigns table pattern
Audit Logs -> Admin Campaigns table pattern with status/event filters
```

## Suggested admin navigation

The backend/admin navigation should remain simple:

```text
Overview
Campaigns
Participants
Submissions
Review Queue
Rewards
Reports
Settings
```

Optional sections:

```text
Teams
Templates
Audit Logs
Reward Rules
```

Optional sections should only appear when the feature is available or relevant to the logged-in admin role.

## Relationship to participant app screens

The backend admin interface should share the same design language as the participant app:

```text
same logo placement
same dark green accent color
same soft mint states
same rounded card system
same typography direction
same icon language
```

However, the admin pages should feel more operational:

```text
more tables
more filters
more approval actions
more queue views
more reporting summaries
```

## Implementation note

During future implementation, build the admin layout once and reuse it.

Do not create separate custom layout structures for each admin page unless a workflow clearly requires it.
