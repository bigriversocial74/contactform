-- Hosted Games Release and QA Foundation v1
-- Release lifecycle, preserved package archives, validation/health records,
-- and isolated merchant/admin preview sessions that never consume live inventory.

ALTER TABLE hosted_game_releases
  MODIFY COLUMN status ENUM('uploaded','active','previous','failed','deleted','draft','testing','archived') NOT NULL DEFAULT 'draft';

UPDATE hosted_game_releases SET status='draft' WHERE status='uploaded';
UPDATE hosted_game_releases SET status='archived' WHERE status IN ('previous','deleted');

ALTER TABLE hosted_game_releases
  MODIFY COLUMN status ENUM('draft','testing','active','failed','archived') NOT NULL DEFAULT 'draft',
  ADD COLUMN release_notes TEXT NULL AFTER original_filename,
  ADD COLUMN package_zip_storage_key VARCHAR(700) NULL AFTER storage_key,
  ADD COLUMN package_zip_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER package_zip_storage_key,
  ADD COLUMN entry_file VARCHAR(255) NOT NULL DEFAULT 'index.html' AFTER package_checksum,
  ADD COLUMN manifest_schema VARCHAR(120) NULL AFTER manifest_json,
  ADD COLUMN manifest_version VARCHAR(40) NULL AFTER manifest_schema,
  ADD COLUMN sdk_version VARCHAR(40) NULL AFTER manifest_version,
  ADD COLUMN validation_status ENUM('pending','passed','warning','failed') NOT NULL DEFAULT 'pending' AFTER extracted_bytes,
  ADD COLUMN validation_json JSON NULL AFTER validation_status,
  ADD COLUMN validated_at DATETIME NULL AFTER validation_json,
  ADD COLUMN health_status ENUM('not_run','pending','passed','warning','failed') NOT NULL DEFAULT 'not_run' AFTER validated_at,
  ADD COLUMN health_json JSON NULL AFTER health_status,
  ADD COLUMN health_checked_at DATETIME NULL AFTER health_json,
  ADD COLUMN activated_by_user_id BIGINT UNSIGNED NULL AFTER uploaded_by_user_id,
  ADD COLUMN archived_at DATETIME NULL AFTER activated_at,
  ADD COLUMN failure_message VARCHAR(500) NULL AFTER archived_at,
  ADD KEY idx_hosted_game_releases_validation (game_id,validation_status,health_status),
  ADD KEY idx_hosted_game_releases_created (game_id,created_at),
  ADD CONSTRAINT fk_hosted_game_releases_activator FOREIGN KEY (activated_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

UPDATE hosted_game_releases
SET entry_file=(SELECT hg.entry_file FROM hosted_games hg WHERE hg.id=hosted_game_releases.game_id)
WHERE status='active';

UPDATE hosted_game_releases
SET validation_status='passed',
    validated_at=COALESCE(validated_at,created_at),
    health_status='passed',
    health_checked_at=COALESCE(health_checked_at,activated_at,created_at),
    manifest_schema=JSON_UNQUOTE(JSON_EXTRACT(manifest_json,'$.schema')),
    manifest_version=JSON_UNQUOTE(JSON_EXTRACT(manifest_json,'$.version')),
    sdk_version='1.1.0'
WHERE status='active';

CREATE TABLE IF NOT EXISTS hosted_game_test_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  game_id BIGINT UNSIGNED NOT NULL,
  release_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  actor_scope ENUM('merchant','admin') NOT NULL,
  status ENUM('active','expired','reset','closed') NOT NULL DEFAULT 'active',
  test_program_public_id CHAR(36) NULL,
  test_campaign_public_id CHAR(36) NULL,
  test_template_public_id CHAR(36) NULL,
  test_player_key VARCHAR(190) NOT NULL,
  expires_at DATETIME NOT NULL,
  last_activity_at DATETIME NULL,
  reset_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hosted_game_test_sessions_public_id (public_id),
  KEY idx_hosted_game_test_sessions_actor (actor_user_id,status,expires_at),
  KEY idx_hosted_game_test_sessions_release (release_id,status,created_at),
  CONSTRAINT fk_hosted_game_test_sessions_game FOREIGN KEY (game_id) REFERENCES hosted_games(id) ON DELETE CASCADE,
  CONSTRAINT fk_hosted_game_test_sessions_release FOREIGN KEY (release_id) REFERENCES hosted_game_releases(id) ON DELETE CASCADE,
  CONSTRAINT fk_hosted_game_test_sessions_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hosted_game_test_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  session_id BIGINT UNSIGNED NOT NULL,
  player_user_id BIGINT UNSIGNED NOT NULL,
  run_token_hash CHAR(64) NOT NULL,
  status ENUM('started','completed','qualified','abandoned','expired','failed') NOT NULL DEFAULT 'started',
  score BIGINT NULL,
  qualified TINYINT(1) NOT NULL DEFAULT 0,
  result_json JSON NULL,
  simulated_reward_public_id CHAR(36) NULL,
  simulated_reward_status VARCHAR(80) NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hosted_game_test_runs_public_id (public_id),
  UNIQUE KEY uq_hosted_game_test_runs_token (run_token_hash),
  KEY idx_hosted_game_test_runs_session (session_id,status,created_at),
  CONSTRAINT fk_hosted_game_test_runs_session FOREIGN KEY (session_id) REFERENCES hosted_game_test_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_hosted_game_test_runs_player FOREIGN KEY (player_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hosted_game_test_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  session_id BIGINT UNSIGNED NOT NULL,
  run_id BIGINT UNSIGNED NULL,
  source ENUM('shell','sdk','runtime','game','system') NOT NULL DEFAULT 'runtime',
  severity ENUM('debug','info','warning','error') NOT NULL DEFAULT 'info',
  event_type VARCHAR(120) NOT NULL,
  request_action VARCHAR(100) NULL,
  duration_ms INT UNSIGNED NULL,
  event_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hosted_game_test_events_public_id (public_id),
  KEY idx_hosted_game_test_events_session (session_id,created_at),
  KEY idx_hosted_game_test_events_type (event_type,severity,created_at),
  CONSTRAINT fk_hosted_game_test_events_session FOREIGN KEY (session_id) REFERENCES hosted_game_test_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_hosted_game_test_events_run FOREIGN KEY (run_id) REFERENCES hosted_game_test_runs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hosted_game_test_state (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id BIGINT UNSIGNED NOT NULL,
  player_user_id BIGINT UNSIGNED NOT NULL,
  state_key VARCHAR(120) NOT NULL,
  state_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hosted_game_test_state (session_id,player_user_id,state_key),
  CONSTRAINT fk_hosted_game_test_state_session FOREIGN KEY (session_id) REFERENCES hosted_game_test_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_hosted_game_test_state_player FOREIGN KEY (player_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.hosted_games.releases.manage','Manage hosted game releases','Upload drafts, test, activate, roll back, archive, compare, and download merchant game releases.',NOW()),
('merchant.hosted_games.preview','Preview hosted game releases','Run protected test sessions for merchant-owned unpublished game releases.',NOW()),
('admin.hosted_games.releases.manage','Manage hosted game releases as admin','Manage release lifecycle and rollback for all hosted games.',NOW()),
('admin.hosted_games.preview','Preview hosted games as admin','Run protected test sessions for any hosted game release.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
  ON p.slug IN ('merchant.hosted_games.releases.manage','merchant.hosted_games.preview')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
  ON p.slug IN ('admin.hosted_games.releases.manage','admin.hosted_games.preview')
WHERE r.slug IN ('admin','super_admin');
