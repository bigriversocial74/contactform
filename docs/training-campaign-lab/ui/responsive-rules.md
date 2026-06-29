# Training Campaign Lab Responsive Rules

## Purpose

This document defines how desktop UI patterns convert to mobile layouts.

The goal is to keep the mobile app simple, touch-friendly, and consistent with the desktop mockups.

## Global responsive behavior

### Desktop

```text
Persistent left sidebar
Top app header
Two-column or three-column content where useful
Tables allowed for dense admin data
Right-side detail panels allowed
Footer actions can sit in page footer or card footer
```

### Mobile

```text
Top app bar
Hamburger menu
Bottom navigation for participant pages
Single-column content
Stacked cards
Tables become list rows
Right-side panels become bottom drawers or expanded cards
Important actions become sticky bottom buttons
Horizontal overflow allowed for reward ladders and steppers
```

## Breakpoints

Recommended CSS breakpoints:

```text
Mobile: 0px - 767px
Tablet: 768px - 1023px
Desktop: 1024px+
Wide desktop: 1280px+
```

## App shell behavior

### Sidebar

Desktop:

```text
Show full sidebar with labels and nested nav.
```

Mobile:

```text
Hide sidebar behind hamburger menu.
Use bottom nav for primary participant actions.
Use drawer menu for admin tools.
```

### Top header

Desktop:

```text
Logo/title left
Search control where useful
Notification icon
User menu
```

Mobile:

```text
Hamburger left
Compact logo/title
Notification icon
Avatar/menu
Avoid large search in top bar unless page requires it
```

### Bottom navigation

Mobile participant nav:

```text
Overview
Campaigns
Sequences
Rewards
Profile
```

Mobile reviewer/admin nav:

```text
Overview
Review Queue
Rewards
Messages
More
```

## Page layout conversions

## 1. Dashboard

Desktop:

```text
KPI row
Active campaign list
Today’s tasks
Reward progress
Recent activity side/right panel
```

Mobile:

```text
KPI cards stack
Active campaign cards stack
Today’s tasks become compact list
Reward progress card moves near top
Recent activity becomes short feed
```

## 2. Campaigns

Desktop:

```text
Campaign grid
Search + filters in one row
Summary stats at top
Optional right summary panel
```

Mobile:

```text
Search full width
Filter chips horizontally scroll
Sort control below or beside chips
Stats stack vertically
Campaign cards stack vertically
Bottom nav active on Campaigns
```

## 3. Campaign Detail

Desktop:

```text
Hero summary card left
Right summary cards
Reward ladder full width
Sequence overview and rules side-by-side
Bottom CTA banner
```

Mobile:

```text
Campaign icon/title stack
Stats can be 2-column cards or compact horizontal strip
Next reward and streak stack
Reward ladder horizontally scrolls
Sequence overview becomes compact step cards
Rules stack
Sticky bottom CTA
```

## 4. Sequence / Tasks

Desktop:

```text
Horizontal step tracker
Current task card
Right progress panel
Checklist below
Completed steps below
```

Mobile:

```text
Step tracker horizontally scrolls if needed
Current task card full width
Upload action becomes sticky bottom CTA
Progress card below task
Checklist rows stack
Completed steps compact
```

## 5. Proof Upload

Desktop:

```text
Task summary card
Large upload area
Selected file row
Notes field
Previous submissions
Checklist
Progress/reward summary side or below
```

Mobile:

```text
Task summary stack
Upload drop zone full width
Selected file row compact
Checklist above progress if needed
Progress/reward cards side-by-side only if enough width, otherwise stack
Submit Proof sticky bottom button
```

## 6. Rewards & Progress

Desktop:

```text
Large reward ladder
Progress cards in grid
Activity history table/list
Help card bottom
```

Mobile:

```text
Reward ladder horizontally scrolls
Progress cards stack or 2-column mini cards
Activity becomes compact rows
Help card bottom
```

## 7. Review Queue

Desktop:

```text
KPI cards
Status tabs
Queue table/list left
Proof detail panel right
Approve/reject buttons in detail panel
```

Mobile:

```text
KPI cards stack or scroll horizontally
Status tabs horizontally scroll
Queue list rows stack
Selected proof opens expanded card or bottom drawer
Approve/reject/resubmission actions stay large and touch-friendly
```

## 8. Participants & Teams

Desktop:

```text
Summary cards
Participants/Teams tabs
Search/filter row
Participant table
Team cards grid
Segmentation strip
```

Mobile:

```text
Summary cards use 2-column grid
Tabs remain top-level
Search full width
Participant table becomes stacked rows
Team cards become horizontal carousel or 2-column cards
Segmentation becomes compact card
```

## 9. Action Receipts & History

Desktop:

```text
KPI cards
Search/date/type/status filters
Receipts table
Right receipt detail panel
Event timeline inside detail panel
```

Mobile:

```text
KPI cards 2-column grid
Search full width
Filters become stacked dropdown chips
Receipt table becomes stacked rows
Selected receipt opens bottom drawer / expanded detail card
Event timeline full width
```

## 10. Settings

Desktop:

```text
Settings grid with section cards
Two-column cards
Account security full width
Save button bottom right
```

Mobile:

```text
Settings cards become accordion sections
Most sections collapsed by default
Expanded section shows fields
Save Changes sticky bottom button
```

## 11. Templates

Desktop:

```text
Template grid
Search and filters top
Selected template detail panel right
```

Mobile:

```text
Search full width
Category chips horizontal scroll
Template cards stack vertically
Use Template CTA on each card
Selected template details can be modal/drawer later
```

## 12. Campaign Builder

Desktop:

```text
Left form column
Right live preview panel
Builder stepper across top
Sticky footer actions
```

Mobile:

```text
Builder stepper horizontal scroll
Form sections stack as cards
Live preview moves below form or into collapsible section
Save Draft and Continue sticky bottom actions
```

## 13. Reward Rules Builder

Desktop:

```text
Reward level cards across top
Rule editor grid
Logic preview table/list
Save/preview actions top right
```

Mobile:

```text
Reward level cards become horizontal scroll strip
Rule fields stack vertically
Logic preview shows one card or carousel
Save Rules sticky bottom button
```

## 14. User Profile / Wallet

Desktop:

```text
Wide profile summary
Wallet summary + quick actions
Earned rewards grid
Activity feed
Claimed history table
Teams and preferences cards
```

Mobile:

```text
Profile summary stack
Wallet summary 2-column cards
Earned rewards horizontal scroll
Recent activity compact rows
Bottom nav active on Profile
```

## 15. Audit Logs

Desktop:

```text
Search/filter toolbar
Audit table
Right event detail drawer
Payload summary code block
```

Mobile:

```text
Search full width
Date/filter controls stack
Event rows as cards
Selected event opens bottom detail drawer
Payload details hidden behind View Full Details
```

## Touch target rules

Minimum sizes:

```text
Buttons: 44px height minimum
Icon buttons: 40px by 40px minimum
Form inputs: 44px height minimum
List rows: 64px height minimum
Sticky CTAs: 56px height minimum
```

## Mobile scrolling rules

Allowed horizontal scroll areas:

```text
Filter chips
Reward ladder
Builder stepper
Reward rule level tabs
Earned reward cards
Team cards
```

Avoid horizontal scroll for:

```text
Primary page content
Form fields
Tables
Submission details
Settings sections
```

## Detail panel rules

Desktop detail panels should appear on the right.

Mobile detail panels should become:

```text
Bottom drawer
Expanded card below selected row
Full-screen detail page for complex content
```

Use bottom drawers for:

```text
Review submission detail
Receipt detail
Audit event detail
```

Use full-screen detail pages for:

```text
Campaign Detail
Proof Upload
Builder steps
Settings sub-sections if needed
```

## Sticky CTA rules

Use sticky bottom CTAs on mobile when the page has one primary action.

Examples:

```text
Continue to Step 3
Open Proof Upload
Submit Proof
Save Changes
Save Rules
Continue Builder
```

Do not use sticky CTA when the page is mainly browse/search, unless a creation action is primary.

## Card spacing rules

Recommended spacing:

```text
Desktop page padding: 24px - 32px
Mobile page padding: 16px - 20px
Card padding desktop: 20px - 24px
Card padding mobile: 16px
Gap desktop: 16px - 24px
Gap mobile: 12px - 16px
Border radius: 12px - 18px
```

## Status color rules

```text
Active / Approved / Issued / Connected: green
Pending / Due soon / Warning: orange
Rejected / Failed / At Risk: red
In Progress / Current / Primary: blue
Locked / Disabled / Archived: gray
Info / Neutral: light blue or slate
```
