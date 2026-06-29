# Training Campaign Lab Route Map

## Purpose

This document maps every planned Training Campaign Lab screen to its PHP route, primary user, build phase, and data dependency.

The goal is to prevent page drift before implementation continues.

## Route principles

```text
Use training-* for participant/user-facing Training Lab pages.
Use admin-training-* for admin, owner, manager, and reviewer pages.
Keep existing Local Quest routes untouched.
Do not repurpose existing quest pages for Training Campaign Lab.
```

## Current Phase 1 routes

These files exist in the Phase 1 static shell:

```text
examples/local-quest-rewards/training-lab.php
examples/local-quest-rewards/training-campaigns.php
examples/local-quest-rewards/training-campaign-data.php
examples/local-quest-rewards/assets/training-lab.css
examples/local-quest-rewards/assets/training-lab.js
```

## Planned route inventory

| # | Page | Route | Primary user | Build phase | Status |
|---|------|-------|--------------|-------------|--------|
| 1 | Dashboard / App Landing | `training-lab.php` | Participant / Admin | Phase 1 | Static shell started |
| 2 | Campaigns | `training-campaigns.php` | Participant / Admin | Phase 1 / 3 | Static shell started |
| 3 | Campaign Detail | `training-campaign-detail.php` | Participant | Phase 4 | Planned |
| 4 | Sequence / Tasks | `training-sequence.php` | Participant | Phase 4 | Planned |
| 5 | Proof Upload | `training-proof-upload.php` | Participant | Phase 5 | Planned |
| 6 | Rewards & Progress | `training-rewards.php` | Participant | Phase 8 / 9 | Planned |
| 7 | Review Queue | `admin-training-review.php` | Reviewer / Admin | Phase 6 | Planned |
| 8 | Participants & Teams | `admin-training-participants.php` | Admin / Manager | Phase 6 / 9 | Planned |
| 9 | Action Receipts & History | `admin-training-receipts.php` | Admin / Reviewer | Phase 7 | Planned |
| 10 | Settings | `admin-training-settings.php` | Owner / Admin | Later admin phase | Planned |
| 11 | Templates | `admin-training-templates.php` | Admin / Manager | Later admin phase | Planned |
| 12 | Campaign Builder | `admin-training-builder.php` | Admin / Manager | Later admin phase | Planned |
| 13 | Reward Rules Builder | `admin-training-reward-rules.php` | Admin / Manager | Phase 8 / later admin phase | Planned |
| 14 | User Profile / Wallet | `training-profile-wallet.php` | Participant | Phase 8 | Planned |
| 15 | Audit Logs | `admin-training-audit-logs.php` | Admin / Compliance | Phase 10 / later admin phase | Planned |

## Participant route flow

### MVP participant flow

```text
training-lab.php
  -> training-campaigns.php
  -> training-campaign-detail.php?campaign=5-day-movement-challenge
  -> training-sequence.php?campaign=5-day-movement-challenge
  -> training-proof-upload.php?campaign=5-day-movement-challenge&task=movement-session
  -> training-rewards.php
  -> training-profile-wallet.php
```

### Participant route responsibilities

#### `training-lab.php`

Purpose:

```text
Training Lab landing/dashboard shell.
Shows product concept, KPI preview, campaign preview, sequence preview, proof/review/receipt preview, and build status.
```

Reads:

```text
Phase 1: training-campaign-data.php
Later: training_campaigns, training_participants, training_task_submissions, training_reward_issues, training_events
```

Actions:

```text
None in Phase 1
Later: route user to current campaign/task/reward state
```

#### `training-campaigns.php`

Purpose:

```text
Campaign browse/list page with search/filter and campaign cards.
```

Reads:

```text
Phase 1: training-campaign-data.php
Later: training_campaigns, training_sequences, training_tasks, training_participants, training_reward_rules
```

Actions:

```text
Search/filter client-side in Phase 1
Later: join/open campaign
```

#### `training-campaign-detail.php`

Purpose:

```text
Detailed campaign page showing campaign summary, reward ladder, rules, sequence overview, current progress, and next action.
```

Reads:

```text
training_campaigns
training_sequences
training_tasks
training_participants
training_task_submissions
training_reward_rules
training_streaks
```

Actions:

```text
Join campaign
Continue campaign
Open sequence
```

#### `training-sequence.php`

Purpose:

```text
Participant task sequence page showing current task, task statuses, proof requirements, checklist, completed steps, and next action.
```

Reads:

```text
training_campaigns
training_sequences
training_tasks
training_participants
training_task_submissions
training_reviews
training_streaks
```

Actions:

```text
Start task
Open proof upload
View previous proof
```

#### `training-proof-upload.php`

Purpose:

```text
Upload photo/video proof, add notes, validate file type/size, and submit for review.
```

Reads:

```text
training_campaigns
training_tasks
training_participants
training_task_submissions
training_files
```

Writes:

```text
training_files
training_task_submissions
training_events
```

Actions:

```text
Upload file
Submit proof
Remove selected file before submit
Resubmit proof after rejection/request
```

#### `training-rewards.php`

Purpose:

```text
Show reward ladder progress, issued rewards, claimed rewards, next reward, current streak, and activity history.
```

Reads:

```text
training_reward_rules
training_reward_issues
training_action_receipts
training_streaks
training_milestones
training_events
existing Local Quest wallet/reward data where useful
```

Actions:

```text
View reward
Check reward status
Open wallet
```

#### `training-profile-wallet.php`

Purpose:

```text
User profile, linked Microgifter account status, wallet summary, earned rewards, claimed history, teams, and preferences.
```

Reads:

```text
existing Local Quest user identity
linked Microgifter account fields
training_reward_issues
training_action_receipts
training_streaks
training_teams / team membership later
```

Actions:

```text
Manage Microgifter connection
View/claim rewards
Update profile preferences later
```

## Reviewer/admin route flow

### MVP reviewer flow

```text
admin-training-review.php
  -> approve/reject/request resubmission
  -> admin-training-receipts.php
  -> training-rewards.php / training-profile-wallet.php
```

### Reviewer/admin route responsibilities

#### `admin-training-review.php`

Purpose:

```text
Review pending proof submissions and take approval actions.
```

Reads:

```text
training_task_submissions
training_files
training_campaigns
training_sequences
training_tasks
training_participants
training_reviews
```

Writes:

```text
training_reviews
training_task_submissions.status
training_action_receipts when approved
training_events
```

Actions:

```text
Approve
Reject
Request resubmission
Save reviewer note
```

#### `admin-training-participants.php`

Purpose:

```text
Track participant progress, team grouping, invitations, completion rates, and at-risk status.
```

Reads:

```text
training_participants
training_campaigns
training_task_submissions
training_reviews
training_streaks
training_teams later
```

Actions:

```text
Invite participant later
Add participant later
Filter/search participants
View participant detail later
```

#### `admin-training-receipts.php`

Purpose:

```text
Search and inspect Action Receipts, reward issue status, proof/review links, and event timeline.
```

Reads:

```text
training_action_receipts
training_reward_issues
training_task_submissions
training_reviews
training_files
training_events
```

Actions:

```text
Open receipt
Copy receipt ID
View proof
View reward issue
Export receipts later
```

#### `admin-training-settings.php`

Purpose:

```text
Manage organization settings, roles, permissions, notification preferences, privacy/retention, integrations, branding, and account security.
```

Reads/writes later:

```text
training_organizations
training_settings
training_integrations
training_roles / permissions
```

Actions:

```text
Save settings
Connect integration
Upload branding assets
Update security settings
```

#### `admin-training-templates.php`

Purpose:

```text
Browse campaign templates and start a campaign from a template.
```

Reads later:

```text
training_templates
training_template_sequences
training_template_tasks
```

Actions:

```text
Use template
Preview template
Suggest template later
```

#### `admin-training-builder.php`

Purpose:

```text
Create and configure a campaign: basics, audience, sequence, tasks, proof, rewards, and publish state.
```

Reads/writes later:

```text
training_campaigns
training_sequences
training_tasks
training_reward_rules
training_participants / teams
```

Actions:

```text
Save draft
Add sequence
Add task
Configure proof requirements
Continue to reward rules
Publish campaign
```

#### `admin-training-reward-rules.php`

Purpose:

```text
Configure reward ladder levels, trigger rules, budget caps, reward values, expiration, and linked Microgift templates.
```

Reads/writes:

```text
training_reward_rules
training_campaigns
training_action_receipts for eligibility preview
Microgifter templates/programs where configured
```

Actions:

```text
Save rules
Preview eligibility
Select reward level
Link Microgift template
```

#### `admin-training-audit-logs.php`

Purpose:

```text
Search all training system events, user actions, proof changes, campaign updates, and reward events.
```

Reads:

```text
training_events
training_action_receipts
training_reward_issues
training_task_submissions
```

Actions:

```text
Search/filter events
Open event details
Export audit logs later
```

## API / action route policy

Phase 1 uses no separate API routes.

When actions are added, prefer standard POST handling on the page for MVP simplicity.

Later, action endpoints may be added only when needed:

```text
training-actions.php
admin-training-actions.php
training-upload-handler.php
```

## Query parameter conventions

Use readable IDs in URLs where practical:

```text
?campaign=5-day-movement-challenge
?sequence=daily-movement-routine
?task=movement-session
?submission=sub_xxxxx
?receipt=rcpt_xxxxx
```

Later, when database records exist, public IDs should be used instead of numeric primary keys.

## Access policy by route

| Route type | Access |
|---|---|
| `training-lab.php` | Guest/demo allowed in Phase 1; later signed-in preferred |
| `training-campaigns.php` | Guest/demo allowed in Phase 1; later signed-in preferred |
| Participant task/proof pages | Signed-in participant required |
| Reviewer/admin pages | Admin/reviewer auth required |
| Settings/builder/rules/audit pages | Owner/admin auth required |

## Build order by route

```text
1. training-lab.php
2. training-campaigns.php
3. training-campaign-detail.php
4. training-sequence.php
5. training-proof-upload.php
6. admin-training-review.php
7. admin-training-receipts.php
8. training-rewards.php
9. training-profile-wallet.php
10. admin-training-participants.php
11. admin-training-templates.php
12. admin-training-builder.php
13. admin-training-reward-rules.php
14. admin-training-settings.php
15. admin-training-audit-logs.php
```
