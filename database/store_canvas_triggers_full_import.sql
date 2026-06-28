-- ------------------------------------------------------------
-- Store Canvas Triggers Full Import
-- ------------------------------------------------------------
-- Purpose:
--   Single-file import for Store Canvas trigger zones, trigger analytics
--   stamp action registration, and trigger automation settings.
--
-- Includes:
--   Stage 20B Store Canvas Trigger Zones
--   Stage 20C Store Canvas Trigger Analytics + Stamp Ledger Hook
--   Stage 20D Store Canvas Automation Rules
--
-- Safe to re-run:
--   This file uses CREATE TABLE IF NOT EXISTS and guarded column/index adds.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS schema_migrations (
  migration_key VARCHAR(190) NOT NULL,
  description VARCHAR(500) NULL,
  checksum VARCHAR(128) NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Stage 20B Store Canvas Trigger Zones
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS mg_store_trigger_zones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL DEFAULT 'IN/OUT Box Trigger',
  trigger_key VARCHAR(120) NOT NULL DEFAULT 'store_canvas_zone',
  campaign_public_id CHAR(36) NULL,
  priority TINYINT UNSIGNED NOT NULL DEFAULT 3,
  x_percent DECIMAL(7,4) NOT NULL DEFAULT 8.0000,
  y_percent DECIMAL(7,4) NOT NULL DEFAULT 8.0000,
  width_percent DECIMAL(7,4) NOT NULL DEFAULT 28.0000,
  height_percent DECIMAL(7,4) NOT NULL DEFAULT 18.0000,
  status ENUM('active','paused','archived') NOT NULL DEFAULT 'active',
  metadata_json JSON NULL,
  last_triggered_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_store_trigger_zones_public_id (public_id),
  KEY idx_mg_store_trigger_zones_merchant_status_priority (merchant_user_id,status,priority,updated_at),
  KEY idx_mg_store_trigger_zones_campaign (merchant_user_id,campaign_public_id,status),
  CONSTRAINT chk_mg_store_trigger_zones_priority CHECK (priority BETWEEN 1 AND 5),
  CONSTRAINT chk_mg_store_trigger_zones_x CHECK (x_percent >= 0 AND x_percent <= 100),
  CONSTRAINT chk_mg_store_trigger_zones_y CHECK (y_percent >= 0 AND y_percent <= 100),
  CONSTRAINT chk_mg_store_trigger_zones_width CHECK (width_percent > 0 AND width_percent <= 100),
  CONSTRAINT chk_mg_store_trigger_zones_height CHECK (height_percent > 0 AND height_percent <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_20b_store_canvas_trigger_zones','Persistent merchant Store Canvas trigger zones with campaign assignment and priority.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- ------------------------------------------------------------
-- Stage 20C Store Canvas Trigger Analytics + Stamp Ledger Hook
-- ------------------------------------------------------------
-- If the Stamp Ledger tables are not installed yet, this import skips the
-- stamp action insert instead of failing. The Store Canvas automation endpoint
-- already falls back when Stamp Ledger tables are unavailable.
-- ------------------------------------------------------------

SET @mg20c_stamp_table := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='stamp_debit_actions');
SET @mg20c_sql := IF(
  @mg20c_stamp_table > 0,
  "INSERT IGNORE INTO stamp_debit_actions (public_id,action_key,label,channel,scope,stamp_value,description,status,created_at,updated_at) VALUES (UUID(),'store_canvas_auto_message_send','Store Canvas automated message','Store Canvas','Automation',1,'Automated merchant message sent when a customer avatar crosses a Store Canvas trigger zone.','active',NOW(),NOW())",
  "SELECT 1"
);
PREPARE mg20c_stmt FROM @mg20c_sql; EXECUTE mg20c_stmt; DEALLOCATE PREPARE mg20c_stmt;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_20c_store_canvas_trigger_analytics_stamps','Store Canvas trigger analytics stamp debit action for automated trigger messaging.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- ------------------------------------------------------------
-- Stage 20D Store Canvas Automation Rules
-- ------------------------------------------------------------

SET @mg20d_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_zones' AND COLUMN_NAME='automation_action');
SET @mg20d_sql := IF(@mg20d_col=0, "ALTER TABLE mg_store_trigger_zones ADD COLUMN automation_action ENUM('message_and_reward','message_only','reward_only','notify_only','follow_up','crm_segment','analytics_only') NOT NULL DEFAULT 'message_and_reward' AFTER campaign_public_id", "SELECT 1");
PREPARE mg20d_stmt FROM @mg20d_sql; EXECUTE mg20d_stmt; DEALLOCATE PREPARE mg20d_stmt;

SET @mg20d_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_zones' AND COLUMN_NAME='cooldown_policy');
SET @mg20d_sql := IF(@mg20d_col=0, "ALTER TABLE mg_store_trigger_zones ADD COLUMN cooldown_policy ENUM('five_minutes','fifteen_minutes','one_hour','once_per_visit','once_per_customer_day') NOT NULL DEFAULT 'fifteen_minutes' AFTER automation_action", "SELECT 1");
PREPARE mg20d_stmt FROM @mg20d_sql; EXECUTE mg20d_stmt; DEALLOCATE PREPARE mg20d_stmt;

SET @mg20d_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_zones' AND COLUMN_NAME='cooldown_seconds');
SET @mg20d_sql := IF(@mg20d_col=0, "ALTER TABLE mg_store_trigger_zones ADD COLUMN cooldown_seconds INT UNSIGNED NOT NULL DEFAULT 900 AFTER cooldown_policy", "SELECT 1");
PREPARE mg20d_stmt FROM @mg20d_sql; EXECUTE mg20d_stmt; DEALLOCATE PREPARE mg20d_stmt;

SET @mg20d_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_zones' AND COLUMN_NAME='auto_message_text');
SET @mg20d_sql := IF(@mg20d_col=0, "ALTER TABLE mg_store_trigger_zones ADD COLUMN auto_message_text VARCHAR(1000) NULL AFTER cooldown_seconds", "SELECT 1");
PREPARE mg20d_stmt FROM @mg20d_sql; EXECUTE mg20d_stmt; DEALLOCATE PREPARE mg20d_stmt;

SET @mg20d_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_zones' AND COLUMN_NAME='fallback_action');
SET @mg20d_sql := IF(@mg20d_col=0, "ALTER TABLE mg_store_trigger_zones ADD COLUMN fallback_action ENUM('notify_only','analytics_only','skip') NOT NULL DEFAULT 'notify_only' AFTER auto_message_text", "SELECT 1");
PREPARE mg20d_stmt FROM @mg20d_sql; EXECUTE mg20d_stmt; DEALLOCATE PREPARE mg20d_stmt;

SET @mg20d_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_zones' AND COLUMN_NAME='crm_segment_name');
SET @mg20d_sql := IF(@mg20d_col=0, "ALTER TABLE mg_store_trigger_zones ADD COLUMN crm_segment_name VARCHAR(160) NULL AFTER fallback_action", "SELECT 1");
PREPARE mg20d_stmt FROM @mg20d_sql; EXECUTE mg20d_stmt; DEALLOCATE PREPARE mg20d_stmt;

SET @mg20d_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_zones' AND COLUMN_NAME='notify_merchant');
SET @mg20d_sql := IF(@mg20d_col=0, "ALTER TABLE mg_store_trigger_zones ADD COLUMN notify_merchant TINYINT(1) NOT NULL DEFAULT 1 AFTER crm_segment_name", "SELECT 1");
PREPARE mg20d_stmt FROM @mg20d_sql; EXECUTE mg20d_stmt; DEALLOCATE PREPARE mg20d_stmt;

SET @mg20d_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_zones' AND INDEX_NAME='idx_mg_store_trigger_zones_action_status');
SET @mg20d_sql := IF(@mg20d_idx=0, "CREATE INDEX idx_mg_store_trigger_zones_action_status ON mg_store_trigger_zones (merchant_user_id,status,automation_action,priority)", "SELECT 1");
PREPARE mg20d_stmt FROM @mg20d_sql; EXECUTE mg20d_stmt; DEALLOCATE PREPARE mg20d_stmt;

SET @mg20d_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_zones' AND INDEX_NAME='idx_mg_store_trigger_zones_cooldown');
SET @mg20d_sql := IF(@mg20d_idx=0, "CREATE INDEX idx_mg_store_trigger_zones_cooldown ON mg_store_trigger_zones (merchant_user_id,status,cooldown_policy,last_triggered_at)", "SELECT 1");
PREPARE mg20d_stmt FROM @mg20d_sql; EXECUTE mg20d_stmt; DEALLOCATE PREPARE mg20d_stmt;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_20d_store_canvas_automation_rules','Per-zone Store Canvas automation actions, cooldowns, Stamp fallback behavior, and CRM metadata.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- ------------------------------------------------------------
-- Verification helpers
-- ------------------------------------------------------------

SELECT 'store_canvas_triggers_full_import_complete' AS import_status;
SELECT COUNT(*) AS mg_store_trigger_zones_table_exists
FROM information_schema.TABLES
WHERE TABLE_SCHEMA=DATABASE()
  AND TABLE_NAME='mg_store_trigger_zones';
