-- ------------------------------------------------------------
-- Microgifter Training Campaign Lab Schema
-- Branch: local-quest-workspace
-- Purpose: additive schema for the duplicate Loyalty Quest workspace
-- ------------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS training_events;
DROP TABLE IF EXISTS training_streaks;
DROP TABLE IF EXISTS training_reward_issues;
DROP TABLE IF EXISTS training_reward_rules;
DROP TABLE IF EXISTS training_action_receipts;
DROP TABLE IF EXISTS training_reviews;
DROP TABLE IF EXISTS training_task_submissions;
DROP TABLE IF EXISTS training_files;
DROP TABLE IF EXISTS training_participants;
DROP TABLE IF EXISTS training_tasks;
DROP TABLE IF EXISTS training_sequences;
DROP TABLE IF EXISTS training_campaigns;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Campaigns
-- ------------------------------------------------------------

CREATE TABLE training_campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  title VARCHAR(190) NOT NULL,
  subtitle VARCHAR(255) NULL,
  description TEXT NOT NULL,
  campaign_type VARCHAR(80) NOT NULL DEFAULT 'general',
  visibility ENUM('public','private','team','invite_only') NOT NULL DEFAULT 'public',
  status ENUM('draft','active','paused','completed','archived') NOT NULL DEFAULT 'draft',
  difficulty VARCHAR(60) NULL,
  sponsor_name VARCHAR(190) NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  created_by_user_id VARCHAR(120) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_campaigns_public_id (public_id),
  UNIQUE KEY uq_training_campaigns_slug (slug),
  KEY idx_training_campaigns_status (status),
  KEY idx_training_campaigns_type (campaign_type),
  KEY idx_training_campaigns_visibility (visibility)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Sequences
-- ------------------------------------------------------------

CREATE TABLE training_sequences (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(160) NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 1,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'active',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_sequences_public_id (public_id),
  UNIQUE KEY uq_training_sequences_campaign_slug (campaign_id, slug),
  KEY idx_training_sequences_campaign (campaign_id),
  KEY idx_training_sequences_status (status),
  CONSTRAINT fk_training_sequences_campaign FOREIGN KEY (campaign_id) REFERENCES training_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tasks
-- ------------------------------------------------------------

CREATE TABLE training_tasks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  sequence_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(160) NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  instructions TEXT NULL,
  proof_type ENUM('photo','video','file','text','checklist','manager_approval','qr_geo') NOT NULL DEFAULT 'photo',
  accepted_extensions VARCHAR(255) NULL,
  max_file_size_mb INT UNSIGNED NULL,
  points INT UNSIGNED NOT NULL DEFAULT 0,
  sort_order INT UNSIGNED NOT NULL DEFAULT 1,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'active',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_tasks_public_id (public_id),
  UNIQUE KEY uq_training_tasks_sequence_slug (sequence_id, slug),
  KEY idx_training_tasks_campaign (campaign_id),
  KEY idx_training_tasks_sequence (sequence_id),
  KEY idx_training_tasks_status (status),
  CONSTRAINT fk_training_tasks_campaign FOREIGN KEY (campaign_id) REFERENCES training_campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_tasks_sequence FOREIGN KEY (sequence_id) REFERENCES training_sequences(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Participants
-- ------------------------------------------------------------

CREATE TABLE training_participants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  user_id VARCHAR(120) NOT NULL,
  external_user_id VARCHAR(160) NULL,
  display_name VARCHAR(190) NULL,
  email VARCHAR(190) NULL,
  role ENUM('participant','reviewer','manager','admin','owner') NOT NULL DEFAULT 'participant',
  status ENUM('invited','active','completed','inactive','removed') NOT NULL DEFAULT 'active',
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_participants_public_id (public_id),
  UNIQUE KEY uq_training_participants_campaign_user (campaign_id, user_id),
  KEY idx_training_participants_campaign (campaign_id),
  KEY idx_training_participants_user (user_id),
  KEY idx_training_participants_status (status),
  CONSTRAINT fk_training_participants_campaign FOREIGN KEY (campaign_id) REFERENCES training_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Files
-- ------------------------------------------------------------

CREATE TABLE training_files (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  uploaded_by_user_id VARCHAR(120) NOT NULL,
  participant_id BIGINT UNSIGNED NULL,
  original_filename VARCHAR(255) NOT NULL,
  stored_filename VARCHAR(255) NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  file_extension VARCHAR(20) NOT NULL,
  mime_type VARCHAR(160) NULL,
  file_size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  file_hash VARCHAR(128) NULL,
  status ENUM('uploaded','attached','archived','removed') NOT NULL DEFAULT 'uploaded',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_files_public_id (public_id),
  KEY idx_training_files_participant (participant_id),
  KEY idx_training_files_uploaded_by (uploaded_by_user_id),
  KEY idx_training_files_status (status),
  CONSTRAINT fk_training_files_participant FOREIGN KEY (participant_id) REFERENCES training_participants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Task Submissions
-- ------------------------------------------------------------

CREATE TABLE training_task_submissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  sequence_id BIGINT UNSIGNED NOT NULL,
  task_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  file_id BIGINT UNSIGNED NULL,
  attempt_number INT UNSIGNED NOT NULL DEFAULT 1,
  participant_note TEXT NULL,
  status ENUM('draft','pending_review','approved','rejected','needs_resubmission','expired','withdrawn') NOT NULL DEFAULT 'pending_review',
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_task_submissions_public_id (public_id),
  KEY idx_training_task_submissions_campaign (campaign_id),
  KEY idx_training_task_submissions_sequence (sequence_id),
  KEY idx_training_task_submissions_task (task_id),
  KEY idx_training_task_submissions_participant (participant_id),
  KEY idx_training_task_submissions_file (file_id),
  KEY idx_training_task_submissions_status (status),
  KEY idx_training_task_submissions_submitted_at (submitted_at),
  CONSTRAINT fk_training_task_submissions_campaign FOREIGN KEY (campaign_id) REFERENCES training_campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_task_submissions_sequence FOREIGN KEY (sequence_id) REFERENCES training_sequences(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_task_submissions_task FOREIGN KEY (task_id) REFERENCES training_tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_task_submissions_participant FOREIGN KEY (participant_id) REFERENCES training_participants(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_task_submissions_file FOREIGN KEY (file_id) REFERENCES training_files(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Reviews
-- ------------------------------------------------------------

CREATE TABLE training_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  submission_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  reviewer_user_id VARCHAR(120) NOT NULL,
  reviewer_role VARCHAR(80) NULL,
  decision ENUM('approved','rejected','resubmission_requested') NOT NULL,
  reviewer_note TEXT NULL,
  reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_reviews_public_id (public_id),
  KEY idx_training_reviews_submission (submission_id),
  KEY idx_training_reviews_campaign (campaign_id),
  KEY idx_training_reviews_participant (participant_id),
  KEY idx_training_reviews_decision (decision),
  KEY idx_training_reviews_reviewed_at (reviewed_at),
  CONSTRAINT fk_training_reviews_submission FOREIGN KEY (submission_id) REFERENCES training_task_submissions(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_reviews_campaign FOREIGN KEY (campaign_id) REFERENCES training_campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_reviews_participant FOREIGN KEY (participant_id) REFERENCES training_participants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Action Receipts
-- ------------------------------------------------------------

CREATE TABLE training_action_receipts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  receipt_type ENUM('task_completion','sequence_completion','milestone_completion','streak_completion','reward_eligibility') NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  sequence_id BIGINT UNSIGNED NULL,
  task_id BIGINT UNSIGNED NULL,
  submission_id BIGINT UNSIGNED NULL,
  review_id BIGINT UNSIGNED NULL,
  reward_rule_id BIGINT UNSIGNED NULL,
  points_awarded INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('created','verified','linked_to_reward','voided') NOT NULL DEFAULT 'created',
  receipt_payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_action_receipts_public_id (public_id),
  KEY idx_training_action_receipts_type (receipt_type),
  KEY idx_training_action_receipts_campaign (campaign_id),
  KEY idx_training_action_receipts_participant (participant_id),
  KEY idx_training_action_receipts_sequence (sequence_id),
  KEY idx_training_action_receipts_task (task_id),
  KEY idx_training_action_receipts_submission (submission_id),
  KEY idx_training_action_receipts_review (review_id),
  KEY idx_training_action_receipts_status (status),
  KEY idx_training_action_receipts_created_at (created_at),
  CONSTRAINT fk_training_action_receipts_campaign FOREIGN KEY (campaign_id) REFERENCES training_campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_action_receipts_participant FOREIGN KEY (participant_id) REFERENCES training_participants(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_action_receipts_sequence FOREIGN KEY (sequence_id) REFERENCES training_sequences(id) ON DELETE SET NULL,
  CONSTRAINT fk_training_action_receipts_task FOREIGN KEY (task_id) REFERENCES training_tasks(id) ON DELETE SET NULL,
  CONSTRAINT fk_training_action_receipts_submission FOREIGN KEY (submission_id) REFERENCES training_task_submissions(id) ON DELETE SET NULL,
  CONSTRAINT fk_training_action_receipts_review FOREIGN KEY (review_id) REFERENCES training_reviews(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Reward Rules
-- ------------------------------------------------------------

CREATE TABLE training_reward_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  trigger_type ENUM('task_completion','sequence_completion','milestone_completion','streak_completion','manual') NOT NULL DEFAULT 'sequence_completion',
  required_completions INT UNSIGNED NOT NULL DEFAULT 1,
  required_streak INT UNSIGNED NOT NULL DEFAULT 0,
  milestone_target INT UNSIGNED NOT NULL DEFAULT 0,
  reward_label VARCHAR(190) NOT NULL,
  reward_type ENUM('microgift','points','badge','manual') NOT NULL DEFAULT 'microgift',
  reward_value_cents INT UNSIGNED NOT NULL DEFAULT 0,
  budget_cap_cents INT UNSIGNED NULL,
  expires_after_days INT UNSIGNED NULL,
  linked_microgifter_program_id VARCHAR(160) NULL,
  linked_microgifter_template_id VARCHAR(160) NULL,
  status ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'active',
  sort_order INT UNSIGNED NOT NULL DEFAULT 1,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_reward_rules_public_id (public_id),
  KEY idx_training_reward_rules_campaign (campaign_id),
  KEY idx_training_reward_rules_trigger (trigger_type),
  KEY idx_training_reward_rules_status (status),
  CONSTRAINT fk_training_reward_rules_campaign FOREIGN KEY (campaign_id) REFERENCES training_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE training_action_receipts
  ADD CONSTRAINT fk_training_action_receipts_reward_rule FOREIGN KEY (reward_rule_id) REFERENCES training_reward_rules(id) ON DELETE SET NULL;

-- ------------------------------------------------------------
-- Reward Issues
-- ------------------------------------------------------------

CREATE TABLE training_reward_issues (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  receipt_id BIGINT UNSIGNED NOT NULL,
  reward_rule_id BIGINT UNSIGNED NOT NULL,
  linked_account_id VARCHAR(190) NULL,
  microgifter_reward_id VARCHAR(190) NULL,
  microgifter_claim_code VARCHAR(190) NULL,
  status ENUM('not_eligible','eligible','needs_linked_account','pending_issue','issued','failed','claimed','redeemed','expired') NOT NULL DEFAULT 'eligible',
  failure_reason TEXT NULL,
  provider_response_json JSON NULL,
  issued_at DATETIME NULL,
  claimed_at DATETIME NULL,
  redeemed_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_reward_issues_public_id (public_id),
  UNIQUE KEY uq_training_reward_issues_receipt_rule_participant (receipt_id, reward_rule_id, participant_id),
  KEY idx_training_reward_issues_campaign (campaign_id),
  KEY idx_training_reward_issues_participant (participant_id),
  KEY idx_training_reward_issues_receipt (receipt_id),
  KEY idx_training_reward_issues_rule (reward_rule_id),
  KEY idx_training_reward_issues_status (status),
  CONSTRAINT fk_training_reward_issues_campaign FOREIGN KEY (campaign_id) REFERENCES training_campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_reward_issues_participant FOREIGN KEY (participant_id) REFERENCES training_participants(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_reward_issues_receipt FOREIGN KEY (receipt_id) REFERENCES training_action_receipts(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_reward_issues_rule FOREIGN KEY (reward_rule_id) REFERENCES training_reward_rules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Streaks
-- ------------------------------------------------------------

CREATE TABLE training_streaks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  current_streak INT UNSIGNED NOT NULL DEFAULT 0,
  best_streak INT UNSIGNED NOT NULL DEFAULT 0,
  total_verified_sequences INT UNSIGNED NOT NULL DEFAULT 0,
  last_verified_at DATETIME NULL,
  status ENUM('active','reset','archived') NOT NULL DEFAULT 'active',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_streaks_public_id (public_id),
  UNIQUE KEY uq_training_streaks_campaign_participant (campaign_id, participant_id),
  KEY idx_training_streaks_campaign (campaign_id),
  KEY idx_training_streaks_participant (participant_id),
  KEY idx_training_streaks_status (status),
  CONSTRAINT fk_training_streaks_campaign FOREIGN KEY (campaign_id) REFERENCES training_campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_training_streaks_participant FOREIGN KEY (participant_id) REFERENCES training_participants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Events
-- ------------------------------------------------------------

CREATE TABLE training_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  event_type VARCHAR(120) NOT NULL,
  actor_user_id VARCHAR(120) NULL,
  actor_role VARCHAR(80) NULL,
  campaign_id BIGINT UNSIGNED NULL,
  participant_id BIGINT UNSIGNED NULL,
  target_type VARCHAR(120) NULL,
  target_id VARCHAR(190) NULL,
  status_before VARCHAR(80) NULL,
  status_after VARCHAR(80) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_events_public_id (public_id),
  KEY idx_training_events_type (event_type),
  KEY idx_training_events_actor (actor_user_id),
  KEY idx_training_events_campaign (campaign_id),
  KEY idx_training_events_participant (participant_id),
  KEY idx_training_events_created_at (created_at),
  CONSTRAINT fk_training_events_campaign FOREIGN KEY (campaign_id) REFERENCES training_campaigns(id) ON DELETE SET NULL,
  CONSTRAINT fk_training_events_participant FOREIGN KEY (participant_id) REFERENCES training_participants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
