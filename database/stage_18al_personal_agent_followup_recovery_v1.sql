-- Personal Agent Follow-Up, Saved Opportunity Recovery, and Conversion Automation v1
-- Safe to re-run.

CREATE TABLE IF NOT EXISTS personal_agent_recovery_preferences (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  saved_followups_enabled TINYINT(1) NOT NULL DEFAULT 1,
  cart_recovery_enabled TINYINT(1) NOT NULL DEFAULT 1,
  campaign_expiry_enabled TINYINT(1) NOT NULL DEFAULT 1,
  unavailable_alternative_enabled TINYINT(1) NOT NULL DEFAULT 1,
  max_notifications_per_week SMALLINT UNSIGNED NOT NULL DEFAULT 3,
  cooldown_hours SMALLINT UNSIGNED NOT NULL DEFAULT 48,
  default_snooze_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
  timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
  quiet_hours_start TIME NULL,
  quiet_hours_end TIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_personal_agent_recovery_preference_public (public_id),
  UNIQUE KEY uq_personal_agent_recovery_preference_user (user_id),
  CONSTRAINT fk_personal_agent_recovery_preference_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS personal_agent_opportunity_followups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  opportunity_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  trigger_type VARCHAR(50) NOT NULL,
  status ENUM('scheduled','due','delivered','snoozed','dismissed','muted','converted','cancelled','failed') NOT NULL DEFAULT 'scheduled',
  scheduled_for DATETIME NOT NULL,
  delivered_at DATETIME NULL,
  snoozed_until DATETIME NULL,
  dismissed_at DATETIME NULL,
  muted_at DATETIME NULL,
  converted_at DATETIME NULL,
  notification_public_id CHAR(36) NULL,
  attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  idempotency_key VARCHAR(190) NOT NULL,
  metadata_json JSON NULL,
  last_error VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_personal_agent_followup_public (public_id),
  UNIQUE KEY uq_personal_agent_followup_idempotency (idempotency_key),
  KEY idx_personal_agent_followup_due (status,scheduled_for),
  KEY idx_personal_agent_followup_user (user_id,status,scheduled_for),
  KEY idx_personal_agent_followup_opportunity (opportunity_id,status,created_at),
  KEY idx_personal_agent_followup_merchant (merchant_user_id,status,created_at),
  CONSTRAINT fk_personal_agent_followup_opportunity FOREIGN KEY (opportunity_id) REFERENCES personal_agent_opportunities(id) ON DELETE CASCADE,
  CONSTRAINT fk_personal_agent_followup_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_personal_agent_followup_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_18al_personal_agent_followup_recovery_v1','Customer-controlled Personal Agent follow-ups, saved opportunity recovery, abandoned cart and checkout automation, campaign expiry notices, conversion recovery, and aggregate merchant reporting.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);