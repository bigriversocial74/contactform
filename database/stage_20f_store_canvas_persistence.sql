-- ------------------------------------------------------------
-- Stage 20F Store Canvas Intelligence Persistence
-- ------------------------------------------------------------
-- Adds durable persistence for Store Canvas intelligence features:
-- - Merchant canvas mode/settings
-- - Rule simulator runs
-- - Optional customer journey snapshots
--
-- Safe to re-run.
-- phpMyAdmin note: select the Microgifter application database first.
-- Do NOT run this while information_schema is selected.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS schema_migrations (
  migration_key VARCHAR(190) NOT NULL,
  description VARCHAR(500) NULL,
  checksum VARCHAR(128) NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_store_canvas_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  canvas_mode ENUM('live','edit','campaigns','paths','analytics') NOT NULL DEFAULT 'live',
  activity_drawer_open TINYINT(1) NOT NULL DEFAULT 0,
  safety_drawer_open TINYINT(1) NOT NULL DEFAULT 0,
  overlay_zone_metrics TINYINT(1) NOT NULL DEFAULT 1,
  overlay_customer_paths TINYINT(1) NOT NULL DEFAULT 1,
  overlay_customer_badges TINYINT(1) NOT NULL DEFAULT 1,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_store_canvas_settings_public_id (public_id),
  UNIQUE KEY uq_mg_store_canvas_settings_merchant (merchant_user_id),
  KEY idx_mg_store_canvas_settings_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_store_trigger_rule_simulations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  trigger_zone_public_id CHAR(36) NULL,
  trigger_zone_name VARCHAR(180) NULL,
  store_session_public_id CHAR(36) NULL,
  customer_user_id BIGINT UNSIGNED NULL,
  simulation_event ENUM('enter','repeat','return','manual') NOT NULL DEFAULT 'enter',
  automation_action VARCHAR(80) NULL,
  cooldown_policy VARCHAR(80) NULL,
  would_fire TINYINT(1) NOT NULL DEFAULT 0,
  would_send_message TINYINT(1) NOT NULL DEFAULT 0,
  would_send_reward TINYINT(1) NOT NULL DEFAULT 0,
  would_block_cooldown TINYINT(1) NOT NULL DEFAULT 0,
  result_label VARCHAR(255) NOT NULL,
  result_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_store_trigger_rule_simulations_public_id (public_id),
  KEY idx_mg_store_trigger_rule_simulations_merchant_created (merchant_user_id,created_at),
  KEY idx_mg_store_trigger_rule_simulations_zone_created (merchant_user_id,trigger_zone_public_id,created_at),
  KEY idx_mg_store_trigger_rule_simulations_session_created (merchant_user_id,store_session_public_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_store_customer_journey_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  customer_user_id BIGINT UNSIGNED NOT NULL,
  store_session_id BIGINT UNSIGNED NOT NULL,
  store_session_public_id CHAR(36) NOT NULL,
  journey_stage ENUM('entered','zone','chat','reward','claim','exit','idle') NOT NULL DEFAULT 'entered',
  journey_label VARCHAR(255) NOT NULL,
  trigger_zone_public_id CHAR(36) NULL,
  campaign_public_id CHAR(36) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_store_customer_journey_snapshots_public_id (public_id),
  KEY idx_mg_store_customer_journey_snapshots_session (store_session_id,created_at),
  KEY idx_mg_store_customer_journey_snapshots_merchant_customer (merchant_user_id,customer_user_id,created_at),
  KEY idx_mg_store_customer_journey_snapshots_stage (merchant_user_id,journey_stage,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_20f_store_canvas_persistence','Store Canvas intelligence persistence for settings, trigger rule simulations, and customer journey snapshots.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

SELECT 'stage_20f_store_canvas_persistence_complete' AS import_status;
SELECT DATABASE() AS active_database;
SHOW TABLES LIKE 'mg_store_canvas_settings';
SHOW TABLES LIKE 'mg_store_trigger_rule_simulations';
SHOW TABLES LIKE 'mg_store_customer_journey_snapshots';
