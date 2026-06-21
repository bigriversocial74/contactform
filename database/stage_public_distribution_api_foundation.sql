-- Public Distribution API foundation
-- Merchant developer apps, API keys, linked users, request logs, and docs permissions.

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

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.developer_api.view','View merchant developer API','View developer apps, API keys, API logs, and public API setup.',NOW()),
('merchant.developer_api.manage','Manage merchant developer API','Create, rotate, revoke, and configure merchant API keys and developer apps.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('merchant.developer_api.view','merchant.developer_api.manage')
WHERE r.slug IN ('merchant','admin','super_admin');
