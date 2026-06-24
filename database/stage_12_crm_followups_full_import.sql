-- Stage 12 CRM + follow-up + message delivery combined import
-- Manual one-file import for phpMyAdmin or direct mysql import.
-- Prefer `php scripts/run_migrations.php` for normal deployments.
-- Do not manually import this file and then also manually import the three individual files.

CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  migration_key VARCHAR(190) NOT NULL,
  description VARCHAR(255) NULL,
  checksum CHAR(64) NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_schema_migrations_key (migration_key),
  INDEX idx_schema_migrations_applied_at (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 1. Merchant CRM foundation
-- Source: database/stage_12_merchant_crm.sql
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS merchant_crm_contacts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  primary_email VARCHAR(255) NULL,
  primary_phone VARCHAR(80) NULL,
  display_name VARCHAR(180) NULL,
  lifecycle_stage ENUM('lead','follower','prospect','customer','supporter','redeemer','inactive','custom') NOT NULL DEFAULT 'lead',
  crm_status ENUM('active','archived','blocked') NOT NULL DEFAULT 'active',
  last_campaign_type VARCHAR(80) NOT NULL DEFAULT 'unknown',
  last_source_type VARCHAR(80) NOT NULL DEFAULT 'unknown',
  first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_engaged_at DATETIME NULL,
  last_followed_at DATETIME NULL,
  last_purchased_at DATETIME NULL,
  last_reward_issued_at DATETIME NULL,
  last_reward_claimed_at DATETIME NULL,
  last_reward_redeemed_at DATETIME NULL,
  total_purchase_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_rewards_issued INT UNSIGNED NOT NULL DEFAULT 0,
  total_rewards_claimed INT UNSIGNED NOT NULL DEFAULT 0,
  total_rewards_redeemed INT UNSIGNED NOT NULL DEFAULT 0,
  source_summary_json JSON NULL,
  tags_json JSON NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_crm_contacts_public_id (public_id),
  UNIQUE KEY uq_merchant_crm_contacts_email (merchant_user_id, primary_email),
  UNIQUE KEY uq_merchant_crm_contacts_user (merchant_user_id, user_id),
  KEY idx_merchant_crm_contacts_merchant_updated (merchant_user_id, updated_at, id),
  KEY idx_merchant_crm_contacts_stage (merchant_user_id, lifecycle_stage, updated_at),
  KEY idx_merchant_crm_contacts_campaign_type (merchant_user_id, last_campaign_type, updated_at),
  CONSTRAINT fk_merchant_crm_contacts_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_crm_contacts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_crm_contact_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  crm_contact_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NULL,
  campaign_type VARCHAR(80) NOT NULL,
  event_type VARCHAR(90) NOT NULL,
  source_type VARCHAR(80) NOT NULL,
  source_public_id VARCHAR(80) NULL,
  user_id BIGINT UNSIGNED NULL,
  email VARCHAR(255) NULL,
  phone VARCHAR(80) NULL,
  name VARCHAR(180) NULL,
  value_cents BIGINT UNSIGNED NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_crm_contact_events_public_id (public_id),
  KEY idx_merchant_crm_events_contact_created (crm_contact_id, created_at, id),
  KEY idx_merchant_crm_events_merchant_created (merchant_user_id, created_at, id),
  KEY idx_merchant_crm_events_campaign (merchant_user_id, campaign_id, campaign_type, created_at),
  KEY idx_merchant_crm_events_type (merchant_user_id, event_type, created_at),
  CONSTRAINT fk_merchant_crm_events_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_crm_events_contact FOREIGN KEY (crm_contact_id) REFERENCES merchant_crm_contacts(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_crm_events_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_crm_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_crm_contact_campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  crm_contact_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  campaign_type VARCHAR(80) NOT NULL,
  first_event_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_event_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  event_count INT UNSIGNED NOT NULL DEFAULT 1,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_crm_contact_campaign (crm_contact_id, campaign_id, campaign_type),
  UNIQUE KEY uq_merchant_crm_contact_campaign_public_id (public_id),
  KEY idx_merchant_crm_contact_campaigns_merchant (merchant_user_id, campaign_type, updated_at),
  CONSTRAINT fk_merchant_crm_contact_campaigns_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_crm_contact_campaigns_contact FOREIGN KEY (crm_contact_id) REFERENCES merchant_crm_contacts(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_crm_contact_campaigns_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_crm_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  crm_contact_id BIGINT UNSIGNED NOT NULL,
  author_user_id BIGINT UNSIGNED NOT NULL,
  note TEXT NOT NULL,
  visibility ENUM('merchant_internal','team') NOT NULL DEFAULT 'merchant_internal',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_crm_notes_public_id (public_id),
  KEY idx_merchant_crm_notes_contact_created (crm_contact_id, created_at),
  CONSTRAINT fk_merchant_crm_notes_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_crm_notes_contact FOREIGN KEY (crm_contact_id) REFERENCES merchant_crm_contacts(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_crm_notes_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_12_merchant_crm','Merchant-owned CRM contacts, events, campaign history, and notes.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- -----------------------------------------------------------------------------
-- 2. Campaign follow-up automation
-- Source: database/stage_12_campaign_followups.sql
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS campaign_followup_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  trigger_event VARCHAR(90) NOT NULL,
  delay_preset ENUM('1_hour','6_hours','1_day','15_days','custom') NOT NULL DEFAULT '1_hour',
  custom_delay_minutes INT UNSIGNED NULL,
  delay_seconds INT UNSIGNED NOT NULL DEFAULT 3600,
  channel ENUM('email','microgifter_message','both') NOT NULL DEFAULT 'email',
  message_mode ENUM('automatic','custom') NOT NULL DEFAULT 'automatic',
  subject VARCHAR(180) NULL,
  body TEXT NULL,
  status ENUM('active','paused','archived') NOT NULL DEFAULT 'active',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campaign_followup_rules_public_id (public_id),
  KEY idx_campaign_followup_rules_campaign (campaign_id,status,trigger_event),
  CONSTRAINT fk_campaign_followup_rules_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_campaign_followup_rules_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_followup_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  dedupe_key VARCHAR(190) NOT NULL,
  rule_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  contact_id BIGINT UNSIGNED NULL,
  wallet_item_id BIGINT UNSIGNED NULL,
  trigger_event VARCHAR(90) NOT NULL,
  status ENUM('queued','processing','sent','skipped','failed','cancelled') NOT NULL DEFAULT 'queued',
  due_at DATETIME NOT NULL,
  payload_json JSON NULL,
  attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(500) NULL,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campaign_followup_jobs_public_id (public_id),
  UNIQUE KEY uq_campaign_followup_jobs_dedupe (dedupe_key),
  KEY idx_campaign_followup_jobs_due (status,due_at),
  KEY idx_campaign_followup_jobs_campaign (campaign_id,status,due_at),
  CONSTRAINT fk_campaign_followup_jobs_rule FOREIGN KEY (rule_id) REFERENCES campaign_followup_rules(id) ON DELETE CASCADE,
  CONSTRAINT fk_campaign_followup_jobs_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_campaign_followup_jobs_contact FOREIGN KEY (contact_id) REFERENCES campaign_contacts(id) ON DELETE SET NULL,
  CONSTRAINT fk_campaign_followup_jobs_wallet FOREIGN KEY (wallet_item_id) REFERENCES wallet_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_12_campaign_followups','Campaign follow-up rules and scheduled follow-up jobs.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- -----------------------------------------------------------------------------
-- 3. Message delivery and campaign suppression
-- Source: database/stage_12_message_delivery_campaign_suppression.sql
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS message_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  event_key VARCHAR(190) NOT NULL,
  event_fingerprint CHAR(64) NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  category VARCHAR(60) NOT NULL DEFAULT 'transactional',
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_message_events_public_id (public_id),
  UNIQUE KEY uq_message_events_key (event_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_delivery_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  message_event_id BIGINT UNSIGNED NOT NULL,
  recipient_user_id BIGINT UNSIGNED NULL,
  channel ENUM('in_app','email','sms','webhook') NOT NULL,
  template_key VARCHAR(120) NOT NULL,
  status ENUM('queued','processing','retrying','delivered','failed','dead_letter','suppressed') NOT NULL DEFAULT 'queued',
  attempt_count INT NOT NULL DEFAULT 0,
  max_attempts INT NOT NULL DEFAULT 3,
  next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  provider_message_id VARCHAR(190) NULL,
  last_error VARCHAR(500) NULL,
  recipient_snapshot_json JSON NULL,
  payload_snapshot_json JSON NULL,
  delivered_at DATETIME NULL,
  failed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_message_delivery_jobs_public_id (public_id),
  UNIQUE KEY uq_message_delivery_jobs_event_channel (message_event_id,channel,recipient_user_id),
  KEY idx_message_delivery_jobs_claim (status,next_attempt_at,id),
  CONSTRAINT fk_message_delivery_jobs_event FOREIGN KEY (message_event_id) REFERENCES message_events(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_delivery_jobs_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_delivery_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  job_id BIGINT UNSIGNED NOT NULL,
  attempt_no INT NOT NULL,
  provider_key VARCHAR(80) NOT NULL,
  status ENUM('success','transient_failure','permanent_failure') NOT NULL,
  error_code VARCHAR(100) NULL,
  error_message VARCHAR(500) NULL,
  provider_response_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_message_delivery_attempts_public_id (public_id),
  UNIQUE KEY uq_message_delivery_attempts_job_attempt (job_id,attempt_no),
  CONSTRAINT fk_message_delivery_attempts_job FOREIGN KEY (job_id) REFERENCES message_delivery_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_provider_callbacks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  provider_key VARCHAR(80) NOT NULL,
  provider_event_id VARCHAR(190) NOT NULL,
  job_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  payload_hash CHAR(64) NOT NULL,
  payload_json JSON NULL,
  status ENUM('processed','ignored') NOT NULL DEFAULT 'processed',
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_message_provider_callbacks_public_id (public_id),
  UNIQUE KEY uq_message_provider_callbacks_event (provider_key,provider_event_id),
  CONSTRAINT fk_message_provider_callbacks_job FOREIGN KEY (job_id) REFERENCES message_delivery_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_suppression_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  channel ENUM('in_app','email','sms','webhook') NOT NULL,
  category VARCHAR(60) NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_message_suppression (user_id,channel,category),
  CONSTRAINT fk_message_suppression_rules_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_email_suppressions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NULL,
  email_hash CHAR(64) NOT NULL,
  scope ENUM('campaign','merchant') NOT NULL DEFAULT 'campaign',
  reason VARCHAR(120) NOT NULL DEFAULT 'unsubscribe',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campaign_email_suppressions_public_id (public_id),
  UNIQUE KEY uq_campaign_email_suppressions_scope (merchant_user_id,campaign_id,email_hash,scope),
  KEY idx_campaign_email_suppressions_lookup (merchant_user_id,email_hash,status),
  CONSTRAINT fk_campaign_email_suppressions_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_campaign_email_suppressions_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_12_message_delivery_campaign_suppression','Message delivery jobs, attempts, callbacks, notification suppression, and campaign email suppression.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
