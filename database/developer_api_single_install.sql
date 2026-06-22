-- Microgifter Developer/Public API single database install
-- Run this one file when installing the Developer API feature manually.
-- It is intentionally idempotent: existing tables are left in place.

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

SET @mg_schema_db = DATABASE();
SET @mg_add_description = (
  SELECT IF(COUNT(*) = 0, 1, 0)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @mg_schema_db
    AND TABLE_NAME = 'schema_migrations'
    AND COLUMN_NAME = 'description'
);
SET @mg_sql = IF(@mg_add_description = 1, 'ALTER TABLE schema_migrations ADD COLUMN description VARCHAR(255) NULL AFTER migration_key', 'SELECT 1');
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_add_checksum = (
  SELECT IF(COUNT(*) = 0, 1, 0)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @mg_schema_db
    AND TABLE_NAME = 'schema_migrations'
    AND COLUMN_NAME = 'checksum'
);
SET @mg_sql = IF(@mg_add_checksum = 1, 'ALTER TABLE schema_migrations ADD COLUMN checksum CHAR(64) NULL AFTER description', 'SELECT 1');
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

CREATE TABLE IF NOT EXISTS merchant_developer_apps (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  distribution_source_connection_id BIGINT UNSIGNED NULL,
  default_program_id BIGINT UNSIGNED NULL,
  name VARCHAR(180) NOT NULL,
  environment ENUM('test','live') NOT NULL DEFAULT 'test',
  status ENUM('draft','active','paused','revoked') NOT NULL DEFAULT 'active',
  allowed_origins_json JSON NULL,
  webhook_url VARCHAR(500) NULL,
  webhook_secret_hash CHAR(64) NULL,
  scopes_json JSON NULL,
  metadata_json JSON NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_developer_apps_public_id (public_id),
  KEY idx_merchant_developer_apps_merchant_status (merchant_user_id,status,environment),
  CONSTRAINT fk_merchant_developer_apps_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_developer_apps_source FOREIGN KEY (distribution_source_connection_id) REFERENCES distribution_source_connections(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_developer_apps_program FOREIGN KEY (default_program_id) REFERENCES distribution_programs(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_developer_apps_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_api_keys (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  app_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  environment ENUM('test','live') NOT NULL DEFAULT 'test',
  key_prefix VARCHAR(32) NOT NULL,
  key_hash CHAR(64) NOT NULL,
  scopes_json JSON NULL,
  status ENUM('active','revoked') NOT NULL DEFAULT 'active',
  expires_at DATETIME NULL,
  last_used_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_api_keys_public_id (public_id),
  UNIQUE KEY uq_merchant_api_keys_prefix (key_prefix),
  UNIQUE KEY uq_merchant_api_keys_hash (key_hash),
  KEY idx_merchant_api_keys_app_status (app_id,status),
  KEY idx_merchant_api_keys_merchant_status (merchant_user_id,status,environment),
  CONSTRAINT fk_merchant_api_keys_app FOREIGN KEY (app_id) REFERENCES merchant_developer_apps(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_api_keys_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_api_keys_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS developer_app_user_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  app_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  microgifter_user_id BIGINT UNSIGNED NOT NULL,
  external_user_id VARCHAR(255) NOT NULL,
  external_user_hash CHAR(64) NOT NULL,
  status ENUM('pending','active','revoked','expired') NOT NULL DEFAULT 'active',
  consent_json JSON NULL,
  metadata_json JSON NULL,
  linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_developer_app_user_links_public_id (public_id),
  UNIQUE KEY uq_developer_app_user_external (app_id,external_user_hash),
  UNIQUE KEY uq_developer_app_user_microgifter (app_id,microgifter_user_id),
  KEY idx_developer_app_user_links_merchant (merchant_user_id,status,linked_at),
  CONSTRAINT fk_developer_app_user_links_app FOREIGN KEY (app_id) REFERENCES merchant_developer_apps(id) ON DELETE CASCADE,
  CONSTRAINT fk_developer_app_user_links_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_developer_app_user_links_user FOREIGN KEY (microgifter_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS distribution_api_request_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  app_id BIGINT UNSIGNED NULL,
  api_key_id BIGINT UNSIGNED NULL,
  source_connection_id BIGINT UNSIGNED NULL,
  request_id VARCHAR(80) NULL,
  method VARCHAR(12) NOT NULL,
  endpoint VARCHAR(255) NOT NULL,
  status_code SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  response_status VARCHAR(80) NULL,
  idempotency_key VARCHAR(255) NULL,
  request_checksum CHAR(64) NULL,
  ip_hash CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  error_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_distribution_api_request_logs_public_id (public_id),
  KEY idx_distribution_api_request_logs_app (app_id,created_at),
  KEY idx_distribution_api_request_logs_merchant (merchant_user_id,created_at),
  KEY idx_distribution_api_request_logs_key (api_key_id,created_at),
  CONSTRAINT fk_distribution_api_request_logs_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_distribution_api_request_logs_app FOREIGN KEY (app_id) REFERENCES merchant_developer_apps(id) ON DELETE SET NULL,
  CONSTRAINT fk_distribution_api_request_logs_key FOREIGN KEY (api_key_id) REFERENCES merchant_api_keys(id) ON DELETE SET NULL,
  CONSTRAINT fk_distribution_api_request_logs_source FOREIGN KEY (source_connection_id) REFERENCES distribution_source_connections(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS developer_app_link_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  app_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  link_code_hash CHAR(64) NOT NULL,
  external_user_id VARCHAR(255) NOT NULL,
  external_user_hash CHAR(64) NOT NULL,
  return_url VARCHAR(700) NOT NULL,
  state VARCHAR(255) NULL,
  status ENUM('pending','approved','cancelled','expired') NOT NULL DEFAULT 'pending',
  requested_scopes_json JSON NULL,
  metadata_json JSON NULL,
  expires_at DATETIME NOT NULL,
  approved_user_id BIGINT UNSIGNED NULL,
  linked_account_public_id CHAR(36) NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_developer_app_link_requests_public_id (public_id),
  UNIQUE KEY uq_developer_app_link_requests_code (link_code_hash),
  KEY idx_developer_app_link_requests_app_status (app_id,status,expires_at),
  KEY idx_developer_app_link_requests_external (app_id,external_user_hash,status),
  CONSTRAINT fk_developer_app_link_requests_app FOREIGN KEY (app_id) REFERENCES merchant_developer_apps(id) ON DELETE CASCADE,
  CONSTRAINT fk_developer_app_link_requests_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_developer_app_link_requests_user FOREIGN KEY (approved_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS developer_webhook_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  app_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  source_event_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(100) NOT NULL,
  aggregate_type VARCHAR(80) NULL,
  aggregate_public_id VARCHAR(80) NULL,
  payload_json JSON NOT NULL,
  status ENUM('queued','processing','delivered','failed','dead_letter','skipped') NOT NULL DEFAULT 'queued',
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 8,
  next_attempt_at DATETIME NULL,
  last_attempt_at DATETIME NULL,
  delivered_at DATETIME NULL,
  failure_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_developer_webhook_events_public_id (public_id),
  KEY idx_developer_webhook_events_queue (status,next_attempt_at,created_at),
  KEY idx_developer_webhook_events_app (app_id,status,created_at),
  KEY idx_developer_webhook_events_aggregate (aggregate_type,aggregate_public_id),
  CONSTRAINT fk_developer_webhook_events_app FOREIGN KEY (app_id) REFERENCES merchant_developer_apps(id) ON DELETE CASCADE,
  CONSTRAINT fk_developer_webhook_events_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_developer_webhook_events_source_event FOREIGN KEY (source_event_id) REFERENCES distribution_source_events(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS developer_webhook_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  webhook_event_id BIGINT UNSIGNED NOT NULL,
  app_id BIGINT UNSIGNED NOT NULL,
  endpoint_hash CHAR(64) NOT NULL,
  attempt_number SMALLINT UNSIGNED NOT NULL,
  status ENUM('processing','delivered','failed') NOT NULL DEFAULT 'processing',
  http_status SMALLINT UNSIGNED NULL,
  request_checksum CHAR(64) NOT NULL,
  response_checksum CHAR(64) NULL,
  failure_message VARCHAR(500) NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_developer_webhook_attempts_public_id (public_id),
  UNIQUE KEY uq_developer_webhook_attempt_number (webhook_event_id,attempt_number),
  KEY idx_developer_webhook_attempts_app (app_id,created_at),
  CONSTRAINT fk_developer_webhook_attempts_event FOREIGN KEY (webhook_event_id) REFERENCES developer_webhook_events(id) ON DELETE CASCADE,
  CONSTRAINT fk_developer_webhook_attempts_app FOREIGN KEY (app_id) REFERENCES merchant_developer_apps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS public_api_quota_buckets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  app_id BIGINT UNSIGNED NOT NULL,
  api_key_id BIGINT UNSIGNED NOT NULL,
  bucket_scope ENUM('minute','day','month') NOT NULL,
  bucket_key VARCHAR(32) NOT NULL,
  limit_value INT UNSIGNED NOT NULL,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  window_start DATETIME NOT NULL,
  window_end DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_public_api_quota_buckets_public_id (public_id),
  UNIQUE KEY uq_public_api_quota_buckets_window (api_key_id,bucket_scope,bucket_key),
  KEY idx_public_api_quota_buckets_app (app_id,bucket_scope,window_start),
  KEY idx_public_api_quota_buckets_merchant (merchant_user_id,bucket_scope,window_start),
  CONSTRAINT fk_public_api_quota_buckets_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_public_api_quota_buckets_app FOREIGN KEY (app_id) REFERENCES merchant_developer_apps(id) ON DELETE CASCADE,
  CONSTRAINT fk_public_api_quota_buckets_key FOREIGN KEY (api_key_id) REFERENCES merchant_api_keys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS public_api_sandbox_rewards (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  app_id BIGINT UNSIGNED NOT NULL,
  api_key_id BIGINT UNSIGNED NOT NULL,
  program_public_id VARCHAR(80) NOT NULL,
  template_public_id VARCHAR(80) NOT NULL,
  linked_account_public_id VARCHAR(120) NOT NULL,
  external_event_id VARCHAR(180) NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  idempotency_key CHAR(64) NOT NULL,
  status ENUM('sandbox_queued','sandbox_delivered','sandbox_failed') NOT NULL DEFAULT 'sandbox_delivered',
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_public_api_sandbox_rewards_public_id (public_id),
  UNIQUE KEY uq_public_api_sandbox_rewards_idempotency (app_id,idempotency_key),
  KEY idx_public_api_sandbox_rewards_app (app_id,created_at),
  KEY idx_public_api_sandbox_rewards_merchant (merchant_user_id,created_at),
  CONSTRAINT fk_public_api_sandbox_rewards_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_public_api_sandbox_rewards_app FOREIGN KEY (app_id) REFERENCES merchant_developer_apps(id) ON DELETE CASCADE,
  CONSTRAINT fk_public_api_sandbox_rewards_key FOREIGN KEY (api_key_id) REFERENCES merchant_api_keys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.developer_api.view','View merchant developer API','View developer apps, API keys, API logs, and public API setup.',NOW()),
('merchant.developer_api.manage','Manage merchant developer API','Create, rotate, revoke, and configure merchant API keys and developer apps.',NOW()),
('merchant.developer_webhooks.test','Test developer webhooks','Queue test webhook events for merchant developer apps.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('merchant.developer_api.view','merchant.developer_api.manage','merchant.developer_webhooks.test')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT INTO schema_migrations (migration_key, description, checksum, applied_at) VALUES
('stage_public_distribution_api_foundation','Applied manually by developer_api_single_install.sql',NULL,NOW()),
('stage_public_distribution_api_account_links_note','Applied manually by developer_api_single_install.sql',NULL,NOW()),
('stage_public_distribution_api_webhooks','Applied manually by developer_api_single_install.sql',NULL,NOW()),
('stage_public_distribution_api_quotas','Applied manually by developer_api_single_install.sql',NULL,NOW()),
('stage_public_distribution_api_sandbox','Applied manually by developer_api_single_install.sql',NULL,NOW())
ON DUPLICATE KEY UPDATE description = COALESCE(schema_migrations.description, VALUES(description));
