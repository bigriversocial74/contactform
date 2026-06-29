-- ------------------------------------------------------------
-- Stage 20E Store Canvas Trigger Deliveries
-- ------------------------------------------------------------
-- Purpose:
--   Adds a dedicated delivery ledger for Store Canvas trigger-zone rules.
--   The automation endpoint uses this table as the source of truth for
--   cooldowns, duplicate prevention, delivery status, and action results.
--
-- Notes:
--   This migration is defensive and safe to rerun. It does not replace
--   mg_store_session_events; session events remain the activity timeline.
--   This table is the rule/delivery ledger used by automation logic.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS schema_migrations (
  migration_key VARCHAR(190) NOT NULL,
  description VARCHAR(500) NULL,
  checksum VARCHAR(128) NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_store_trigger_deliveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  customer_user_id BIGINT UNSIGNED NOT NULL,
  store_session_id BIGINT UNSIGNED NOT NULL,
  store_session_public_id CHAR(36) NOT NULL,
  trigger_zone_id BIGINT UNSIGNED NULL,
  trigger_zone_public_id CHAR(36) NOT NULL,
  trigger_key VARCHAR(120) NOT NULL DEFAULT 'store_canvas_zone',
  trigger_label VARCHAR(180) NULL,
  trigger_priority TINYINT UNSIGNED NOT NULL DEFAULT 3,
  campaign_public_id CHAR(36) NULL,
  campaign_title VARCHAR(255) NULL,
  reward_template_id VARCHAR(190) NULL,
  automation_action ENUM('message_and_reward','message_only','reward_only','notify_only','follow_up','crm_segment','analytics_only') NOT NULL DEFAULT 'message_and_reward',
  cooldown_policy ENUM('five_minutes','fifteen_minutes','one_hour','once_per_visit','once_per_customer_day') NOT NULL DEFAULT 'fifteen_minutes',
  cooldown_seconds INT UNSIGNED NOT NULL DEFAULT 900,
  cooldown_until DATETIME NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  status ENUM('pending','fired','cooldown','skipped','failed') NOT NULL DEFAULT 'pending',
  fallback_applied TINYINT(1) NOT NULL DEFAULT 0,
  skipped_reason VARCHAR(120) NULL,
  notify_merchant TINYINT(1) NOT NULL DEFAULT 0,
  follow_up_created TINYINT(1) NOT NULL DEFAULT 0,
  crm_segment_added TINYINT(1) NOT NULL DEFAULT 0,
  crm_segment_name VARCHAR(160) NULL,
  analytics_only TINYINT(1) NOT NULL DEFAULT 0,
  message_sent TINYINT(1) NOT NULL DEFAULT 0,
  reward_sent TINYINT(1) NOT NULL DEFAULT 0,
  stamp_debited TINYINT(1) NOT NULL DEFAULT 0,
  stamp_ledger_entry_id VARCHAR(190) NULL,
  stamp_action_key VARCHAR(120) NULL,
  stamp_debit_error VARCHAR(255) NULL,
  message_id VARCHAR(190) NULL,
  wallet_item_id VARCHAR(190) NULL,
  message_error VARCHAR(255) NULL,
  reward_error VARCHAR(255) NULL,
  metadata_json JSON NULL,
  fired_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_store_trigger_deliveries_public_id (public_id),
  UNIQUE KEY uq_mg_store_trigger_deliveries_idempotency (idempotency_key),
  KEY idx_mg_store_trigger_deliveries_active_cooldown (merchant_user_id,customer_user_id,trigger_zone_public_id,cooldown_until),
  KEY idx_mg_store_trigger_deliveries_session_zone (store_session_id,trigger_zone_public_id,status,created_at),
  KEY idx_mg_store_trigger_deliveries_campaign (merchant_user_id,campaign_public_id,status,created_at),
  KEY idx_mg_store_trigger_deliveries_status (merchant_user_id,status,created_at),
  KEY idx_mg_store_trigger_deliveries_zone_created (merchant_user_id,trigger_zone_public_id,created_at),
  CONSTRAINT chk_mg_store_trigger_deliveries_priority CHECK (trigger_priority BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @mg20e_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_deliveries' AND INDEX_NAME='idx_mg_store_trigger_deliveries_daily_customer');
SET @mg20e_sql := IF(@mg20e_idx=0, "CREATE INDEX idx_mg_store_trigger_deliveries_daily_customer ON mg_store_trigger_deliveries (merchant_user_id,customer_user_id,trigger_zone_public_id,fired_at)", "SELECT 1");
PREPARE mg20e_stmt FROM @mg20e_sql; EXECUTE mg20e_stmt; DEALLOCATE PREPARE mg20e_stmt;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_20e_store_canvas_trigger_deliveries','Dedicated Store Canvas trigger delivery ledger for cooldowns, duplicate prevention, and automation action results.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
