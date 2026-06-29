# Training Campaign Lab Schema Install Plan

## Purpose

This document defines the SQL install plan for the Training Campaign Lab before the schema is implemented.

The goal is to make the Phase 2 database work predictable and prevent foreign-key import failures, table-name collisions, or accidental changes to existing Loyalty Quest tables.

## Schema file target

```text
examples/local-quest-rewards/database/training_campaign_lab.sql
```

Optional seed file:

```text
examples/local-quest-rewards/database/training_campaign_lab_seed.sql
```

## Install principle

The Training Campaign Lab schema must be additive.

Do not alter or drop existing Local Quest tables unless a separate migration plan is written and approved.

## Table naming rule

All Training Campaign Lab tables must use the `training_` prefix.

Required MVP tables:

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

Optional later tables:

```text
training_organizations
training_locations
training_teams
training_team_members
training_templates
training_template_sequences
training_template_tasks
training_integrations
training_settings
training_notifications
```

## Primary key standard

Use:

```sql
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
```

Use `public_id` for user-facing references:

```sql
public_id CHAR(36) NOT NULL
```

Recommended standard columns:

```sql
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

## Recommended table creation order

Create independent tables first, then dependent tables.

```text
1. training_campaigns
2. training_sequences
3. training_tasks
4. training_participants
5. training_files
6. training_task_submissions
7. training_reviews
8. training_action_receipts
9. training_reward_rules
10. training_reward_issues
11. training_streaks
12. training_events
```

## Recommended foreign-key relationship order

```text
training_sequences.campaign_id -> training_campaigns.id
training_tasks.sequence_id -> training_sequences.id
training_participants.campaign_id -> training_campaigns.id
training_task_submissions.participant_id -> training_participants.id
training_task_submissions.campaign_id -> training_campaigns.id
training_task_submissions.sequence_id -> training_sequences.id
training_task_submissions.task_id -> training_tasks.id
training_task_submissions.file_id -> training_files.id
training_reviews.submission_id -> training_task_submissions.id
training_action_receipts.participant_id -> training_participants.id
training_action_receipts.campaign_id -> training_campaigns.id
training_action_receipts.sequence_id -> training_sequences.id nullable
training_action_receipts.task_id -> training_tasks.id nullable
training_action_receipts.submission_id -> training_task_submissions.id nullable
training_action_receipts.review_id -> training_reviews.id nullable
training_reward_rules.campaign_id -> training_campaigns.id
training_reward_issues.participant_id -> training_participants.id
training_reward_issues.campaign_id -> training_campaigns.id
training_reward_issues.receipt_id -> training_action_receipts.id
training_reward_issues.reward_rule_id -> training_reward_rules.id
training_streaks.participant_id -> training_participants.id
training_streaks.campaign_id -> training_campaigns.id
```

## Index requirements

Required indexes:

```text
public_id unique index on user-facing tables
campaign_id indexes on campaign child tables
participant_id indexes on participant activity tables
status indexes on task submissions, reviews, reward issues, and campaigns
created_at indexes for event/history pages
receipt_id indexes for reward issues
```

Recommended unique keys:

```text
training_campaigns.public_id
training_sequences.public_id
training_tasks.public_id
training_participants.public_id
training_files.public_id
training_task_submissions.public_id
training_reviews.public_id
training_action_receipts.public_id
training_reward_rules.public_id
training_reward_issues.public_id
training_events.public_id
```

Duplicate prevention keys to consider:

```text
one active participant per user/campaign
one task completion receipt per participant/task/approved submission
one sequence completion receipt per participant/sequence completion
one reward issue per participant/receipt/reward rule
```

## Seed install order

Seed file should be imported after schema.

Recommended seed order:

```text
1. training_campaigns
2. training_sequences
3. training_tasks
4. training_reward_rules
5. optional sample participants
6. optional sample submissions/reviews/receipts for demo only
```

MVP seed campaigns:

```text
5-Day Movement Challenge
Coffee Shop Opening Routine
14-Day Creator Practice Streak
```

## Local install command examples

Generic import:

```bash
mysql -u USER -p DATABASE_NAME < examples/local-quest-rewards/database/training_campaign_lab.sql
```

Seed import:

```bash
mysql -u USER -p DATABASE_NAME < examples/local-quest-rewards/database/training_campaign_lab_seed.sql
```

## Rollback/drop order

Drop dependent tables first.

```text
1. training_events
2. training_streaks
3. training_reward_issues
4. training_reward_rules
5. training_action_receipts
6. training_reviews
7. training_task_submissions
8. training_files
9. training_participants
10. training_tasks
11. training_sequences
12. training_campaigns
```

## Phase 2 acceptance criteria

Schema is accepted when:

```text
SQL imports cleanly
All required tables exist
Foreign keys are valid
Table names use training_ prefix
Primary keys are BIGINT UNSIGNED
public_id exists where practical
created_at/updated_at exist where practical
No existing Local Quest tables are modified
Validation script can detect required tables in schema file
```

## Common failure risks

```text
Foreign keys created before referenced tables exist
Mismatched signed/unsigned integer types
Different charset/collation between tables
Duplicate enum values across docs and SQL
Missing indexes on status/history pages
Using numeric IDs in URLs instead of public IDs later
```

## Validation script expectations

The validation script should check:

```text
schema file exists
required table names exist in schema text
required status values exist in schema text or status model
required route files exist by phase
required docs exist
upload folder path is documented
```

## Production caution

Before production use:

```text
Review storage engine and charset
Review file storage location
Review backup/restore process
Review retention policy
Review admin permission model
Review indexing for receipt/audit pages
```
