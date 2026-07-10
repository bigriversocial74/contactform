-- Trigger Event Ingestion & Customer Interaction Orchestration v1
--
-- Extends the existing Store Canvas trigger engine with durable source checkpoints,
-- queue/retry/dead-letter controls, orchestration policies, scheduler run history,
-- emergency pause, and campaign-open/participation event families.
--
-- Existing campaigns, reward_templates, campaign_events, wallet_items, Store Canvas
-- sessions, notifications, action receipts, Wallet, Inbox, and PPPM remain authoritative.
-- Safe to re-run. No browser avatar position or visual overlap is event authority.

CREATE TABLE IF NOT EXISTS schema_migrations (
  migration_key VARCHAR(190) NOT NULL,
  description VARCHAR(500) NULL,
  checksum VARCHAR(128) NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_store_trigger_ingestion_checkpoints (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  source_key VARCHAR(80) NOT NULL,
  cursor_json JSON NULL,
  health_status ENUM('never','healthy','warning','failed','paused') NOT NULL DEFAULT 'never',
  last_scan_at DATETIME NULL,
  last_success_at DATETIME NULL,
  last_error_code VARCHAR(120) NULL,
  last_error_message VARCHAR(500) NULL,
  ingested_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  skipped_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  last_run_public_id CHAR(36) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_trigger_ingestion_checkpoint_public (public_id),
  UNIQUE KEY uq_mg_trigger_ingestion_checkpoint_source (merchant_user_id,source_key),
  KEY idx_mg_trigger_ingestion_checkpoint_health (merchant_user_id,health_status,updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_store_trigger_orchestration_policies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  name VARCHAR(180) NOT NULL,
  status ENUM('enabled','paused','archived') NOT NULL DEFAULT 'paused',
  delay_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  retry_delay_seconds INT UNSIGNED NOT NULL DEFAULT 900,
  max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 12,
  quiet_hours_start TIME NULL,
  quiet_hours_end TIME NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
  require_active_session TINYINT(1) NOT NULL DEFAULT 1,
  greeting_mode ENUM('none','first_visit','returning','contextual') NOT NULL DEFAULT 'contextual',
  follow_up_mode ENUM('none','campaign_only','claim_aware','redemption_aware') NOT NULL DEFAULT 'campaign_only',
  release_after_seconds INT UNSIGNED NOT NULL DEFAULT 86400,
  suppress_after_claim_seconds INT UNSIGNED NOT NULL DEFAULT 86400,
  suppress_after_redeem_seconds INT UNSIGNED NOT NULL DEFAULT 604800,
  message_variants_json JSON NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_trigger_orchestration_policy_public (public_id),
  UNIQUE KEY uq_mg_trigger_orchestration_policy_type (merchant_user_id,event_type),
  KEY idx_mg_trigger_orchestration_policy_status (merchant_user_id,status,updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_store_trigger_scheduler_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  run_type ENUM('ingestion','orchestration','full') NOT NULL DEFAULT 'full',
  execution_mode ENUM('paused','dry_run','notification') NOT NULL DEFAULT 'paused',
  status ENUM('running','completed','partial','failed','paused') NOT NULL DEFAULT 'running',
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  sources_scanned SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  records_scanned INT UNSIGNED NOT NULL DEFAULT 0,
  events_queued INT UNSIGNED NOT NULL DEFAULT 0,
  events_evaluated INT UNSIGNED NOT NULL DEFAULT 0,
  notifications_delivered INT UNSIGNED NOT NULL DEFAULT 0,
  events_blocked INT UNSIGNED NOT NULL DEFAULT 0,
  events_retried INT UNSIGNED NOT NULL DEFAULT 0,
  events_dead_lettered INT UNSIGNED NOT NULL DEFAULT 0,
  error_count INT UNSIGNED NOT NULL DEFAULT 0,
  summary_json JSON NULL,
  error_message VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_trigger_scheduler_run_public (public_id),
  KEY idx_mg_trigger_scheduler_run_merchant (merchant_user_id,started_at,id),
  KEY idx_mg_trigger_scheduler_run_status (status,started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_store_trigger_dead_letters (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  trigger_event_id BIGINT UNSIGNED NULL,
  event_public_id CHAR(36) NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  source_type VARCHAR(80) NOT NULL,
  source_public_id VARCHAR(190) NULL,
  customer_user_id BIGINT UNSIGNED NULL,
  reason_code VARCHAR(120) NOT NULL,
  reason_text VARCHAR(1000) NOT NULL,
  attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  payload_json JSON NULL,
  status ENUM('open','requeued','resolved','ignored') NOT NULL DEFAULT 'open',
  requeued_event_id BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_trigger_dead_letter_public (public_id),
  UNIQUE KEY uq_mg_trigger_dead_letter_event (merchant_user_id,event_public_id),
  KEY idx_mg_trigger_dead_letter_status (merchant_user_id,status,created_at),
  KEY idx_mg_trigger_dead_letter_customer (merchant_user_id,customer_user_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Engine-level ingestion/orchestration controls.
SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_engine_settings' AND COLUMN_NAME='emergency_pause');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_engine_settings ADD COLUMN emergency_pause TINYINT(1) NOT NULL DEFAULT 0 AFTER execution_mode", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_engine_settings' AND COLUMN_NAME='ingestion_enabled');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_engine_settings ADD COLUMN ingestion_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER emergency_pause", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_engine_settings' AND COLUMN_NAME='scheduler_enabled');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_engine_settings ADD COLUMN scheduler_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER ingestion_enabled", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_engine_settings' AND COLUMN_NAME='orchestration_timezone');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_engine_settings ADD COLUMN orchestration_timezone VARCHAR(64) NOT NULL DEFAULT 'UTC' AFTER default_cooldown_seconds", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_engine_settings' AND COLUMN_NAME='quiet_hours_start');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_engine_settings ADD COLUMN quiet_hours_start TIME NULL AFTER orchestration_timezone", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_engine_settings' AND COLUMN_NAME='quiet_hours_end');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_engine_settings ADD COLUMN quiet_hours_end TIME NULL AFTER quiet_hours_start", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_engine_settings' AND COLUMN_NAME='last_ingestion_at');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_engine_settings ADD COLUMN last_ingestion_at DATETIME NULL AFTER last_run_summary_json", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_engine_settings' AND COLUMN_NAME='last_ingestion_status');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_engine_settings ADD COLUMN last_ingestion_status ENUM('never','running','completed','partial','failed','paused') NOT NULL DEFAULT 'never' AFTER last_ingestion_at", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_engine_settings' AND COLUMN_NAME='last_ingestion_summary_json');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_engine_settings ADD COLUMN last_ingestion_summary_json JSON NULL AFTER last_ingestion_status", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_engine_settings' AND COLUMN_NAME='last_scheduler_heartbeat_at');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_engine_settings ADD COLUMN last_scheduler_heartbeat_at DATETIME NULL AFTER last_ingestion_summary_json", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

-- Add campaign open/participation event families to existing rules and queue.
ALTER TABLE mg_store_trigger_engine_rules
  MODIFY COLUMN event_type ENUM('store_entry','return_visit','visit_milestone','campaign_interest','inactivity_risk','product_interest','campaign_opened','campaign_participated','reward_claimed','reward_redeemed') NOT NULL;

ALTER TABLE mg_store_trigger_events
  MODIFY COLUMN event_type ENUM('store_entry','return_visit','visit_milestone','campaign_interest','inactivity_risk','product_interest','campaign_opened','campaign_participated','reward_claimed','reward_redeemed') NOT NULL,
  MODIFY COLUMN status ENUM('pending','processing','retry','evaluated','ignored','dead_letter','error') NOT NULL DEFAULT 'pending';

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_engine_rules' AND COLUMN_NAME='orchestration_policy_public_id');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_engine_rules ADD COLUMN orchestration_policy_public_id CHAR(36) NULL AFTER trigger_zone_public_id", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_events' AND COLUMN_NAME='source_record_id');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_events ADD COLUMN source_record_id BIGINT UNSIGNED NULL AFTER source_public_id", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_events' AND COLUMN_NAME='orchestration_policy_public_id');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_events ADD COLUMN orchestration_policy_public_id CHAR(36) NULL AFTER event_type", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_events' AND COLUMN_NAME='attempt_count');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_events ADD COLUMN attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER status", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_events' AND COLUMN_NAME='max_attempts');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_events ADD COLUMN max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 12 AFTER attempt_count", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_events' AND COLUMN_NAME='available_at');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_events ADD COLUMN available_at DATETIME NULL AFTER max_attempts", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;
UPDATE mg_store_trigger_events SET available_at=COALESCE(available_at,created_at,NOW()) WHERE available_at IS NULL;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_events' AND COLUMN_NAME='locked_at');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_events ADD COLUMN locked_at DATETIME NULL AFTER available_at", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_events' AND COLUMN_NAME='locked_by');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_events ADD COLUMN locked_by VARCHAR(120) NULL AFTER locked_at", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_events' AND COLUMN_NAME='processed_at');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_events ADD COLUMN processed_at DATETIME NULL AFTER locked_by", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_events' AND COLUMN_NAME='last_error_code');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_events ADD COLUMN last_error_code VARCHAR(120) NULL AFTER processed_at", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_events' AND COLUMN_NAME='last_error_message');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_events ADD COLUMN last_error_message VARCHAR(1000) NULL AFTER last_error_code", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_events' AND COLUMN_NAME='dead_lettered_at');
SET @mgio_sql := IF(@mgio_col=0, "ALTER TABLE mg_store_trigger_events ADD COLUMN dead_lettered_at DATETIME NULL AFTER last_error_message", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

-- Dry-run and live notification evaluations must be independently auditable.
SET @mgio_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_evaluations' AND INDEX_NAME='uq_mg_store_trigger_evaluations_event_rule');
SET @mgio_sql := IF(@mgio_idx>0, "ALTER TABLE mg_store_trigger_evaluations DROP INDEX uq_mg_store_trigger_evaluations_event_rule", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_evaluations' AND INDEX_NAME='uq_mg_store_trigger_evaluations_event_rule_mode');
SET @mgio_sql := IF(@mgio_idx=0, "CREATE UNIQUE INDEX uq_mg_store_trigger_evaluations_event_rule_mode ON mg_store_trigger_evaluations (event_id,rule_id,execution_mode)", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_events' AND INDEX_NAME='idx_mg_store_trigger_events_queue');
SET @mgio_sql := IF(@mgio_idx=0, "CREATE INDEX idx_mg_store_trigger_events_queue ON mg_store_trigger_events (merchant_user_id,status,available_at,event_at,id)", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

SET @mgio_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mg_store_trigger_events' AND INDEX_NAME='idx_mg_store_trigger_events_source_record');
SET @mgio_sql := IF(@mgio_idx=0, "CREATE INDEX idx_mg_store_trigger_events_source_record ON mg_store_trigger_events (merchant_user_id,source_type,source_record_id)", "SELECT 1");
PREPARE mgio_stmt FROM @mgio_sql; EXECUTE mgio_stmt; DEALLOCATE PREPARE mgio_stmt;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'trigger_event_ingestion_orchestration_v1',
  'Canonical trigger-event ingestion, queue retry/dead-letter controls, orchestration policies, scheduler health, and emergency pause.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

SELECT 'trigger_event_ingestion_orchestration_v1_complete' AS import_status;
SELECT DATABASE() AS active_database;
SHOW TABLES LIKE 'mg_store_trigger_ingestion_checkpoints';
SHOW TABLES LIKE 'mg_store_trigger_orchestration_policies';
SHOW TABLES LIKE 'mg_store_trigger_scheduler_runs';
SHOW TABLES LIKE 'mg_store_trigger_dead_letters';
