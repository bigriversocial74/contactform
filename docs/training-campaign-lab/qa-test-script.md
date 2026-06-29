# Training Campaign Lab QA Test Script

## Purpose

This document defines manual QA checks for the Training Campaign Lab before and during implementation.

The goal is to keep the build staged, testable, and safe while preserving the original Loyalty Quest / Local Quest flow.

## Testing principle

Each build phase must pass its own test before the next phase starts.

Do not continue building new features when the current phase has a broken route, broken layout, broken status transition, or unclear data state.

## Environment

Recommended local command:

```bash
php -S 127.0.0.1:8090 -t examples/local-quest-rewards
```

Base URLs:

```text
http://127.0.0.1:8090/training-lab.php
http://127.0.0.1:8090/training-campaigns.php
```

Original Local Quest should still be available at:

```text
http://127.0.0.1:8090/index.php
http://127.0.0.1:8090/wallet.php
http://127.0.0.1:8090/admin.php
```

## Global branch safety tests

### Test: branch remains separate

Expected:

```text
Current work is on local-quest-workspace
main is not changed
Training Lab files are added separately
Existing Local Quest files still exist
```

### Test: protected files untouched unless explicitly approved

Protected existing files:

```text
examples/local-quest-rewards/index.php
examples/local-quest-rewards/wallet.php
examples/local-quest-rewards/quests.php
examples/local-quest-rewards/admin.php
examples/local-quest-rewards/admin-portal.php
examples/local-quest-rewards/admin-quest-controls.php
examples/local-quest-rewards/quest-controls.php
examples/local-quest-rewards/storage-sql.php
examples/local-quest-rewards/webhook.php
```

Expected:

```text
Training Lab can reuse helpers from these files, but should not rewrite them during the Training Lab build unless separately approved.
```

---

# Phase 1 QA: Static product shell

## Routes to test

```text
/training-lab.php
/training-campaigns.php
```

## Test 1.1 — Training Lab dashboard loads

Steps:

```text
Open /training-lab.php
```

Expected:

```text
Page loads without fatal error
Training Campaign Lab branding appears
Hero section appears
KPI cards appear
Featured campaign cards appear
Sequence preview appears
Reward ladder preview appears
Proof/review/receipt preview sections appear
Mobile bottom nav exists at mobile width
```

Fail if:

```text
PHP fatal error
Blank page
CSS missing
Original Local Quest redirect breaks page unexpectedly
Page edits existing Local Quest behavior
```

## Test 1.2 — Campaigns page loads

Steps:

```text
Open /training-campaigns.php
```

Expected:

```text
Page loads without fatal error
Campaigns page title appears
Three seed campaign cards appear
Search input appears
Filter chips appear
How campaigns work section appears
```

Seed campaigns expected:

```text
5-Day Movement Challenge
Coffee Shop Opening Routine
14-Day Creator Practice Streak
```

## Test 1.3 — Search/filter behavior

Steps:

```text
Open /training-campaigns.php
Search for Movement
Click Fitness filter
Click Merchant filter
Click Creator filter
Click All filter
```

Expected:

```text
Search filters visible campaign cards
Filter chips show relevant campaigns
All restores all campaigns
No page reload required
```

## Test 1.4 — Mobile layout

Steps:

```text
Resize viewport to mobile width
Open /training-lab.php
Open /training-campaigns.php
```

Expected:

```text
No horizontal page overflow
Cards stack cleanly
Mobile bottom nav appears
Buttons are touch-friendly
Campaign cards remain readable
Reward ladder scrolls or stacks cleanly
```

## Test 1.5 — Original Local Quest still works

Steps:

```text
Open /index.php
Open /wallet.php
Open /cover.php
```

Expected:

```text
Original Local Quest screens load as they did before
No Training Lab CSS leaks into Local Quest pages
No Local Quest route is replaced
```

---

# Phase 2 QA: SQL schema

## Test 2.1 — SQL import

Steps:

```text
Import examples/local-quest-rewards/database/training_campaign_lab.sql into MySQL/MariaDB
```

Expected:

```text
Import completes without foreign key errors
All expected training_* tables are created
No existing Local Quest tables are dropped or altered unexpectedly
```

Required tables:

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
training_events
```

## Test 2.2 — Schema naming

Expected:

```text
Tables use training_ prefix
Primary keys are BIGINT UNSIGNED
User-facing records have public_id where practical
Timestamps exist where practical
Foreign keys import in correct order
```

---

# Phase 3 QA: Seed data persistence

## Test 3.1 — Seed campaigns load from database

Steps:

```text
Import seed data
Open /training-campaigns.php
```

Expected:

```text
Seed campaigns render from SQL when schema exists
Fallback PHP seed data remains available if schema is not installed
Campaign cards still display title, description, tags, tasks, participants, and reward preview
```

## Test 3.2 — Seed data matches docs

Expected campaigns:

```text
5-Day Movement Challenge
Coffee Shop Opening Routine
14-Day Creator Practice Streak
```

Each campaign should have:

```text
At least one sequence
At least three tasks
At least one reward rule
Proof requirements
Status
```

---

# Phase 4 QA: Participant join and sequence view

## Test 4.1 — Signed-in participant can join campaign

Steps:

```text
Create or sign in as Local Quest user
Open /training-campaign-detail.php?campaign=5-day-movement-challenge
Click Join Campaign
```

Expected:

```text
training_participants record created
Duplicate join is prevented
Campaign Detail shows joined/active state
Event training.campaign.joined is logged
```

## Test 4.2 — Campaign Detail displays correct data

Expected:

```text
Campaign hero appears
Reward ladder appears
Sequence overview appears
Rules/requirements appear
Next action routes to Sequence / Tasks
```

## Test 4.3 — Sequence / Tasks page displays current task

Steps:

```text
Open /training-sequence.php?campaign=5-day-movement-challenge
```

Expected:

```text
Ordered tasks appear
Current task is clear
Proof requirement is clear
Completed/pending/locked statuses display correctly
Open Proof Upload CTA routes to proof upload page
```

---

# Phase 5 QA: Proof upload

## Test 5.1 — Valid file upload

Steps:

```text
Open proof upload route for current task
Choose valid JPG/PNG/WEBP/MP4/MOV/WEBM file
Add optional note
Submit proof
```

Expected:

```text
File uploads successfully
Safe stored filename is generated
training_files record is created
training_task_submissions record is created
Submission status is pending_review
User sees pending review status
Event training.proof.uploaded is logged
```

## Test 5.2 — Invalid file type

Steps:

```text
Attempt to upload unsupported file type
```

Expected:

```text
Upload is rejected
Clear error message appears
No training_files record is created
No submission record is created
```

## Test 5.3 — File too large

Steps:

```text
Attempt to upload a file above max allowed size
```

Expected:

```text
Upload is rejected
Clear file-size error appears
No partial submission is created
```

## Test 5.4 — Resubmission

Precondition:

```text
Prior submission is rejected or needs_resubmission
```

Steps:

```text
Open proof upload page
Upload new proof
Submit
```

Expected:

```text
New attempt is created
Attempt number increments
Prior attempt remains in history
Latest attempt becomes pending_review
```

---

# Phase 6 QA: Admin proof review

## Test 6.1 — Review queue shows pending submissions

Steps:

```text
Open /admin-training-review.php
```

Expected:

```text
Pending submissions appear
Each row shows participant, campaign, task, proof, timestamp, and status
Selected submission shows proof detail panel
```

## Test 6.2 — Approve proof

Steps:

```text
Select pending submission
Add reviewer note
Click Approve
```

Expected:

```text
training_reviews record is created
submission status becomes approved
training.review.approved event is logged
task is counted as approved
if all required tasks approved, sequence completion is checked
```

## Test 6.3 — Reject proof

Steps:

```text
Select pending submission
Add reviewer note
Click Reject
```

Expected:

```text
training_reviews record is created
submission status becomes rejected
training.review.rejected event is logged
participant can see rejected status
```

## Test 6.4 — Request resubmission

Steps:

```text
Select pending submission
Add reviewer note
Click Request Resubmission
```

Expected:

```text
training_reviews record is created
submission status becomes needs_resubmission
participant can upload another attempt
training.review.resubmission_requested event is logged
```

---

# Phase 7 QA: Action Receipts

## Test 7.1 — Task completion receipt

Precondition:

```text
Task proof is approved
```

Expected:

```text
task_completion Action Receipt is created
Receipt links participant, campaign, sequence, task, submission, proof, and review
Duplicate task completion receipt is not created if reviewer action is retried
```

## Test 7.2 — Sequence completion receipt

Precondition:

```text
All required tasks in a sequence are approved
```

Expected:

```text
sequence_completion Action Receipt is created
Sequence status becomes verified_complete
Event training.sequence.verified_complete is logged
```

## Test 7.3 — Receipt page

Steps:

```text
Open /admin-training-receipts.php
Open a receipt detail
```

Expected:

```text
Receipt list appears
Receipt detail appears
Event timeline appears
Linked proof/review/reward status appears when available
```

---

# Phase 8 QA: Reward release

## Test 8.1 — Reward eligibility

Precondition:

```text
Sequence completion receipt exists
Reward rule exists for sequence completion
```

Expected:

```text
Reward rule evaluator returns eligible
training.reward.eligible event is logged
```

## Test 8.2 — Microgifter account not linked

Precondition:

```text
Participant has no linked Microgifter account
```

Expected:

```text
Reward issue is blocked or set to needs_linked_account
User sees clear link-account message
No duplicate issue is created
```

## Test 8.3 — Reward issued

Precondition:

```text
Participant has linked Microgifter account
Reward rule eligibility passes
Microgifter config is valid
```

Expected:

```text
training_reward_issues record is created
Issue status becomes pending_issue, issued, or failed
Microgifter response is stored
training.reward.issued or training.reward.failed event is logged
Rewards page shows status
Profile/Wallet page shows reward status
```

---

# Phase 9 QA: Consistency and management pages

## Test 9.1 — Streak updates

Precondition:

```text
Participant completes multiple verified sequences
```

Expected:

```text
training_streaks updates
Reward ladder can unlock later rewards
Rewards page shows current streak
```

## Test 9.2 — Participants page

Steps:

```text
Open /admin-training-participants.php
```

Expected:

```text
Participant list appears
Progress values are accurate
At-risk status is clear
Team cards show completion rates when team data exists
```

## Test 9.3 — Builder pages load

Routes:

```text
/admin-training-templates.php
/admin-training-builder.php
/admin-training-reward-rules.php
/admin-training-settings.php
/admin-training-audit-logs.php
```

Expected:

```text
Each page loads
Each page uses Training Lab design system
Each page has clear empty/stub state if data is not wired yet
```

---

# Phase 10 QA: Validation script

## Test 10.1 — Validation script runs

Steps:

```bash
php scripts/validate_training_campaign_lab.php
```

Expected:

```text
Reports required files
Reports required docs
Reports SQL/table checks when schema is present
No fatal errors
Non-zero exit when required files are missing
```

---

# Final vertical slice QA

The first complete MVP is not done until this full path passes:

```text
1. Create/sign in participant
2. Open Training Lab dashboard
3. Open Campaigns
4. Open 5-Day Movement Challenge
5. Join campaign
6. Open sequence
7. Upload proof for required task
8. Admin opens Review Queue
9. Admin approves proof
10. Task completion Action Receipt is created
11. Repeat until all required tasks approved
12. Sequence completion Action Receipt is created
13. Reward rule evaluates as eligible
14. Reward issue is created
15. Reward status appears on Rewards & Progress
16. Reward status appears on User Profile / Wallet
17. Validation script passes
18. Original Local Quest app still works
```

## Final pass/fail rule

Fail the build if any of these happen:

```text
Existing Local Quest flow breaks
Main branch is modified unexpectedly
User can receive reward from unapproved proof
Reward issues duplicate for same receipt/rule
Approved task does not create receipt
Sequence completion occurs before all required tasks are approved
Unsupported files can be uploaded
Rejected proof counts toward progress
Mobile layout has unusable horizontal overflow
Validation script cannot run
```
