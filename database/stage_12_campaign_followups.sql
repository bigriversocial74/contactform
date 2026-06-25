-- Stage 12 campaign follow-up automation
-- Creates merchant-managed follow-up rules and scheduled follow-up jobs.

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
