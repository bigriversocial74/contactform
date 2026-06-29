# Expanded Training Campaign Lab Platform Outline

## Purpose

This document captures the larger platform ideas that should shape the Training Campaign Lab architecture before implementation.

The MVP should stay focused, but the data model should leave room for proof quality, consistency, reward economics, coaching, team goals, sponsor pools, and post-reward outcomes.

## Core platform thesis

```text
Microgifter Training Campaigns measure not only completion, but effort, consistency, improvement, verification quality, and reward impact.
```

Microgifter turns completed human action into verified, rewardable data.

Training Campaigns apply that idea to action sequences, routines, streaks, challenges, teams, and progress-based rewards.

## Product layers

```text
1. Action Layer
What must the participant do?

2. Proof Layer
How does the participant prove it?

3. Review Layer
Who or what verifies the proof?

4. Receipt Layer
What durable record proves completion?

5. Progress Layer
How does the action count toward streaks, milestones, skills, or certification?

6. Reward Layer
What value unlocks when the rule is satisfied?

7. Outcome Layer
What happened after the reward was issued, claimed, or redeemed?
```

## Action and training structure

The system should support more than one campaign type.

### Campaign

A rewardable program owned by an organization, gym, merchant, employer, sponsor, trainer, creator, school, or community.

### Challenge

A user-facing goal inside a campaign.

Examples:

- Complete 5 verified routines this week.
- Complete 10 workouts in 14 days.
- Complete opening checklist 5 days in a row.
- Complete onboarding sequence before Friday.

### Sequence

A repeatable set of required actions.

Examples:

- Daily Movement Routine
- Coffee Shop Opening Sequence
- Safety Inspection Sequence
- Creator Practice Routine
- Event Booth Setup Routine

### Task

One required action inside a sequence.

Examples:

- Upload warmup video.
- Upload clean counter photo.
- Upload squat video.
- Upload QR table tent placement photo.
- Submit practice note.

## Training paths

A campaign is one unit. A training path is a series of campaigns or sequences that unlock progressively.

Example:

```text
Coffee Shop Staff Training Path
  Level 1: Opening Routine
  Level 2: Equipment Handling
  Level 3: Customer Service Sequence
  Level 4: Closing Routine
  Level 5: Shift Lead Certification
```

Account for:

- path_id
- prerequisite_campaign_id
- unlock_rule
- required_level
- user_level
- certification outcome

## Difficulty levels and adaptive paths

Campaigns should support beginner, intermediate, and advanced paths.

Examples:

```text
Beginner: 5 squats, 20-second plank
Intermediate: 15 squats, 45-second plank
Advanced: 30 squats, 90-second plank
```

Account for:

- difficulty_level
- participant_level
- adaptive_level
- recommended_next_level
- difficulty_adjustment_reason
- coach_override

## Proof instructions

Each task should include clear proof instructions.

Examples:

```text
Upload a photo showing the entire counter, register area, and QR table tent.
Upload a 15-second side-angle video showing all 10 squats.
Upload a short video showing espresso machine startup.
```

Account for:

- proof_instruction
- example_image_url
- example_video_url
- required_camera_angle
- required_objects_visible
- minimum_duration_seconds
- accepted_media_types

## Proof rubrics

Proof review should eventually use rubrics, not only approve/reject.

Example merchant rubric:

```text
Counter is clean: 40 points
Pastry case is stocked: 30 points
QR table tent visible: 20 points
Photo is clear: 10 points
```

Example fitness rubric:

```text
Full movement visible
Correct number of reps
Safe form
Routine completed within time
```

Account for:

- rubric_items
- minimum_passing_score
- rubric_score
- reviewer_confidence
- ai_confidence
- proof_quality_score

## Proof quality and confidence

Separate approval from confidence.

Examples:

```text
approved, 70% confidence
approved, 95% confidence
rejected, 90% confidence
needs review, low confidence
```

Account for:

- verification_confidence
- reviewer_confidence
- ai_confidence
- proof_confidence
- fraud_confidence
- completion_score

## Attempt and resubmission tracking

Participants may need multiple tries.

Track:

- attempt_number
- first_submitted_at
- last_submitted_at
- approved_attempt_number
- rejection_count
- resubmission_count
- time_to_approval
- resubmission_reason

This helps identify confusing tasks, poor proof instructions, users who need coaching, and routines that are too difficult.

## Appeals and disputes

Some rejected proof may need an appeal path.

Account for:

- appeal_requested
- appeal_note
- appeal_status
- appeal_reviewer_id
- appeal_decision
- appeal_decided_at

Not MVP, but useful for teams, paid challenges, and compliance programs.

## Skills and competencies

Each task can map to one or more skills.

Examples:

```text
Task: Upload squat video
Skill: Lower-body movement

Task: Upload espresso startup video
Skill: Equipment operation

Task: Upload clean counter photo
Skill: Store readiness
```

Account for:

- skills
- task_skill_map
- user_skill_progress
- skill_level
- skill_expiration
- recertification_required

## Badges and certifications

Rewards are commerce. Badges and certifications are status and durable proof.

Examples:

- Opening Routine Certified
- 5-Day Movement Challenge Complete
- Safety Setup Verified
- Coffee Bar Level 1
- Creator Practice Streak: 14 Days

Account for:

- badges
- certifications
- user_badges
- certification_status
- expires_at
- issued_by
- proof_receipt_id

## Certification expiration and recertification

Some training proof should expire.

Examples:

```text
Safety training expires every 90 days.
Opening routine certification expires after 6 months.
Fitness challenge badge is permanent, but streak resets monthly.
```

Account for:

- certification_expires_at
- recertification_required
- proof_valid_until
- renewal_campaign_id

## Personal baseline and improvement tracking

For fitness and coaching, reward improvement against a user baseline, not only absolute performance.

Examples:

```text
Week 1: 20-second plank
Week 4: 60-second plank
Reward unlocked for improvement.
```

Account for:

- baseline_value
- current_value
- improvement_percentage
- personal_best
- baseline_date
- progress_delta

## Reward fairness and alternate tasks

Training campaigns should allow equivalent paths when needed.

Examples:

- alternate movement for accessibility
- beginner path counts equally
- coach-adjusted goal
- medical limitation accommodation
- manager-approved substitute task

Account for:

- alternate_task_id
- accessibility_mode
- participant_adjustment
- coach_override
- equivalent_completion_rule

## Habit design and streak protection

Consistency systems need real-life flexibility.

Features:

- daily goals
- weekly goals
- recovery days
- missed-day recovery
- restart rules
- grace periods
- vacation pause
- sick day pause
- manager-approved pause
- streak freeze

Account for:

- pause_reason
- pause_start
- pause_end
- streak_freeze_used
- recovery_task_id
- recovery_allowed_count
- recovery_used_count
- streak_preserved

## Reward ladders and locked rewards

Show users what they are working toward before it unlocks.

Example:

```text
Locked: $15 Wellness Reward
Progress: 3 of 5 verified routines
Unlocks after: 2 more approved submissions
```

Account for:

- locked_reward_visible
- unlock_progress
- unlock_requirement_text
- reward_preview_image
- reward_choice_enabled
- reward_catalog_id
- selected_reward_id

## Reward choice

Users may choose from reward options once they qualify.

Examples:

- $5 smoothie
- $5 coffee
- $5 local lunch credit
- contest entry
- badge only

Account for:

- reward_catalog_id
- selected_reward_id
- reward_selection_deadline
- reward_type
- reward_unit
- reward_value
- reward_delivery_method

## Reward budget controls

Organizations need caps.

Account for:

- max_rewards_per_user
- max_rewards_per_day
- max_rewards_per_campaign
- max_total_reward_value
- daily_budget_cap
- weekly_budget_cap
- cooldown_period
- duplicate_reward_prevention
- reward_velocity_limit
- reward_pacing_rule

## Sponsor pools and matching

A sponsor can fund or match rewards.

Examples:

```text
Sponsor funds $2,500.
Each verified 5-day fitness completion unlocks $10.
Campaign stops when pool is exhausted.
```

```text
For every 10 verified workouts, sponsor adds $25 to local reward pool.
```

Account for:

- sponsor_id
- reward_pool_amount
- amount_allocated
- amount_issued
- amount_claimed
- amount_remaining
- sponsor_match_rule
- matching_amount
- match_threshold
- cofunding_partner_id

## Team goals and group rewards

A reward should not always be individual.

Examples:

```text
Everyone on Morning Crew completes the opening routine 5 days in a row -> team reward.
10 gym members complete the challenge -> group reward.
Creator community reaches 1,000 total practice uploads -> sponsor reward.
```

Account for:

- team_goal
- team_progress
- team_reward_rule
- group_reward_issue
- minimum_team_participation
- shared_reward_pool

## Leaderboards

Leaderboards can drive engagement for fitness, team training, creator practice, and staff routines.

Leaderboard types:

- most sequences completed
- longest streak
- highest score
- team completion rate
- fastest completion
- most improved

Account for:

- leaderboard_score
- rank
- rank_period
- team_rank
- streak_rank
- quality_rank

## Reviewer workload and approval chains

Reviewers need queues and assignment.

Features:

- assign submissions to reviewer
- reviewer queue
- review SLA
- overdue reviews
- bulk approve
- bulk reject
- second review required
- final reward approver

Account for:

- assigned_reviewer_id
- review_due_at
- review_sla_hours
- second_review_required
- review_priority
- approval_stage
- required_approval_count
- final_approver_id
- approval_chain_status

## Evidence chain and audit trail

Every important event should be logged.

Evidence chain example:

```text
Task assigned
Proof submitted
File stored
Reviewer opened
Reviewer approved
Receipt created
Reward issued
Wallet updated
Claim reported
Webhook confirmed
```

Account for:

- evidence_chain_id
- event_sequence_number
- event_type
- actor_id
- event_payload
- created_at

Audit events:

- campaign created
- task submitted
- proof uploaded
- review approved
- review rejected
- receipt created
- reward issued
- reward claimed
- reward expired

## Fraud and abuse controls

Proof-based rewards need basic protections.

Track:

- duplicate_file_hash
- same_video_reused
- same_photo_reused
- suspicious_submission_flag
- too_many_submissions
- same_device_multiple_accounts
- manual_review_required
- campaign_risk_status
- auto_pause_enabled
- pause_reason
- risk_score
- risk_review_required

## Privacy, consent, and retention

Because photo/video proof is sensitive, privacy needs to be built in early.

Account for:

- proof_consent_given
- media_retention_policy
- can_use_for_training
- can_use_for_ai_review
- can_share_with_sponsor
- can_share_with_manager
- delete_after_review
- delete_after_campaign
- terms_version
- waiver_accepted_at
- media_consent_at
- reward_terms_accepted_at

Recommended defaults:

- collect only what the task requires
- do not use proof for biometric identity by default
- keep sponsor visibility aggregate-only by default
- make proof retention configurable

## Proof visibility rules

Not everyone should see uploaded proof.

Default visibility:

- participant sees own proof
- reviewer sees assigned proof
- admin sees organization proof
- sponsor sees aggregate reporting only
- reward provider sees reward/claim/redemption data, not private proof by default

Account for:

- proof_visibility
- reviewer_scope
- sponsor_visibility
- merchant_visibility

## Location, environment, equipment, and inventory context

Training tasks may involve a real-world location, asset, or inventory state.

Examples:

- Store #3 opening checklist
- espresso machine startup
- QR table tent placement
- stocked pastry case
- event booth setup
- POS setup

Account for:

- location_id
- geo_lat
- geo_lng
- geo_accuracy
- location_verified
- environment_context
- asset_id
- asset_type
- asset_status
- asset_photo_required
- inventory_item_id
- stock_status
- display_status
- restock_needed

## Multi-location support

A campaign may run across multiple locations.

Examples:

```text
Same opening routine across 5 locations.
Each location has its own team, manager, proof queue, budget, and completion rate.
```

Account for:

- location_id
- location_team_id
- location_reward_budget
- location_completion_rate
- task_location_override
- location_specific_instruction
- location_specific_reward_rule

## Campaign templates, cloning, and versioning

Organizations should be able to reuse successful campaigns.

Features:

- clone campaign
- clone sequence
- clone reward ladder
- save as template
- version campaign changes

Account for:

- campaign_templates
- sequence_templates
- task_templates
- reward_ladder_templates
- source_campaign_id
- cloned_from_id
- template_version
- campaign_version
- sequence_version
- task_version
- proof_instruction_version
- reward_rule_version

## Notifications and agent coaching

The agent should eventually act as coach, reviewer assistant, and campaign builder.

Notification examples:

```text
You have one task left.
Upload today’s proof by 8 PM.
Your proof was rejected. Re-upload to keep your streak.
You are one day away from a reward.
```

Agent coaching data:

- agent_message_log
- coach_recommendation
- next_best_action
- missed_step_reason
- streak_risk
- reward_opportunity
- participant_summary
- recommendation_reason
- recommended_campaign_id

## User segments and next-best action

Segments:

- new participant
- active participant
- at-risk participant
- streak builder
- high performer
- needs coaching
- reward earned but not claimed
- claimed but not redeemed
- inactive after reward

Next-best-action examples:

- completed first routine -> suggest 5-day streak
- failed proof twice -> suggest coaching tip
- claimed reward -> suggest next campaign
- missed deadline -> send restart option

## Proof-to-commerce attribution

This is one of the most important Microgifter-specific layers.

The system should connect:

```text
Training action -> reward issued -> reward claimed -> reward redeemed -> commerce created
```

Track:

- commerce_attribution_id
- reward_redemption_location
- reward_redemption_value
- campaign_generated_revenue
- cost_per_verified_action
- cost_per_redemption
- reward_claim_rate
- redemption_rate

## Human effort value score

Microgifter can score verified effort.

Examples:

```text
Upload one photo = 1 action point
Complete 5-step routine = 10 action points
Complete 7-day streak = 50 action points
Complete team goal = 250 action points
```

Account for:

- action_value_score
- effort_weight
- difficulty_weight
- consistency_multiplier
- team_multiplier
- reward_value_ratio

## Reward impact reporting

Sponsors, merchants, and organizations need impact reports.

Example report:

```text
500 verified wellness actions
$4,200 in rewards issued
72% claimed
48% redeemed at local businesses
Top redemption category: coffee/smoothies
```

Account for:

- impact_report_id
- verified_actions_count
- reward_value_issued
- reward_value_claimed
- reward_value_redeemed
- local_impact_estimate

## External proof and API events

Not all proof will be uploads.

Future proof sources:

- fitness app
- calendar attendance
- POS event
- QR scan
- geolocation check-in
- webhook
- LMS/training platform
- wearable/sensor

Account for:

- external_event_id
- external_provider
- external_payload
- verification_source
- webhook_signature_status

## Webhook events

Future events:

```text
training.task.submitted
training.task.approved
training.task.rejected
training.sequence.completed
training.streak.updated
training.reward.eligible
training.reward.issued
training.reward.claimed
training.reward.redeemed
```

## White-label and embedded pages

Organizations may want branded public pages.

Account for:

- embed_enabled
- brand_name
- brand_color
- logo_url
- campaign_header_image
- custom_domain
- public_campaign_url
- custom_terms
- custom_success_message

## Shareable completion cards and testimonials

After completion, participants can share progress.

Account for:

- share_card_url
- share_text
- public_badge_url
- participant_rating
- testimonial
- reward_satisfaction
- motivation_score

## MVP boundary

The MVP should include:

- campaigns
- sequences
- tasks
- photo/video proof
- manual review
- reviewer notes
- resubmission
- Action Receipts
- reward issue
- basic streak count
- basic reward ladder
- basic file metadata
- basic event log

Not MVP, but account for now:

- AI review
- badges/certifications
- sponsor pools
- team rewards
- leaderboards
- external proof APIs
- notifications
- agent coach
- full privacy/retention UI
- fraud scoring
- reward marketplaces
- formal compliance exports

## Strategic build rule

Do not model this as only a campaign/reward system.

Model it as a verified progress system.

Every feature should answer:

```text
What action was required?
What proof was submitted?
Who verified it?
How good was the completion?
Did it count toward a sequence?
Did it count toward consistency?
What reward did it unlock?
What did the user do next?
```
