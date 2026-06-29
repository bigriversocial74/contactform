# Training Campaign Lab Schema Outline

## Goal

Define the first SQL structure for the Training Campaign Lab vertical slice.

Implementation file target:

```text
examples/local-quest-rewards/database/training_campaign_lab.sql
```

## Schema principles

- Prefix all new module tables with `training_`.
- Use `BIGINT UNSIGNED` primary keys.
- Add `public_id CHAR(36)` to user-facing records.
- Add `created_at` and `updated_at` where practical.
- Keep proof/review fields separate from reward issue fields.
- Use JSON columns for flexible settings, external payloads, and evidence metadata.
- Order foreign-key creation so imports do not fail.
- Keep MVP simple, but leave room for skills, badges, sponsor pools, teams, streaks, evidence chains, and impact reporting.

## Recommended import order

```text
1. training_organizations
2. training_locations
3. training_teams
4. training_team_members
5. training_campaigns
6. training_challenges
7. training_sequences
8. training_tasks
9. training_participants
10. training_files
11. training_task_submissions
12. training_reviews
13. training_action_receipts
14. training_reward_rules
15. training_reward_issues
16. training_streaks
17. training_milestones
18. training_events
```

Optional later tables:

```text
training_skills
training_task_skills
training_user_skills
training_badges
training_user_badges
training_certifications
training_sponsor_pools
training_leaderboards
training_notifications
training_agent_messages
training_templates
training_external_events
training_impact_reports
```

## Core tables

## training_organizations

Stores the business, gym, merchant, sponsor, school, employer, creator, trainer, or community that owns campaigns.

Suggested fields:

```text
id BIGINT UNSIGNED PK
public_id CHAR(36) UNIQUE
name VARCHAR(190)
slug VARCHAR(190) UNIQUE
organization_type ENUM('merchant','gym','employer','sponsor','school','creator','trainer','community','demo')
owner_user_id VARCHAR(190) NULL
status ENUM('active','paused','archived') DEFAULT 'active'
settings_json JSON NULL
created_at DATETIME
updated_at DATETIME
```

## training_locations

Stores optional physical locations for multi-location merchants, gyms, schools, or events.

Suggested fields:

```text
id BIGINT UNSIGNED PK
public_id CHAR(36) UNIQUE
organization_id BIGINT UNSIGNED
name VARCHAR(190)
slug VARCHAR(190)
address_line1 VARCHAR(190) NULL
address_line2 VARCHAR(190) NULL
city VARCHAR(120) NULL
region VARCHAR(120) NULL
postal_code VARCHAR(40) NULL
country VARCHAR(80) NULL
geo_lat DECIMAL(10,7) NULL
geo_lng DECIMAL(10,7) NULL
status ENUM('active','paused','archived') DEFAULT 'active'
settings_json JSON NULL
created_at DATETIME
updated_at DATETIME
```

## training_teams

Groups participants and reviewers inside an organization.

Suggested fields:

```text
id BIGINT UNSIGNED PK
public_id CHAR(36) UNIQUE
organization_id BIGINT UNSIGNED
location_id BIGINT UNSIGNED NULL
name VARCHAR(190)
slug VARCHAR(190)
description TEXT NULL
status ENUM('active','paused','archived') DEFAULT 'active'
settings_json JSON NULL
created_at DATETIME
updated_at DATETIME
```

## training_team_members

Maps users to teams and organization roles.

Suggested fields:

```text
id BIGINT UNSIGNED PK
public_id CHAR(36) UNIQUE
organization_id BIGINT UNSIGNED
team_id BIGINT UNSIGNED NULL
user_id VARCHAR(190)
email VARCHAR(190) NULL
display_name VARCHAR(190) NULL
role ENUM('owner','admin','manager','coach','reviewer','participant','sponsor','reward_provider')
status ENUM('invited','active','paused','removed') DEFAULT 'active'
joined_at DATETIME NULL
created_at DATETIME
updated_at DATETIME
```

## training_campaigns

Stores campaign-level configuration.

Suggested fields:

```text
id BIGINT UNSIGNED PK
public_id CHAR(36) UNIQUE
organization_id BIGINT UNSIGNED NULL
location_id BIGINT UNSIGNED NULL
team_id BIGINT UNSIGNED NULL
title VARCHAR(190)
slug VARCHAR(190)
description TEXT NULL
campaign_type ENUM('sequence','consistency','challenge','team','sponsor','public','invite_only')
visibility ENUM('public','hidden','invite_only','team_only') DEFAULT 'public'
status ENUM('draft','active','paused','completed','archived') DEFAULT 'draft'
starts_at DATETIME NULL
ends_at DATETIME NULL
join_code VARCHAR(80) NULL
qr_join_token VARCHAR(120) NULL
max_participants INT UNSIGNED DEFAULT 0
reward_program_id VARCHAR(190) NULL
default_template_id VARCHAR(190) NULL
settings_json JSON NULL
created_at DATETIME
updated_at DATETIME
```

## training_challenges

Stores the user-facing goal inside a campaign.

Suggested fields:

```text
id BIGINT UNSIGNED PK
public_id CHAR(36) UNIQUE
campaign_id BIGINT UNSIGNED
title VARCHAR(190)
description TEXT NULL
goal_type ENUM('sequence_completion','streak','frequency','milestone','team_total','sponsor_pool')
goal_target INT UNSIGNED DEFAULT 1
time_window_days INT UNSIGNED NULL
status ENUM('draft','active','paused','completed','archived') DEFAULT 'draft'
settings_json JSON NULL
created_at DATETIME
updated_at DATETIME
```

## training_sequences

Stores repeatable action sequences.

Suggested fields:

```text
id BIGINT UNSIGNED PK
public_id CHAR(36) UNIQUE
campaign_id BIGINT UNSIGNED
challenge_id BIGINT UNSIGNED NULL
title VARCHAR(190)
description TEXT NULL
sequence_type ENUM('one_time','daily','weekly','monthly','custom') DEFAULT 'one_time'
required_order TINYINT(1) DEFAULT 1
repeat_frequency ENUM('none','daily','weekly','monthly','custom') DEFAULT 'none'
difficulty_level ENUM('beginner','intermediate','advanced','custom') DEFAULT 'beginner'
status ENUM('draft','active','paused','archived') DEFAULT 'draft'
sort_order INT UNSIGNED DEFAULT 0
settings_json JSON NULL
created_at DATETIME
updated_at DATETIME
```

## training_tasks

Stores individual steps in a sequence.

Suggested fields:

```text
id BIGINT UNSIGNED PK
public_id CHAR(36) UNIQUE
campaign_id BIGINT UNSIGNED
sequence_id BIGINT UNSIGNED
title VARCHAR(190)
instructions TEXT NULL
proof_instruction TEXT NULL
proof_type ENUM('photo','video','photo_or_video','checklist','qr','geo','manager_approval','coach_approval','external_event','manual_text') DEFAULT 'photo_or_video'
approval_mode ENUM('manual','auto','ai_assisted','manager_final') DEFAULT 'manual'
is_required TINYINT(1) DEFAULT 1
sort_order INT UNSIGNED DEFAULT 0
minimum_duration_seconds INT UNSIGNED NULL
maximum_file_size_mb INT UNSIGNED NULL
allowed_media_types JSON NULL
time_window_rule JSON NULL
rubric_json JSON NULL
settings_json JSON NULL
status ENUM('draft','active','paused','archived') DEFAULT 'draft'
created_at DATETIME
updated_at DATETIME
```

## training_participants

Stores user membership in a campaign.

Suggested fields:

```text
id BIGINT UNSIGNED PK
public_id CHAR(36) UNIQUE
campaign_id BIGINT UNSIGNED
organization_id BIGINT UNSIGNED NULL
team_id BIGINT UNSIGNED NULL
user_id VARCHAR(190)
external_user_id VARCHAR(190) NULL
email VARCHAR(190) NULL
display_name VARCHAR(190) NULL
participant_status ENUM('invited','joined','active','paused','completed','reward_eligible','reward_issued','expired','removed') DEFAULT 'joined'
joined_at DATETIME NULL
completed_at DATETIME NULL
settings_json JSON NULL
created_at DATETIME
updated_at DATETIME
```

## training_files

Stores uploaded proof file metadata.

Suggested fields:

```text
id BIGINT UNSIGNED PK
public_id CHAR(36) UNIQUE
organization_id BIGINT UNSIGNED NULL
campaign_id BIGINT UNSIGNED NULL
participant_id BIGINT UNSIGNED NULL
original_filename VARCHAR(255)
stored_filename VARCHAR(255)
storage_path VARCHAR(500)
storage_provider ENUM('local','s3','external') DEFAULT 'local'
mime_type VARCHAR(120)
file_size_bytes BIGINT UNSIGNED
file_hash CHAR(64) NULL
media_type ENUM('image','video','document','other')
duration_seconds INT UNSIGNED NULL
width INT UNSIGNED NULL
height INT UNSIGNED NULL
retention_policy ENUM('delete_after_review','30_days','campaign_duration','audit_period') DEFAULT 'campaign_duration'
deleted_at DATETIME NULL
metadata_json JSON NULL
created_at DATETIME
updated_at DATETIME
```

## training_task_submissions

Stores participant proof submissions.

Suggested fields:

```text
id BIGINT UNSIGNED PK
public_id CHAR(36) UNIQUE
campaign_id BIGINT UNSIGNED
sequence_id BIGINT UNSIGNED
task_id BIGINT UNSIGNED
participant_id BIGINT UNSIGNED
proof_file_id BIGINT UNSIGNED NULL
proof_note TEXT NULL
attempt_number INT UNSIGNED DEFAULT 1
submission_status ENUM('created','uploaded','pending_review','under_review','approved','rejected','needs_resubmission','archived') DEFAULT 'created'
submitted_at DATETIME NULL
first_submitted_at DATETIME NULL
last_submitted_at DATETIME NULL
metadata_json JSON NULL
created_at DATETIME
updated_at DATETIME
```

## training_reviews

Stores review decisions for submitted proof.

Suggested fields:

```text
id BIGINT UNSIGNED PK
public_id CHAR(36) UNIQUE
submission_id BIGINT UNSIGNED
campaign_id BIGINT UNSIGNED
participant_id BIGINT UNSIGNED
reviewer_id VARCHAR(190) NULL
decision ENUM('approved','rejected','needs_resubmission','flagged')
notes TEXT NULL
score DECIMAL(5,2) NULL
reviewer_confidence DECIMAL(5,2) NULL
ai_confidence DECIMAL(5,2) NULL
review_stage ENUM('primary','secondary','final','ai_assist') DEFAULT 'primary'
reviewed_at DATETIME
metadata_json JSON NULL
created_at DATETIME
updated_at DATETIME
```

## training_action_receipts

Stores verified records of completed tasks, sequences, streaks, milestones, team goals, or sponsor pool events.

Suggested fields:

```text
id BIGINT UNSIGNED PK
public_id CHAR(36) UNIQUE
receipt_type ENUM('task_completion','sequence_completion','streak_completion','milestone_completion','team_completion','sponsor_pool_completion')
action_type VARCHAR(120)
organization_id BIGINT UNSIGNED NULL
team_id BIGINT UNSIGNED NULL
campaign_id BIGINT UNSIGNED
challenge_id BIGINT UNSIGNED NULL
sequence_id BIGINT UNSIGNED NULL
task_id BIGINT UNSIGNED NULL
submission_id BIGINT UNSIGNED NULL
review_id BIGINT UNSIGNED NULL
participant_id BIGINT UNSIGNED NULL
user_id VARCHAR(190) NULL
proof_type VARCHAR(80) NULL
review_status VARCHAR(80) NULL
completion_score DECIMAL(5,2) NULL
action_value_score DECIMAL(10,2) DEFAULT 0
completed_at DATETIME NULL
approved_at DATETIME NULL
reward_rule_id BIGINT UNSIGNED NULL
reward_issue_id BIGINT UNSIGNED NULL
metadata_json JSON NULL
created_at DATETIME
updated_at DATETIME
```

## training_reward_rules

Stores reward ladder and eligibility rules.

Suggested fields:

```text
id BIGINT UNSIGNED PK
public_id CHAR(36) UNIQUE
campaign_id BIGINT UNSIGNED
challenge_id BIGINT UNSIGNED NULL
rule_name VARCHAR(190)
trigger_type ENUM('task_approved','sequence_completed','challenge_completed','streak_reached','frequency_reached','milestone_reached','team_goal_reached','sponsor_pool_threshold','quality_score_reached')
trigger_count INT UNSIGNED DEFAULT 1
time_window_days INT UNSIGNED NULL
reward_type ENUM('microgift','badge','points','contest_entry','unlock','team_pool','sponsor_match','voucher') DEFAULT 'microgift'
reward_label VARCHAR(190)
reward_template_id VARCHAR(190) NULL
reward_value DECIMAL(10,2) NULL
max_issues INT UNSIGNED DEFAULT 0
max_per_user INT UNSIGNED DEFAULT 1
cooldown_hours INT UNSIGNED DEFAULT 0
status ENUM('active','paused','archived') DEFAULT 'active'
settings_json JSON NULL
created_at DATETIME
updated_at DATETIME
```

## training_reward_issues

Maps reward rule triggers to Microgifter reward issue responses.

Suggested fields:

```text
id BIGINT UNSIGNED PK
public_id CHAR(36) UNIQUE
campaign_id BIGINT UNSIGNED
participant_id BIGINT UNSIGNED
user_id VARCHAR(190) NULL
reward_rule_id BIGINT UNSIGNED
action_receipt_id BIGINT UNSIGNED NULL
external_reward_id VARCHAR(190) NULL
microgifter_item_id VARCHAR(190) NULL
issue_status ENUM('locked','eligible','pending_issue','issued','failed','expired') DEFAULT 'locked'
claim_status ENUM('not_claimed','claim_pending','claimed','redeemed','failed','expired') DEFAULT 'not_claimed'
issued_at DATETIME NULL
claimed_at DATETIME NULL
redeemed_at DATETIME NULL
expires_at DATETIME NULL
response_json JSON NULL
created_at DATETIME
updated_at DATETIME
```

## training_streaks

Stores consistency tracking.

Suggested fields:

```text
id BIGINT UNSIGNED PK
public_id CHAR(36) UNIQUE
campaign_id BIGINT UNSIGNED
sequence_id BIGINT UNSIGNED NULL
participant_id BIGINT UNSIGNED
current_streak INT UNSIGNED DEFAULT 0
longest_streak INT UNSIGNED DEFAULT 0
total_completions INT UNSIGNED DEFAULT 0
weekly_completions INT UNSIGNED DEFAULT 0
monthly_completions INT UNSIGNED DEFAULT 0
last_completed_at DATETIME NULL
streak_started_at DATETIME NULL
streak_broken_at DATETIME NULL
recovery_used_count INT UNSIGNED DEFAULT 0
settings_json JSON NULL
created_at DATETIME
updated_at DATETIME
```

## training_milestones

Stores milestone progress and awards.

Suggested fields:

```text
id BIGINT UNSIGNED PK
public_id CHAR(36) UNIQUE
campaign_id BIGINT UNSIGNED
participant_id BIGINT UNSIGNED
milestone_type ENUM('completion_count','streak','quality_score','team_goal','sponsor_pool')
target_value INT UNSIGNED
current_value INT UNSIGNED DEFAULT 0
status ENUM('locked','in_progress','complete','reward_issued','expired') DEFAULT 'locked'
completed_at DATETIME NULL
reward_issue_id BIGINT UNSIGNED NULL
settings_json JSON NULL
created_at DATETIME
updated_at DATETIME
```

## training_events

Stores audit and evidence-chain events.

Suggested fields:

```text
id BIGINT UNSIGNED PK
public_id CHAR(36) UNIQUE
organization_id BIGINT UNSIGNED NULL
campaign_id BIGINT UNSIGNED NULL
participant_id BIGINT UNSIGNED NULL
evidence_chain_id CHAR(36) NULL
event_sequence_number INT UNSIGNED NULL
event_type VARCHAR(160)
actor_type ENUM('system','participant','admin','reviewer','agent','webhook','api') DEFAULT 'system'
actor_id VARCHAR(190) NULL
summary VARCHAR(255) NULL
event_payload JSON NULL
created_at DATETIME
```

## Optional future tables

## training_sponsor_pools

Tracks sponsor reward budgets.

Fields to account for:

```text
sponsor_id
campaign_id
reward_pool_amount
amount_allocated
amount_issued
amount_claimed
amount_remaining
match_rule_json
status
```

## training_skills / training_task_skills / training_user_skills

Tracks skill progress, skill levels, and recertification.

Fields to account for:

```text
skill_name
skill_category
task_id
user_id
skill_level
verified_count
last_verified_at
expires_at
```

## training_badges / training_user_badges / training_certifications

Tracks status and certification outcomes.

Fields to account for:

```text
badge_name
certification_name
issued_to_user_id
issued_by_user_id
receipt_id
expires_at
certification_status
```

## training_notifications

Tracks reminders and agent/coach notifications.

Fields to account for:

```text
recipient_id
campaign_id
notification_type
trigger_event
message
delivery_channel
sent_at
read_at
status
```

## training_external_events

Tracks API/webhook proof sources.

Fields to account for:

```text
external_provider
external_event_id
verification_source
webhook_signature_status
external_payload
matched_participant_id
matched_task_id
```

## First implementation recommendation

For the first build, implement only the core tables required for the vertical slice:

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

Keep organizations, teams, locations, skills, badges, sponsor pools, and notifications in the schema outline, but defer full UI implementation until the proof/reward loop works.
