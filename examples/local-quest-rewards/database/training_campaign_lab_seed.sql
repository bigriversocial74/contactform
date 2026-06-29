-- ------------------------------------------------------------
-- Microgifter Training Campaign Lab Seed Data
-- Import after training_campaign_lab.sql
-- ------------------------------------------------------------

INSERT INTO training_campaigns
(public_id, slug, title, subtitle, description, campaign_type, visibility, status, difficulty, sponsor_name, metadata_json)
VALUES
('00000000-0000-4000-8000-000000000101', '5-day-movement-challenge', '5-Day Movement Challenge', 'Verified movement, daily proof, rewardable consistency', 'Complete a daily movement sequence, upload photo or video proof, and unlock rewards for verified consistency.', 'fitness', 'public', 'active', 'Beginner', 'Microgifter Wellness', JSON_OBJECT('tags', JSON_ARRAY('Fitness','Streak','Video Proof'), 'reward_preview', '$15 Wellness Microgift', 'duration', '5 days')),
('00000000-0000-4000-8000-000000000102', 'coffee-shop-opening-routine', 'Coffee Shop Opening Routine', 'Team readiness proof for local merchants', 'Verify daily store-readiness actions with photos, checklist steps, and manager review.', 'merchant', 'team', 'active', 'Easy', 'Microgifter Merchant Lab', JSON_OBJECT('tags', JSON_ARRAY('Merchant','Team','Photo Proof'), 'reward_preview', '$10 Team Reward', 'duration', 'Weekday routine')),
('00000000-0000-4000-8000-000000000103', '14-day-creator-practice-streak', '14-Day Creator Practice Streak', 'Daily practice proof for creators', 'Encourage daily creator practice through short uploads, proof receipts, and streak-based rewards.', 'creator', 'public', 'active', 'Medium', 'Microgifter Creator Lab', JSON_OBJECT('tags', JSON_ARRAY('Creator','Consistency','Daily Upload'), 'reward_preview', 'Exclusive Badge + Reward', 'duration', '14 days'));

INSERT INTO training_sequences
(public_id, campaign_id, slug, title, description, sort_order, is_required, status)
SELECT '00000000-0000-4000-8000-000000000201', id, 'daily-movement-routine', 'Daily Movement Routine', 'A repeatable four-step movement sequence with proof upload at each step.', 1, 1, 'active'
FROM training_campaigns WHERE slug = '5-day-movement-challenge';

INSERT INTO training_sequences
(public_id, campaign_id, slug, title, description, sort_order, is_required, status)
SELECT '00000000-0000-4000-8000-000000000202', id, 'daily-opening-checklist', 'Daily Opening Checklist', 'A store readiness sequence for teams and shift managers.', 1, 1, 'active'
FROM training_campaigns WHERE slug = 'coffee-shop-opening-routine';

INSERT INTO training_sequences
(public_id, campaign_id, slug, title, description, sort_order, is_required, status)
SELECT '00000000-0000-4000-8000-000000000203', id, 'daily-practice-routine', 'Daily Practice Routine', 'A daily creative practice streak with lightweight proof uploads.', 1, 1, 'active'
FROM training_campaigns WHERE slug = '14-day-creator-practice-streak';

-- 5-Day Movement Challenge tasks
INSERT INTO training_tasks
(public_id, campaign_id, sequence_id, slug, title, description, instructions, proof_type, accepted_extensions, max_file_size_mb, points, sort_order, is_required, status)
SELECT '00000000-0000-4000-8000-000000000301', c.id, s.id, 'warm-up', 'Warm-Up', 'Complete a 5-10 minute warm-up and upload proof.', 'Upload a clear photo or short video that shows your warm-up activity.', 'photo', 'jpg,jpeg,png,webp,mp4,mov,webm', 50, 10, 1, 1, 'active'
FROM training_campaigns c JOIN training_sequences s ON s.campaign_id = c.id WHERE c.slug = '5-day-movement-challenge' AND s.slug = 'daily-movement-routine';
INSERT INTO training_tasks
(public_id, campaign_id, sequence_id, slug, title, description, instructions, proof_type, accepted_extensions, max_file_size_mb, points, sort_order, is_required, status)
SELECT '00000000-0000-4000-8000-000000000302', c.id, s.id, 'movement-session', 'Movement Session', 'Complete your main movement activity for at least 20 minutes.', 'Upload a short video or photo proof from your activity session.', 'video', 'jpg,jpeg,png,webp,mp4,mov,webm', 100, 15, 2, 1, 'active'
FROM training_campaigns c JOIN training_sequences s ON s.campaign_id = c.id WHERE c.slug = '5-day-movement-challenge' AND s.slug = 'daily-movement-routine';
INSERT INTO training_tasks
(public_id, campaign_id, sequence_id, slug, title, description, instructions, proof_type, accepted_extensions, max_file_size_mb, points, sort_order, is_required, status)
SELECT '00000000-0000-4000-8000-000000000303', c.id, s.id, 'cool-down', 'Cool Down', 'Stretch or recover after your movement session.', 'Upload a photo or note showing your cooldown was completed.', 'photo', 'jpg,jpeg,png,webp', 25, 10, 3, 1, 'active'
FROM training_campaigns c JOIN training_sequences s ON s.campaign_id = c.id WHERE c.slug = '5-day-movement-challenge' AND s.slug = 'daily-movement-routine';
INSERT INTO training_tasks
(public_id, campaign_id, sequence_id, slug, title, description, instructions, proof_type, accepted_extensions, max_file_size_mb, points, sort_order, is_required, status)
SELECT '00000000-0000-4000-8000-000000000304', c.id, s.id, 'reflection', 'Reflection', 'Submit a short note about how the session went.', 'Write one or two sentences about your effort, progress, or recovery.', 'text', NULL, NULL, 15, 4, 1, 'active'
FROM training_campaigns c JOIN training_sequences s ON s.campaign_id = c.id WHERE c.slug = '5-day-movement-challenge' AND s.slug = 'daily-movement-routine';

-- Coffee Shop Opening Routine tasks
INSERT INTO training_tasks
(public_id, campaign_id, sequence_id, slug, title, description, instructions, proof_type, accepted_extensions, max_file_size_mb, points, sort_order, is_required, status)
SELECT '00000000-0000-4000-8000-000000000401', c.id, s.id, 'clean-counter-photo', 'Clean Counter Photo', 'Upload a photo of the clean counter and register area.', 'Take a clear photo before opening.', 'photo', 'jpg,jpeg,png,webp', 25, 10, 1, 1, 'active'
FROM training_campaigns c JOIN training_sequences s ON s.campaign_id = c.id WHERE c.slug = 'coffee-shop-opening-routine' AND s.slug = 'daily-opening-checklist';
INSERT INTO training_tasks
(public_id, campaign_id, sequence_id, slug, title, description, instructions, proof_type, accepted_extensions, max_file_size_mb, points, sort_order, is_required, status)
SELECT '00000000-0000-4000-8000-000000000402', c.id, s.id, 'stocked-pastry-case', 'Stocked Pastry Case', 'Upload a photo of stocked display items.', 'Make sure the case is visible and organized.', 'photo', 'jpg,jpeg,png,webp', 25, 10, 2, 1, 'active'
FROM training_campaigns c JOIN training_sequences s ON s.campaign_id = c.id WHERE c.slug = 'coffee-shop-opening-routine' AND s.slug = 'daily-opening-checklist';
INSERT INTO training_tasks
(public_id, campaign_id, sequence_id, slug, title, description, instructions, proof_type, accepted_extensions, max_file_size_mb, points, sort_order, is_required, status)
SELECT '00000000-0000-4000-8000-000000000403', c.id, s.id, 'espresso-startup', 'Espresso Startup', 'Upload a short startup proof clip.', 'Capture a quick clip of startup/prep completion.', 'video', 'mp4,mov,webm', 100, 15, 3, 1, 'active'
FROM training_campaigns c JOIN training_sequences s ON s.campaign_id = c.id WHERE c.slug = 'coffee-shop-opening-routine' AND s.slug = 'daily-opening-checklist';
INSERT INTO training_tasks
(public_id, campaign_id, sequence_id, slug, title, description, instructions, proof_type, accepted_extensions, max_file_size_mb, points, sort_order, is_required, status)
SELECT '00000000-0000-4000-8000-000000000404', c.id, s.id, 'qr-table-tent', 'QR Table Tent', 'Confirm QR/table tent placement is visible.', 'Upload a photo of the Microgifter table tent or QR display.', 'photo', 'jpg,jpeg,png,webp', 25, 10, 4, 1, 'active'
FROM training_campaigns c JOIN training_sequences s ON s.campaign_id = c.id WHERE c.slug = 'coffee-shop-opening-routine' AND s.slug = 'daily-opening-checklist';
INSERT INTO training_tasks
(public_id, campaign_id, sequence_id, slug, title, description, instructions, proof_type, accepted_extensions, max_file_size_mb, points, sort_order, is_required, status)
SELECT '00000000-0000-4000-8000-000000000405', c.id, s.id, 'manager-approval', 'Manager Approval', 'Manager verifies the opening sequence.', 'Manager reviews the opening proof set.', 'manager_approval', NULL, NULL, 15, 5, 1, 'active'
FROM training_campaigns c JOIN training_sequences s ON s.campaign_id = c.id WHERE c.slug = 'coffee-shop-opening-routine' AND s.slug = 'daily-opening-checklist';

-- Creator Practice tasks
INSERT INTO training_tasks
(public_id, campaign_id, sequence_id, slug, title, description, instructions, proof_type, accepted_extensions, max_file_size_mb, points, sort_order, is_required, status)
SELECT '00000000-0000-4000-8000-000000000501', c.id, s.id, 'practice-clip', 'Practice Clip', 'Upload a 30-second practice clip.', 'Submit a short practice clip from today.', 'video', 'mp4,mov,webm', 100, 15, 1, 1, 'active'
FROM training_campaigns c JOIN training_sequences s ON s.campaign_id = c.id WHERE c.slug = '14-day-creator-practice-streak' AND s.slug = 'daily-practice-routine';
INSERT INTO training_tasks
(public_id, campaign_id, sequence_id, slug, title, description, instructions, proof_type, accepted_extensions, max_file_size_mb, points, sort_order, is_required, status)
SELECT '00000000-0000-4000-8000-000000000502', c.id, s.id, 'practice-note', 'Practice Note', 'Submit a short note about today’s practice.', 'Write what you practiced and what improved.', 'text', NULL, NULL, 10, 2, 1, 'active'
FROM training_campaigns c JOIN training_sequences s ON s.campaign_id = c.id WHERE c.slug = '14-day-creator-practice-streak' AND s.slug = 'daily-practice-routine';
INSERT INTO training_tasks
(public_id, campaign_id, sequence_id, slug, title, description, instructions, proof_type, accepted_extensions, max_file_size_mb, points, sort_order, is_required, status)
SELECT '00000000-0000-4000-8000-000000000503', c.id, s.id, 'daily-completion', 'Daily Completion', 'Confirm the daily routine is complete.', 'Complete the daily checklist confirmation.', 'checklist', NULL, NULL, 10, 3, 1, 'active'
FROM training_campaigns c JOIN training_sequences s ON s.campaign_id = c.id WHERE c.slug = '14-day-creator-practice-streak' AND s.slug = 'daily-practice-routine';

-- Reward rules
INSERT INTO training_reward_rules
(public_id, campaign_id, title, trigger_type, required_completions, required_streak, milestone_target, reward_label, reward_type, reward_value_cents, budget_cap_cents, expires_after_days, linked_microgifter_program_id, linked_microgifter_template_id, status, sort_order)
SELECT '00000000-0000-4000-8000-000000000601', id, 'First Verified Sequence Reward', 'sequence_completion', 1, 0, 0, '$5 Smoothie Microgift', 'microgift', 500, 50000, 30, NULL, NULL, 'active', 1
FROM training_campaigns WHERE slug = '5-day-movement-challenge';
INSERT INTO training_reward_rules
(public_id, campaign_id, title, trigger_type, required_completions, required_streak, milestone_target, reward_label, reward_type, reward_value_cents, budget_cap_cents, expires_after_days, linked_microgifter_program_id, linked_microgifter_template_id, status, sort_order)
SELECT '00000000-0000-4000-8000-000000000602', id, 'Five Day Streak Reward', 'streak_completion', 0, 5, 0, '$15 Wellness Microgift', 'microgift', 1500, 150000, 30, NULL, NULL, 'active', 2
FROM training_campaigns WHERE slug = '5-day-movement-challenge';
INSERT INTO training_reward_rules
(public_id, campaign_id, title, trigger_type, required_completions, required_streak, milestone_target, reward_label, reward_type, reward_value_cents, budget_cap_cents, expires_after_days, linked_microgifter_program_id, linked_microgifter_template_id, status, sort_order)
SELECT '00000000-0000-4000-8000-000000000603', id, 'Team Opening Completion Reward', 'sequence_completion', 1, 0, 0, '$10 Team Reward', 'microgift', 1000, 100000, 14, NULL, NULL, 'active', 1
FROM training_campaigns WHERE slug = 'coffee-shop-opening-routine';
INSERT INTO training_reward_rules
(public_id, campaign_id, title, trigger_type, required_completions, required_streak, milestone_target, reward_label, reward_type, reward_value_cents, budget_cap_cents, expires_after_days, linked_microgifter_program_id, linked_microgifter_template_id, status, sort_order)
SELECT '00000000-0000-4000-8000-000000000604', id, 'Creator Practice Milestone', 'sequence_completion', 1, 0, 0, 'Creator Badge + Reward', 'badge', 0, NULL, NULL, NULL, NULL, 'active', 1
FROM training_campaigns WHERE slug = '14-day-creator-practice-streak';

INSERT INTO training_events
(public_id, event_type, actor_user_id, actor_role, campaign_id, target_type, target_id, status_after, metadata_json)
SELECT '00000000-0000-4000-8000-000000000701', 'training.seed.loaded', 'system', 'system', NULL, 'seed', 'training_campaign_lab_seed', 'loaded', JSON_OBJECT('campaign_count', 3, 'purpose', 'Training Campaign Lab demo seed');
