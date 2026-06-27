-- Stage 18AE Scanner Operations Control

CREATE TABLE IF NOT EXISTS scanner_device_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  workspace_id BIGINT UNSIGNED NULL,
  location_id BIGINT UNSIGNED NULL,
  location_public_id CHAR(36) NULL,
  device_label VARCHAR(120) NULL,
  device_key_hash CHAR(64) NULL,
  trusted_device TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_scan_at DATETIME NULL,
  last_ip_hash CHAR(64) NULL,
  last_user_agent_hash CHAR(64) NULL,
  disabled_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_scanner_device_sessions_public_id (public_id),
  KEY idx_scanner_device_sessions_merchant (merchant_user_id,status,last_scan_at),
  KEY idx_scanner_device_sessions_location (merchant_user_id,location_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_scanner_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  workspace_id BIGINT UNSIGNED NULL,
  location_id BIGINT UNSIGNED NULL,
  location_public_id CHAR(36) NULL,
  require_confirmation TINYINT(1) NOT NULL DEFAULT 1,
  lock_scanner_to_location TINYINT(1) NOT NULL DEFAULT 0,
  allow_manual_entry TINYINT(1) NOT NULL DEFAULT 1,
  max_failed_scans_per_hour SMALLINT UNSIGNED NOT NULL DEFAULT 8,
  require_manager_review_high_risk TINYINT(1) NOT NULL DEFAULT 1,
  high_risk_threshold TINYINT UNSIGNED NOT NULL DEFAULT 65,
  settings_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_scanner_settings_public_id (public_id),
  KEY idx_merchant_scanner_settings_scope (merchant_user_id,workspace_id,location_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_scanner_incidents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  risk_event_id BIGINT UNSIGNED NULL,
  incident_type VARCHAR(80) NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  status ENUM('open','reviewing','dismissed','escalated','resolved') NOT NULL DEFAULT 'open',
  merchant_user_id BIGINT UNSIGNED NULL,
  scanner_user_id BIGINT UNSIGNED NULL,
  scanner_location_id BIGINT UNSIGNED NULL,
  scanner_device_session_id BIGINT UNSIGNED NULL,
  gift_public_id VARCHAR(80) NULL,
  receipt_public_id CHAR(36) NULL,
  voucher_token_public_id CHAR(36) NULL,
  summary VARCHAR(255) NOT NULL,
  details_json JSON NULL,
  assigned_admin_user_id BIGINT UNSIGNED NULL,
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_scanner_incidents_public_id (public_id),
  KEY idx_admin_scanner_incidents_status (status,severity,created_at),
  KEY idx_admin_scanner_incidents_merchant (merchant_user_id,status,created_at),
  KEY idx_admin_scanner_incidents_device (scanner_device_session_id,status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_18ae_scanner_operations_control','Scanner operations control tables.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
