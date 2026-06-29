# Microgifter Training Campaign Lab

## Status

Draft product and build specification for the `local-quest-workspace` branch.

This document defines the next direction for the Local Quest example app without changing the original quest script on `main`.

## Core idea

Microgifter Training Campaigns let organizations create step-by-step action sequences, collect photo or video proof from participants, verify completion, and issue campaign rewards when users complete sequences, streaks, milestones, or consistency goals.

Short version:

```text
Create the campaign.
Assign the sequence.
Collect proof.
Verify completion.
Reward completed action.
Track consistency.
```

Platform positioning:

```text
Microgifter turns completed human action into verified, rewardable data.
```

Training Campaign positioning:

```text
Microgifter Training Campaigns reward people for completing verified action sequences and maintaining consistency over time.
```

## Why this belongs in the Local Quest example

The current Local Quest Rewards example already has the pieces needed for a proof-of-action reward loop:

- participant account flow
- Microgifter account linking
- quest/action completion
- QR and geolocation evidence foundations
- reward issue flow through the Public Distribution API
- wallet status and claim reporting
- webhook reconciliation
- admin controls
- event logs
- SQL runtime storage

Training Campaign Lab extends the quest idea from a single action into a repeatable sequence of verified actions.

Instead of only:

```text
Scan QR -> complete quest -> issue reward
```

Training Campaign Lab supports:

```text
Complete task sequence -> upload proof for each step -> verify proof -> build consistency -> issue rewards
```

## Product model

The system is organization-first, but user identity should remain user-owned.

A user can belong to many organizations, teams, campaigns, and challenges. An organization can create campaigns, invite members, review proof, fund rewards, and view analytics.

```text
Organization
  Team
    Campaign
      Challenge
        Sequence
          Task
            Proof Submission
              Review
                Action Receipt
                  Reward Issue
```

## Core objects

### Organization

The business, gym, sponsor, school, restaurant, employer, creator, trainer, or merchant that owns campaigns.

Examples:

- Fit Studio Phoenix
- Demo Coffee Merchant
- Downtown Business Association
- Employer wellness program
- Local creator community

### Team

A group of users inside an organization.

Examples:

- Morning Crew
- 14-Day Fitness Group
- Phoenix Sales Team
- New Hire Cohort
- Weekend Event Staff

### Campaign

The main reward program created by the organization.

Examples:

- 5-Day Movement Challenge
- Coffee Shop Opening Routine
- New Hire Training Week
- Safety Setup Checklist
- Creator Practice Streak

### Challenge

The user-facing goal inside a campaign.

Examples:

- Complete 5 verified routines this week
- Complete 10 workouts in 14 days
- Complete opening checklist 5 days in a row
- Complete onboarding sequence before Friday

### Sequence

A repeatable set of actions that must be completed.

Examples:

- Daily Movement Routine
- Coffee Shop Opening Sequence
- Safety Inspection Sequence
- Music Practice Routine
- Event Booth Setup Routine

### Task

One required action inside a sequence.

Examples:

- Upload warmup video
- Upload clean counter photo
- Upload squat video
- Upload stocked display photo
- Confirm checklist item
- Scan location QR

### Proof Submission

The evidence submitted for a task.

Proof types:

- photo upload
- video upload
- checklist confirmation
- QR scan
- location capture
- manager approval
- coach approval
- AI-assisted review
- external API/webhook event
- time-window confirmation

MVP proof types:

- photo upload
- video upload
- manual reviewer approval

### Review

A manual or automated decision about a proof submission.

Review outcomes:

- approved
- rejected
- needs resubmission
- pending review
- auto-approved
- AI-flagged

### Action Receipt

The verified record that a person completed a valuable action.

Action Receipts are the data layer. They prove what happened, who completed it, how it was verified, and what reward was unlocked.

### Reward Issue

The Microgifter reward request created when a reward rule is satisfied.

Reward issues should map back to:

- user
- organization
- campaign
- challenge
- sequence
- task or milestone
- reward rule
- action receipt
- Microgifter reward/item identifiers
- claim/redemption status

## User and account structure

### Account model

```text
User Account
  joins Organizations
  joins Teams
  joins Campaigns
  completes Sequences
  submits Proof
  earns Rewards
```

```text
Organization Account
  owns Campaigns
  manages Teams
  invites Members
  reviews Proof
  funds or approves Rewards
```

### Paid seats vs participants

Separate internal seats from campaign participants.

Paid seats:

- owner
- admin
- manager
- coach
- reviewer

Participants:

- employee
- gym member
- customer
- student
- fan
- volunteer
- community member
- event participant

Participants should be able to join with a link, QR code, invite code, email invite, or public challenge page without becoming paid seats.

## Roles and permissions

### Owner

- creates organization
- manages billing and settings
- manages reward funding
- manages API credentials
- creates admins
- can view all reports

### Admin

- creates campaigns
- creates teams
- manages participants
- creates sequences and tasks
- configures reward ladders
- manages campaign visibility
- views proof/reward analytics

### Manager / Coach / Reviewer

- reviews proof submissions
- approves or rejects steps
- requests resubmission
- adds reviewer notes
- views assigned team progress
- verifies sequence completion

### Participant

- joins campaigns
- views assigned tasks
- uploads proof
- tracks progress and streaks
- receives rewards
- claims rewards in wallet

### Sponsor

- funds reward pool
- views aggregate impact
- views completion and redemption reports
- does not need to see private proof files unless explicitly granted

### Reward Provider / Merchant

- provides the reward fulfillment source
- may be the same as the organization
- may be a different merchant in a local reward network

## Campaign types

### Sequence Campaign

Complete a fixed set of steps once.

Example:

```text
Complete 5 onboarding tasks and receive a reward.
```

### Consistency Campaign

Repeat a sequence over time.

Example:

```text
Complete the daily opening sequence 5 days in a row.
```

### Challenge Campaign

Complete a goal inside a time window.

Example:

```text
Complete 10 verified routines in 14 days.
```

### Team Campaign

A group completes a shared goal.

Example:

```text
Morning Crew completes 100 total verified routines this month.
```

### Sponsor Campaign

A sponsor funds rewards for verified actions.

Example:

```text
Employer funds wellness rewards for employees who complete weekly fitness routines.
```

### Public Campaign

Anyone with the link or QR can join.

### Invite-Only Campaign

Only invited users, team members, or users with a join code can join.

## Participant lifecycle

```text
invited
joined
active
submitted_proof
under_review
partially_complete
sequence_complete
verified_complete
reward_eligible
reward_issued
claimed
redeemed
expired
removed
```

Recommended flow:

1. Participant opens campaign link or scans QR.
2. Participant creates or signs into account.
3. Participant joins campaign/team.
4. Participant sees current sequence.
5. Participant completes task.
6. Participant uploads photo/video proof.
7. Proof enters review queue.
8. Reviewer approves or rejects.
9. Approved task counts toward sequence.
10. Completed sequence creates Action Receipt.
11. Reward rules evaluate.
12. Eligible rewards are issued through Microgifter.
13. Participant sees reward in wallet.
14. Claim/redemption is tracked back to the campaign.

## Status model

### Task status

```text
not_started
in_progress
submitted
under_review
approved
rejected
needs_resubmission
expired
```

### Sequence status

```text
not_started
active
partially_complete
complete_pending_review
verified_complete
failed
expired
```

### Submission status

```text
created
uploaded
pending_review
approved
rejected
needs_resubmission
archived
```

### Campaign participant status

```text
joined
active
paused
completed
reward_eligible
reward_issued
expired
removed
```

### Reward status

```text
locked
eligible
pending_issue
issued
claim_pending
claimed
redeemed
failed
expired
```

## Reward logic

Rewards should be rule-based, not hardcoded.

Reward triggers:

- task approved
- sequence completed
- challenge completed
- streak reached
- frequency reached
- milestone reached
- team goal reached
- sponsor pool threshold reached
- quality score reached
- manager final approval

Reward examples:

```text
Complete first sequence -> badge or small reward
Complete 3 sequences -> $5 Microgift
Complete 5-day streak -> $15 Microgift
Complete 20 total routines -> $30 Microgift
Team completes 100 routines -> group reward
Perfect month -> sponsor bonus
```

## Reward ladders

A Reward Ladder defines increasing reward levels.

Example:

```text
Level 1: Complete first full routine
Level 2: Complete 3 verified routines
Level 3: Complete 5-day streak
Level 4: Complete 20 total routines
Level 5: Complete perfect month
```

Each level can unlock:

- badge
- campaign entry
- small Microgift
- bonus Microgift
- sponsor reward
- team reward
- VIP reward

## Consistency engine

The consistency engine tracks repeated sequence completion.

Track:

- daily streak
- weekly completion count
- monthly completion count
- total lifetime completions
- missed days
- allowed misses
- restart rules
- bonus windows
- perfect-week status
- perfect-month status

Rule examples:

```text
5 completions in 7 days
10 completions in 14 days
Complete every weekday
Complete 3 times per week for 4 weeks
No missed sequence for 30 days
```

Consistency Track fields:

- track name
- required sequence
- required frequency
- time window
- allowed misses
- streak requirement
- milestone levels
- reward per level
- reset rule
- bonus rule

## Proof handling

### MVP proof model

MVP should use manual proof review.

Supported first proof types:

- photo upload
- video upload
- optional text note
- reviewer notes
- approve/reject decision

### Later proof enhancements

- AI-assisted proof review
- computer vision scoring
- rep counting
- motion/routine detection
- quality score
- face-blind verification
- QR + location hybrid proof
- signed proof packets
- external sensor/API proof

### Privacy and retention

Default principles:

- collect only proof required for the task
- make proof retention configurable
- do not use video for biometric identity by default
- store review outcome and action receipt separately from raw proof media
- allow organizations to delete or archive proof files after review window
- sponsors should see aggregate reporting unless granted explicit proof access

Recommended retention options:

```text
Delete proof after approval
Keep proof for 30 days
Keep proof for campaign duration
Keep proof for compliance/audit period
```

## Action Receipt model

Action Receipt is the most important data object.

It should be created when a task, sequence, milestone, or consistency goal is verified.

Suggested fields:

```text
receipt_id
public_id
user_id
organization_id
team_id
campaign_id
challenge_id
sequence_id
task_id
submission_id
proof_type
review_status
reviewer_id
completion_score
completed_at
approved_at
action_type
receipt_type
reward_rule_id
reward_issue_id
metadata_json
created_at
updated_at
```

Receipt types:

```text
task_completion
sequence_completion
streak_completion
milestone_completion
team_completion
sponsor_pool_completion
```

## Database table outline

First build tables:

```text
training_organizations
training_teams
training_team_members
training_campaigns
training_challenges
training_sequences
training_tasks
training_participants
training_task_submissions
training_reviews
training_action_receipts
training_reward_rules
training_reward_issues
training_streaks
training_milestones
training_files
training_events
```

Later tables:

```text
training_ai_reviews
training_sponsor_pools
training_team_rewards
training_notification_log
training_coach_messages
training_campaign_templates
training_proof_retention_policies
```

## Suggested table responsibilities

### training_campaigns

Stores campaign-level configuration.

Key fields:

- organization_id
- title
- slug
- description
- campaign_type
- visibility
- starts_at
- ends_at
- status
- reward_program_id
- default_template_id
- settings_json

### training_sequences

Stores repeatable action sequences.

Key fields:

- campaign_id
- title
- description
- sequence_type
- required_order
- repeat_frequency
- status
- sort_order

### training_tasks

Stores individual steps.

Key fields:

- sequence_id
- title
- instructions
- proof_type
- is_required
- sort_order
- time_window_rule
- approval_mode
- settings_json

### training_task_submissions

Stores participant proof submissions.

Key fields:

- participant_id
- task_id
- sequence_id
- campaign_id
- proof_file_id
- proof_note
- submission_status
- submitted_at
- metadata_json

### training_reviews

Stores proof review decisions.

Key fields:

- submission_id
- reviewer_id
- decision
- notes
- score
- reviewed_at

### training_reward_rules

Stores reward ladder logic.

Key fields:

- campaign_id
- trigger_type
- trigger_count
- time_window
- reward_label
- reward_template_id
- max_issues
- settings_json

### training_reward_issues

Maps triggered rules to Microgifter reward issue responses.

Key fields:

- user_id
- campaign_id
- reward_rule_id
- action_receipt_id
- external_reward_id
- microgifter_item_id
- issue_status
- claim_status
- response_json

## Screens and pages

### Admin / merchant side

```text
Dashboard
Organizations / Teams
Campaign Builder
Sequence Builder
Task Builder
Proof Review Queue
Participants
Reward Ladder
Streaks / Milestones
Action Receipts
Wallet / Reward Issues
Analytics
Settings
```

### Participant side

```text
My Campaigns
Today’s Sequence
Task Detail
Upload Proof
Submission Status
Progress / Streaks
Rewards
Wallet
Team Leaderboard
```

### Reviewer side

```text
Pending Proof
Submission Detail
Approve / Reject
Request Resubmission
Reviewer Notes
Completion History
```

## MVP pages for this branch

Add these files first:

```text
examples/local-quest-rewards/training-lab.php
examples/local-quest-rewards/training-campaigns.php
examples/local-quest-rewards/training-sequence.php
examples/local-quest-rewards/training-upload.php
examples/local-quest-rewards/admin-training-review.php
examples/local-quest-rewards/training-rewards.php
examples/local-quest-rewards/training-consistency.php
examples/local-quest-rewards/assets/training-lab.css
examples/local-quest-rewards/assets/training-lab.js
examples/local-quest-rewards/database/training_campaign_lab.sql
```

Keep the first version integrated with the existing Local Quest app shell and SQL runtime where practical.

## First demo campaign: Fitness

Campaign:

```text
5-Day Movement Challenge
```

Sequence:

```text
Daily Movement Routine
```

Tasks:

1. Upload warmup photo/video.
2. Upload squat video.
3. Upload plank video.
4. Upload cooldown proof.

Reward ladder:

```text
Complete first full routine -> entry badge
Complete 3 verified routines -> $5 Microgift
Complete 5-day streak -> $15 Microgift
Complete 4 perfect weeks -> sponsor bonus
```

Use case:

- gym member challenge
- personal trainer program
- employer wellness campaign
- sponsor-funded local wellness rewards

## Second demo campaign: Merchant staff training

Campaign:

```text
Coffee Shop Opening Routine
```

Sequence:

```text
Daily Opening Checklist
```

Tasks:

1. Upload clean counter photo.
2. Upload stocked pastry case photo.
3. Upload espresso machine startup video.
4. Upload QR/table tent placement photo.
5. Manager approves final setup.

Reward ladder:

```text
Complete one opening sequence -> $2 reward
Complete 5 days in a row -> $10 bonus
Perfect month -> $50 team reward
```

Use case:

- restaurant opening routines
- retail store readiness
- employee training
- team consistency tracking

## Third demo campaign: Creator practice streak

Campaign:

```text
14-Day Creator Practice Streak
```

Sequence:

```text
Daily Practice Routine
```

Tasks:

1. Upload 30-second practice clip.
2. Submit practice note.
3. Confirm daily completion.

Reward ladder:

```text
Complete 5 days -> fan badge
Complete 10 days -> Microgift reward
Complete 14-day streak -> sponsor reward or merch unlock
```

Use case:

- musicians
- creators
- students
- online coaching
- fan-funded challenges

## Agentic roadmap

The agent should eventually support three roles.

### Campaign Builder Agent

Helps an organization create campaigns.

Example prompts:

```text
Create a 5-day movement challenge for gym members.
Create a daily opening routine for my coffee shop.
Create a training checklist for event staff.
```

Agent outputs:

- campaign title
- sequence steps
- proof type per step
- reward ladder
- reminders
- review rules
- launch checklist

### Participant Coach Agent

Guides users through the campaign.

Example messages:

```text
You are 4 of 5 days complete. Upload today’s proof before 8 PM to unlock your weekly reward.
Your video was submitted and is waiting for review.
Step 2 was rejected because the required setup was not visible. Re-upload when ready.
Sequence complete. Your campaign reward has been sent.
```

### Reviewer Assistant Agent

Helps admins review proof faster.

MVP remains manual review, but later the agent can:

- summarize submissions
- flag missing evidence
- suggest approve/reject
- detect incomplete proof
- group suspicious duplicates
- prepare action receipt summaries

## Build phases

### Phase 1: Documentation and product shell

- Create this spec.
- Add Training Lab landing page.
- Add static campaign cards.
- Add sample campaign definitions.
- Reuse Local Quest shell where possible.

### Phase 2: Sequence and task flow

- Add campaign list.
- Add participant join flow.
- Add sequence detail page.
- Add task detail page.
- Add upload proof form.

### Phase 3: Proof review

- Add admin proof review queue.
- Add submission detail view.
- Add approve/reject actions.
- Add reviewer notes.
- Add needs-resubmission status.

### Phase 4: Completion and Action Receipts

- Mark task approved.
- Mark sequence complete when all required tasks are approved.
- Create Action Receipt for sequence completion.
- Add Action Receipt log.

### Phase 5: Reward release

- Add reward rule for sequence completion.
- Issue reward through existing Microgifter distribution flow.
- Store reward issue response.
- Display reward in wallet.

### Phase 6: Consistency and reward ladders

- Add streak tracking.
- Add milestone tracking.
- Add reward ladder rules.
- Add weekly/monthly consistency summary.

### Phase 7: Agentic and AI-assisted review

- Add campaign generation helper.
- Add participant coach messages.
- Add optional AI proof review fields.
- Add verification score and confidence metadata.

## MVP acceptance checklist

The first usable Training Campaign Lab should prove:

- Admin can view a sample training campaign.
- Participant can join the campaign.
- Participant can view a sequence of required tasks.
- Participant can upload photo/video proof for each task.
- Admin can review proof submissions.
- Admin can approve or reject each task.
- Rejected proof can be resubmitted.
- Sequence is marked complete when all required tasks are approved.
- Sequence completion creates an Action Receipt.
- Reward eligibility is triggered by completion.
- Reward issue is stored and visible.
- Participant sees reward status in wallet.
- Streak or completion count is tracked for repeat sequences.

## Non-goals for MVP

Do not start with these:

- full AI video analysis
- biometric identity detection
- real-time motion tracking
- complex sponsor billing
- multi-tenant enterprise permission overhaul
- mobile app build
- full notification engine

Start with:

```text
Manual proof upload + manual review + action receipt + reward release.
```

## Open decisions

These should be decided before implementation:

1. Should Training Campaign Lab reuse the existing Local Quest user tables or create isolated training tables?
2. Should uploaded proof files be stored locally for the demo or through a storage abstraction?
3. Should participants need Microgifter linking before submitting proof or only before reward issue?
4. Should rewards issue automatically after all approvals or require final admin release in MVP?
5. Should proof retention default to delete-after-approval or campaign-duration storage?
6. Should teams be required for MVP, or should campaign participation be enough?
7. Should reward rules be JSON-configured first, then normalized later?

## Recommended MVP decisions

For fastest build:

1. Keep Training Campaign Lab inside `examples/local-quest-rewards/`.
2. Use SQL tables specific to training lab.
3. Store uploaded proof in an `uploads/training-proof/` demo directory with metadata in SQL.
4. Require participant sign-in before joining a campaign.
5. Allow proof submission before Microgifter linking.
6. Require Microgifter linking before reward issue.
7. Use manual review first.
8. Auto-issue reward after all required steps are approved.
9. Track the reward issue in the existing wallet model where practical.
10. Add consistency tracking after single-sequence completion works.

## Build principle

Do not replace the Local Quest script. Extend the example app with a new lab module.

The branch should preserve the original app while proving a new pattern:

```text
Quest = one verified action.
Training Campaign = verified sequence of actions.
Consistency Campaign = repeated verified sequences over time.
```

## Final product statement

Microgifter Training Campaigns let organizations create action-based challenges, assign them to teams or public participants, collect proof of completion, verify progress, and issue rewards for completed sequences, streaks, and milestones.
