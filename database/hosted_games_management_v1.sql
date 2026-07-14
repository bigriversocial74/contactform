-- Hosted Games Management v1
-- Merchant-uploaded game releases, per-game API/runtime integration, isolated game databases,
-- player runs, and event analytics.

CREATE TABLE IF NOT EXISTS hosted_games (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  developer_app_id BIGINT UNSIGNED NULL,
  api_key_id BIGINT UNSIGNED NULL,
  distribution_program_id BIGINT UNSIGNED NULL,
  campaign_id BIGINT UNSIGNED NULL,
  pppm_template_id BIGINT UNSIGNED NULL,
  current_release_public_id CHAR(36) NULL,
  name VARCHAR(180) NOT NULL,
  slug VARCHAR(140) NOT NULL,
  description TEXT NULL,
  cover_url VARCHAR(500) NULL,
  entry_file VARCHAR(255) NOT NULL DEFAULT 'index.html',
  status ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'draft',
  integration_status ENUM('pending','ready','blocked','error') NOT NULL DEFAULT 'pending',
  database_status ENUM('pending','ready','error','disabled') NOT NULL DEFAULT 'pending',
  published_at DATETIME NULL,
  archived_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hosted_games_public_id (public_id),
  UNIQUE KEY uq_hosted_games_slug (slug),
  KEY idx_hosted_games_merchant_status (merchant_user_id,status,updated_at),
  KEY idx_hosted_games_app (developer_app_id,status),
  KEY idx_hosted_games_program (distribution_program_id,status),
  KEY idx_hosted_games_campaign (campaign_id,status),
  KEY idx_hosted_games_pppm (pppm_template_id,status),
  CONSTRAINT fk_hosted_games_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_hosted_games_app FOREIGN KEY (developer_app_id) REFERENCES merchant_developer_apps(id) ON DELETE SET NULL,
  CONSTRAINT fk_hosted_games_api_key FOREIGN KEY (api_key_id) REFERENCES merchant_api_keys(id) ON DELETE SET NULL,
  CONSTRAINT fk_hosted_games_program FOREIGN KEY (distribution_program_id) REFERENCES distribution_programs(id) ON DELETE SET NULL,
  CONSTRAINT fk_hosted_games_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
  CONSTRAINT fk_hosted_games_pppm FOREIGN KEY (pppm_template_id) REFERENCES catalog_pppm_templates(id) ON DELETE SET NULL,
  CONSTRAINT fk_hosted_games_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_hosted_games_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hosted_game_releases (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  game_id BIGINT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  storage_key VARCHAR(700) NOT NULL,
  package_checksum CHAR(64) NOT NULL,
  manifest_json JSON NULL,
  file_count INT UNSIGNED NOT NULL DEFAULT 0,
  extracted_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('uploaded','active','previous','failed','deleted') NOT NULL DEFAULT 'uploaded',
  uploaded_by_user_id BIGINT UNSIGNED NOT NULL,
  activated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hosted_game_releases_public_id (public_id),
  UNIQUE KEY uq_hosted_game_releases_version (game_id,version_number),
  KEY idx_hosted_game_releases_status (game_id,status,version_number),
  CONSTRAINT fk_hosted_game_releases_game FOREIGN KEY (game_id) REFERENCES hosted_games(id) ON DELETE CASCADE,
  CONSTRAINT fk_hosted_game_releases_uploader FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hosted_game_secrets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  game_id BIGINT UNSIGNED NOT NULL,
  api_credential_ciphertext MEDIUMTEXT NULL,
  webhook_secret_ciphertext MEDIUMTEXT NULL,
  state_secret_ciphertext MEDIUMTEXT NULL,
  encryption_version VARCHAR(40) NOT NULL DEFAULT 'secretbox-v1',
  rotated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hosted_game_secrets_game (game_id),
  CONSTRAINT fk_hosted_game_secrets_game FOREIGN KEY (game_id) REFERENCES hosted_games(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hosted_game_database_connections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  game_id BIGINT UNSIGNED NOT NULL,
  driver ENUM('mysql') NOT NULL DEFAULT 'mysql',
  host VARCHAR(255) NOT NULL,
  port SMALLINT UNSIGNED NOT NULL DEFAULT 3306,
  database_name VARCHAR(190) NOT NULL,
  username_ciphertext MEDIUMTEXT NOT NULL,
  password_ciphertext MEDIUMTEXT NOT NULL,
  charset VARCHAR(40) NOT NULL DEFAULT 'utf8mb4',
  status ENUM('pending','ready','error','disabled') NOT NULL DEFAULT 'pending',
  last_tested_at DATETIME NULL,
  last_connected_at DATETIME NULL,
  last_error_message VARCHAR(500) NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hosted_game_database_public_id (public_id),
  UNIQUE KEY uq_hosted_game_database_game (game_id),
  UNIQUE KEY uq_hosted_game_database_target (host,port,database_name),
  KEY idx_hosted_game_database_status (status,last_tested_at),
  CONSTRAINT fk_hosted_game_database_game FOREIGN KEY (game_id) REFERENCES hosted_games(id) ON DELETE CASCADE,
  CONSTRAINT fk_hosted_game_database_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hosted_game_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  game_id BIGINT UNSIGNED NOT NULL,
  player_user_id BIGINT UNSIGNED NOT NULL,
  developer_app_id BIGINT UNSIGNED NOT NULL,
  linked_account_public_id CHAR(36) NOT NULL,
  program_public_id CHAR(36) NOT NULL,
  campaign_public_id CHAR(36) NOT NULL,
  template_public_id CHAR(36) NOT NULL,
  run_token_hash CHAR(64) NOT NULL,
  external_event_id VARCHAR(180) NOT NULL,
  status ENUM('started','completed','qualified','issuing','queued','delivered','failed','expired') NOT NULL DEFAULT 'started',
  score BIGINT NULL,
  result_json JSON NULL,
  reward_public_id CHAR(36) NULL,
  api_status VARCHAR(80) NULL,
  error_message VARCHAR(500) NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  rewarded_at DATETIME NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hosted_game_runs_public_id (public_id),
  UNIQUE KEY uq_hosted_game_runs_token_hash (run_token_hash),
  UNIQUE KEY uq_hosted_game_runs_external_event (external_event_id),
  UNIQUE KEY uq_hosted_game_runs_reward (reward_public_id),
  KEY idx_hosted_game_runs_game_status (game_id,status,created_at),
  KEY idx_hosted_game_runs_player_status (player_user_id,status,created_at),
  KEY idx_hosted_game_runs_program (program_public_id,created_at),
  KEY idx_hosted_game_runs_campaign (campaign_public_id,created_at),
  KEY idx_hosted_game_runs_expiry (status,expires_at),
  CONSTRAINT fk_hosted_game_runs_game FOREIGN KEY (game_id) REFERENCES hosted_games(id) ON DELETE CASCADE,
  CONSTRAINT fk_hosted_game_runs_player FOREIGN KEY (player_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_hosted_game_runs_app FOREIGN KEY (developer_app_id) REFERENCES merchant_developer_apps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hosted_game_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  game_id BIGINT UNSIGNED NOT NULL,
  run_id BIGINT UNSIGNED NULL,
  player_user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(100) NOT NULL,
  event_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hosted_game_events_public_id (public_id),
  KEY idx_hosted_game_events_game_created (game_id,created_at),
  KEY idx_hosted_game_events_type_created (event_type,created_at),
  KEY idx_hosted_game_events_run (run_id,created_at),
  CONSTRAINT fk_hosted_game_events_game FOREIGN KEY (game_id) REFERENCES hosted_games(id) ON DELETE CASCADE,
  CONSTRAINT fk_hosted_game_events_run FOREIGN KEY (run_id) REFERENCES hosted_game_runs(id) ON DELETE SET NULL,
  CONSTRAINT fk_hosted_game_events_player FOREIGN KEY (player_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.hosted_games.view','View hosted games','View merchant-owned hosted games, releases, integrations, and analytics.',NOW()),
('merchant.hosted_games.manage','Manage hosted games','Upload, configure, publish, pause, and version merchant-hosted games.',NOW()),
('admin.hosted_games.view','View hosted games administration','View hosted game inventory and database readiness.',NOW()),
('admin.hosted_games.manage','Manage hosted games administration','Configure and test isolated game database credentials and platform controls.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('merchant.hosted_games.view','merchant.hosted_games.manage')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.hosted_games.view','admin.hosted_games.manage')
WHERE r.slug IN ('admin','super_admin');
