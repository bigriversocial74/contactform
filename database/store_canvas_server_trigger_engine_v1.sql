-- Store Canvas Server-Authoritative Trigger/Event Engine v1
--
-- Adds governed engine settings, merchant-owned rules, normalized server events,
-- and auditable evaluation outcomes. Existing campaigns, reward_templates,
-- Store Canvas sessions, behavior profiles, notifications, action receipts,
-- Wallet, Inbox, and PPPM remain authoritative.
--
-- Safe to re-run. No browser position or avatar overlap is stored as authority.

CREATE TABLE IF NOT EXISTS schema_migrations (
  migration_key VARCHAR(190) NOT NULL,
  description VARCHAR(500) NULL,
  checksum VARCHAR(128) NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_store_trigger_engine_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  execution_mode ENUM('paused','dry_run','notification') NOT NULL DEFAULT 'paused',
  max_notifications_per_run SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  default_cooldown_seconds INT UNSIGNED NOT NULL DEFAULT 86400,
  last_run_at DATETIME NULL,
  last_run_status ENUM('never','running','completed','partial','failed') NOT NULL DEFAULT 'never',
  last_run_summary_json JSON NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_store_trigger_engine_settings_public (public_id),
  UNIQUE KEY uq_mg_store_trigger_engine_settings_merchant (merchant_user_id),
  KEY idx_mg_store_trigger_engine_settings_mode (execution_mode,updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_store_trigger_engine_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  trigger_zone_public_id CHAR(36) NULL,
  campaign_public_id CHAR(36) NOT NULL,
  name VARCHAR(180) NOT NULL,
  event_type ENUM('store_entry','return_visit','visit_milestone','campaign_interest','inactivity_risk','product_interest','reward_claimed','reward_redeemed') NOT NULL,
  status ENUM('enabled','paused','archived') NOT NULL DEFAULT 'paused',
  priority TINYINT UNSIGNED NOT NULL DEFAULT 3,
  minimum_probability DECIMAL(5,2) NOT NULL DEFAULT 50.00,
  minimum_confidence DECIMAL(5,2) NOT NULL DEFAULT 30.00,
  visit_milestone SMALLINT UNSIGNED NULL,
  cooldown_seconds INT UNSIGNED NOT NULL DEFAULT 86400,
  max_per_customer_day SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  require_active_session TINYINT(1) NOT NULL DEFAULT 1,
  notification_note VARCHAR(1000) NULL,
  audience_rules_json JSON NULL,
  metadata_json JSON NULL,
  last_evaluated_at DATETIME NULL,
  last_matched_at DATETIME NULL,
  last_delivered_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_store_trigger_engine_rules_public (public_id),
  KEY idx_mg_store_trigger_engine_rules_merchant_status (merchant_user_id,status,priority,updated_at),
  KEY idx_mg_store_trigger_engine_rules_campaign (merchant_user_id,campaign_public_id,status),
  KEY idx_mg_store_trigger_engine_rules_zone (merchant_user_id,trigger_zone_public_id,status),
  KEY idx_mg_store_trigger_engine_rules_event (merchant_user_id,event_type,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_store_trigger_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  customer_user_id BIGINT UNSIGNED NOT NULL,
  store_session_id BIGINT UNSIGNED NULL,
  store_session_public_id CHAR(36) NULL,
  event_key VARCHAR(190) NOT NULL,
  event_type ENUM('store_entry','return_visit','visit_milestone','campaign_interest','inactivity_risk','product_interest','reward_claimed','reward_redeemed') NOT NULL,
  source_type VARCHAR(80) NOT NULL,
  source_public_id VARCHAR(190) NULL,
  event_at DATETIME NOT NULL,
  payload_json JSON NULL,
  status ENUM('pending','evaluated','ignored','error') NOT NULL DEFAULT 'pending',
  error_code VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_store_trigger_events_public (public_id),
  UNIQUE KEY uq_mg_store_trigger_events_key (merchant_user_id,event_key),
  KEY idx_mg_store_trigger_events_merchant_status (merchant_user_id,status,event_at),
  KEY idx_mg_store_trigger_events_customer (merchant_user_id,customer_user_id,event_at),
  KEY idx_mg_store_trigger_events_session (store_session_id,event_at),
  KEY idx_mg_store_trigger_events_type (merchant_user_id,event_type,event_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_store_trigger_evaluations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  customer_user_id BIGINT UNSIGNED NOT NULL,
  event_id BIGINT UNSIGNED NOT NULL,
  rule_id BIGINT UNSIGNED NOT NULL,
  campaign_public_id CHAR(36) NOT NULL,
  trigger_zone_public_id CHAR(36) NULL,
  execution_mode ENUM('dry_run','notification') NOT NULL,
  decision ENUM('matched','skipped','blocked','delivered','error') NOT NULL,
  reason_code VARCHAR(120) NOT NULL,
  reason_text VARCHAR(500) NOT NULL,
  probability_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  confidence_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  recommendation_id CHAR(36) NULL,
  notification_id VARCHAR(190) NULL,
  evidence_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_store_trigger_evaluations_public (public_id),
  UNIQUE KEY uq_mg_store_trigger_evaluations_event_rule (event_id,rule_id),
  KEY idx_mg_store_trigger_evaluations_merchant_decision (merchant_user_id,decision,created_at),
  KEY idx_mg_store_trigger_evaluations_customer (merchant_user_id,customer_user_id,created_at),
  KEY idx_mg_store_trigger_evaluations_rule (rule_id,created_at),
  KEY idx_mg_store_trigger_evaluations_campaign (merchant_user_id,campaign_public_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'store_canvas_server_trigger_engine_v1',
  'Server-authoritative Store Canvas trigger settings, rules, normalized events, and auditable evaluations.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

SELECT 'store_canvas_server_trigger_engine_v1_complete' AS import_status;
SELECT DATABASE() AS active_database;
SHOW TABLES LIKE 'mg_store_trigger_engine_settings';
SHOW TABLES LIKE 'mg_store_trigger_engine_rules';
SHOW TABLES LIKE 'mg_store_trigger_events';
SHOW TABLES LIKE 'mg_store_trigger_evaluations';
