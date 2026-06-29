# Training Campaign Lab Implementation Tickets

## Purpose

This document breaks the staged build outline into small implementation tickets.

The project should not continue as one large build. Each ticket should be independently understandable, testable, and reversible.

## Build control rule

Before starting a ticket, confirm:

```text
Branch is local-quest-workspace
Main is untouched
Ticket does not modify protected Local Quest files unless explicitly approved
Expected files are listed
Acceptance criteria are clear
```

## Ticket status values

```text
Not Started
In Progress
Blocked
Ready for Review
Done
```

## Phase 0: Documentation and branch control

### Ticket 0.1 — Lock branch strategy

Status: Done

Files:

```text
docs/training-campaign-lab/branch-strategy.md
```

Acceptance criteria:

```text
Branch purpose is documented
Protected existing Local Quest files are listed
Training Lab file naming rules are documented
Merge policy is documented
```

### Ticket 0.2 — Lock route map

Status: Done

Files:

```text
docs/training-campaign-lab/route-map.md
```

Acceptance criteria:

```text
All 15 planned pages have route names
Participant/admin flows are documented
Route access rules are documented
Build order by route is documented
```

### Ticket 0.3 — Lock UI data map

Status: Done

Files:

```text
docs/training-campaign-lab/ui-data-map.md
```

Acceptance criteria:

```text
Each page has read/write data requirements
Phase 1 seed data and later SQL data are separated
Cross-page data rules are documented
```

## Phase 1: Static product shell

### Ticket 1.1 — Create Training Lab seed data helper

Status: Done

Files:

```text
examples/local-quest-rewards/training-campaign-data.php
```

Acceptance criteria:

```text
At least three demo campaigns exist
Each campaign has sequence data
Each campaign has task data
Each campaign has reward ladder data
No database change required
```

### Ticket 1.2 — Create shared Training Lab CSS

Status: Done

Files:

```text
examples/local-quest-rewards/assets/training-lab.css
```

Acceptance criteria:

```text
Defines Training Lab colors, cards, buttons, nav, status pills, grid, mobile behavior
Does not modify portal.css
Does not affect existing Local Quest pages
```

### Ticket 1.3 — Create shared Training Lab JS

Status: Done

Files:

```text
examples/local-quest-rewards/assets/training-lab.js
```

Acceptance criteria:

```text
Initializes progress bars
Supports campaign search
Supports campaign filter chips
Does not require external dependencies
```

### Ticket 1.4 — Create Training Lab dashboard shell

Status: Done

Files:

```text
examples/local-quest-rewards/training-lab.php
```

Acceptance criteria:

```text
Page loads without database changes
Shows Training Lab product concept
Shows KPI preview
Shows campaign preview
Shows sequence/reward/proof/review/receipt preview sections
Links to campaigns page
Does not break existing Local Quest app
```

### Ticket 1.5 — Create Campaigns page shell

Status: Done

Files:

```text
examples/local-quest-rewards/training-campaigns.php
```

Acceptance criteria:

```text
Page loads without database changes
Renders seeded campaigns
Search input filters campaigns
Filter chips filter campaigns
Mobile layout works using shared CSS
Does not change existing Local Quest files
```

## Phase 2: SQL schema

### Ticket 2.1 — Create Training Lab SQL schema file

Status: Not Started

Files:

```text
examples/local-quest-rewards/database/training_campaign_lab.sql
```

Acceptance criteria:

```text
SQL imports cleanly on MySQL/MariaDB
Tables use BIGINT UNSIGNED primary keys
public_id exists for user-facing records
created_at and updated_at exist where practical
Foreign keys import in correct order
No collision with existing Local Quest tables
```

### Ticket 2.2 — Add schema install notes

Status: Not Started

Files:

```text
docs/training-campaign-lab/schema-install.md
```

Acceptance criteria:

```text
Documents import command
Documents table order
Documents rollback/drop order
Documents required config assumptions
```

### Ticket 2.3 — Add schema validation script section

Status: Not Started

Files:

```text
scripts/validate_training_campaign_lab.php
```

Acceptance criteria:

```text
Validation script checks SQL file exists
Validation script checks expected table names in SQL
Validation script reports missing required tables clearly
```

## Phase 3: Demo campaign seed persistence

### Ticket 3.1 — Convert PHP seed campaigns to database seed format

Status: Not Started

Files:

```text
examples/local-quest-rewards/database/training_campaign_lab_seed.sql
```

Acceptance criteria:

```text
Includes 5-Day Movement Challenge
Includes Coffee Shop Opening Routine
Includes 14-Day Creator Practice Streak
Includes sequences and tasks
Includes reward rules
Can be imported after schema
```

### Ticket 3.2 — Add seed loader helper

Status: Not Started

Files:

```text
examples/local-quest-rewards/training-storage.php
```

Acceptance criteria:

```text
Reads training campaigns from SQL when available
Falls back safely to PHP seed data if schema is not installed
Does not modify existing storage-sql.php unless approved
```

## Phase 4: Participant campaign join and sequence view

### Ticket 4.1 — Create Campaign Detail page

Status: Not Started

Files:

```text
examples/local-quest-rewards/training-campaign-detail.php
```

Acceptance criteria:

```text
Loads selected campaign by slug/public_id
Shows campaign hero
Shows reward ladder
Shows sequence overview
Shows rules and requirements
Shows joined/not joined state
```

### Ticket 4.2 — Implement participant join action

Status: Not Started

Files:

```text
examples/local-quest-rewards/training-campaign-detail.php
examples/local-quest-rewards/training-storage.php
```

Acceptance criteria:

```text
Signed-in user can join campaign
Creates training_participants record
Duplicate join is prevented
training.campaign.joined event is logged
```

### Ticket 4.3 — Create Sequence / Tasks page

Status: Not Started

Files:

```text
examples/local-quest-rewards/training-sequence.php
```

Acceptance criteria:

```text
Shows ordered task list
Shows current task
Shows task status
Shows proof requirements
Shows next reward preview
Routes current task to proof upload page
```

## Phase 5: Proof upload

### Ticket 5.1 — Add proof upload route

Status: Not Started

Files:

```text
examples/local-quest-rewards/training-proof-upload.php
examples/local-quest-rewards/uploads/training-proof/.gitkeep
```

Acceptance criteria:

```text
Signed-in participant can select proof file
Accepted file types are enforced
Max file size is enforced
Safe file names are generated
Upload folder exists
```

### Ticket 5.2 — Persist proof file metadata

Status: Not Started

Files:

```text
examples/local-quest-rewards/training-proof-upload.php
examples/local-quest-rewards/training-storage.php
```

Acceptance criteria:

```text
training_files record is created
training_task_submissions record is created
Submission status is pending_review
training.proof.uploaded event is logged
```

### Ticket 5.3 — Add proof resubmission support

Status: Not Started

Files:

```text
examples/local-quest-rewards/training-proof-upload.php
```

Acceptance criteria:

```text
Rejected or needs_resubmission task can submit another attempt
Attempt number increments
Prior attempts remain visible
Only latest pending attempt is active for review
```

## Phase 6: Admin proof review

### Ticket 6.1 — Create Review Queue page

Status: Not Started

Files:

```text
examples/local-quest-rewards/admin-training-review.php
```

Acceptance criteria:

```text
Admin/reviewer can see pending submissions
Shows participant, campaign, task, proof file, submitted timestamp, and notes
Shows proof preview where practical
```

### Ticket 6.2 — Add approval action

Status: Not Started

Files:

```text
examples/local-quest-rewards/admin-training-review.php
examples/local-quest-rewards/training-storage.php
```

Acceptance criteria:

```text
Approve action creates training_reviews record
Submission status becomes approved
training.review.approved event is logged
Approved task counts toward sequence completion
```

### Ticket 6.3 — Add reject/resubmission actions

Status: Not Started

Files:

```text
examples/local-quest-rewards/admin-training-review.php
examples/local-quest-rewards/training-storage.php
```

Acceptance criteria:

```text
Reject action records reviewer note
Request resubmission action records reviewer note
Submission status updates correctly
Participant can see resubmission requirement
Events are logged
```

## Phase 7: Action Receipts

### Ticket 7.1 — Create Action Receipt creation helper

Status: Not Started

Files:

```text
examples/local-quest-rewards/training-receipt-service.php
```

Acceptance criteria:

```text
Task completion receipt is created after task approval
Sequence completion receipt is created after all required tasks are approved
Duplicate receipts are prevented
Receipt links participant, campaign, sequence, task/submission/review where practical
```

### Ticket 7.2 — Create Action Receipts page

Status: Not Started

Files:

```text
examples/local-quest-rewards/admin-training-receipts.php
```

Acceptance criteria:

```text
Shows receipt list
Shows receipt detail
Shows event timeline
Shows reward issue status if available
Can search/filter later
```

## Phase 8: Reward release

### Ticket 8.1 — Add reward rule evaluator

Status: Not Started

Files:

```text
examples/local-quest-rewards/training-reward-service.php
```

Acceptance criteria:

```text
Evaluates reward rules after Action Receipt creation
Checks duplicate reward issue prevention
Checks Microgifter account link requirement
Checks budget/cap fields if present
Returns eligible/ineligible result clearly
```

### Ticket 8.2 — Create reward issue record

Status: Not Started

Files:

```text
examples/local-quest-rewards/training-reward-service.php
```

Acceptance criteria:

```text
Creates training_reward_issues record
Stores pending_issue / issued / failed status
Stores Microgifter response payload where available
Logs reward events
```

### Ticket 8.3 — Create Rewards & Progress page

Status: Not Started

Files:

```text
examples/local-quest-rewards/training-rewards.php
```

Acceptance criteria:

```text
Shows reward ladder
Shows issued rewards
Shows claimed/redeemed history if available
Shows next reward
Shows failure state clearly
```

### Ticket 8.4 — Create Profile / Wallet page

Status: Not Started

Files:

```text
examples/local-quest-rewards/training-profile-wallet.php
```

Acceptance criteria:

```text
Shows linked Microgifter account status
Shows reward/wallet summary
Shows earned rewards
Shows recent Action Receipt activity
Links back to existing Local Quest wallet where useful
```

## Phase 9: Admin builder and management pages

### Ticket 9.1 — Create Participants & Teams page

Status: Not Started

Files:

```text
examples/local-quest-rewards/admin-training-participants.php
```

Acceptance criteria:

```text
Shows participant list
Shows progress percent
Shows streak count
Shows participant status
Shows team grouping preview
```

### Ticket 9.2 — Create Templates page

Status: Not Started

Files:

```text
examples/local-quest-rewards/admin-training-templates.php
```

Acceptance criteria:

```text
Shows campaign templates
Search/filter works
Use Template action starts a draft later
```

### Ticket 9.3 — Create Campaign Builder page

Status: Not Started

Files:

```text
examples/local-quest-rewards/admin-training-builder.php
```

Acceptance criteria:

```text
Shows builder stepper
Shows campaign basics form
Shows sequence/task form structure
Shows live preview
Save Draft is stubbed or functional depending build stage
```

### Ticket 9.4 — Create Reward Rules Builder page

Status: Not Started

Files:

```text
examples/local-quest-rewards/admin-training-reward-rules.php
```

Acceptance criteria:

```text
Shows reward ladder levels
Shows rule configuration fields
Shows logic preview
Can save rules after schema is ready
```

### Ticket 9.5 — Create Settings page

Status: Not Started

Files:

```text
examples/local-quest-rewards/admin-training-settings.php
```

Acceptance criteria:

```text
Shows organization settings
Shows permissions preview
Shows notifications/privacy/integrations sections
Save behavior can be stubbed until settings table exists
```

### Ticket 9.6 — Create Audit Logs page

Status: Not Started

Files:

```text
examples/local-quest-rewards/admin-training-audit-logs.php
```

Acceptance criteria:

```text
Shows training_events list
Search/filter shell exists
Event detail panel exists
```

## Phase 10: QA validation

### Ticket 10.1 — Create validation script

Status: Not Started

Files:

```text
scripts/validate_training_campaign_lab.php
```

Acceptance criteria:

```text
Checks required docs exist
Checks required routes exist
Checks CSS/JS files exist
Checks SQL schema exists when Phase 2 starts
Checks expected table names in schema
Reports missing items clearly
Exits non-zero when required files are missing
```

### Ticket 10.2 — Add manual QA test script

Status: Done

Files:

```text
docs/training-campaign-lab/qa-test-script.md
```

Acceptance criteria:

```text
Documents manual test scenarios
Documents expected MVP vertical slice
Documents mobile checks
Documents failure state tests
```

## Final MVP done definition

The MVP is done when this path works end-to-end:

```text
Create/sign in participant
Open Training Lab
Join 5-Day Movement Challenge
Open sequence
Upload proof for required tasks
Admin approves submissions
All required tasks become approved
Sequence completion Action Receipt is created
Reward rule evaluates as eligible
Reward issue is created
Reward status displays in rewards/wallet view
Validation script passes
```

## Do not start these until the vertical slice works

```text
AI proof review
Computer vision
Wearable integrations
Sponsor billing
Template marketplace
Advanced analytics
Leaderboard automation
Notification engine
Public API endpoints
White-label embedding
```
