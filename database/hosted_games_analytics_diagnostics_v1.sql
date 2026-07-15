-- Hosted Games Analytics and Diagnostics v1
-- Per-run release/client snapshots, grouped diagnostics, occurrence history,
-- and dedicated Merchant/Admin permissions.

CREATE TABLE IF NOT EXISTS hosted_game_run_observability (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  run_id BIGINT UNSIGNED NOT NULL,
  game_id BIGINT UNSIGNED NOT NULL,
  release_public_id CHAR(36) NULL,
  release_version INT UNSIGNED NULL,
  sdk_version VARCHAR(40) NULL,
  game_version VARCHAR(40) NULL,
  client_json JSON NULL,
  qualified_at DATETIME NULL,
  abandoned_at DATETIME NULL,
  duration_ms BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hosted_game_run_observability_run (run_id),
  KEY idx_hosted_game_run_observability_release (game_id,release_public_id,created_at),
  KEY idx_hosted_game_run_observability_game_created (game_id,created_at),
  CONSTRAINT fk_hosted_game_run_observability_run FOREIGN KEY (run_id) REFERENCES hosted_game_runs(id) ON DELETE CASCADE,
  CONSTRAINT fk_hosted_game_run_observability_game FOREIGN KEY (game_id) REFERENCES hosted_games(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hosted_game_diagnostic_groups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  game_id BIGINT UNSIGNED NOT NULL,
  release_public_id CHAR(36) NULL,
  release_version INT UNSIGNED NULL,
  fingerprint CHAR(64) NOT NULL,
  category VARCHAR(80) NOT NULL,
  severity ENUM('info','warning','error','critical') NOT NULL DEFAULT 'error',
  status ENUM('open','resolved','ignored') NOT NULL DEFAULT 'open',
  title VARCHAR(180) NOT NULL,
  message VARCHAR(500) NOT NULL,
  browser_family VARCHAR(80) NULL,
  occurrence_count BIGINT UNSIGNED NOT NULL DEFAULT 1,
  affected_players BIGINT UNSIGNED NOT NULL DEFAULT 0,
  first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  resolved_by_user_id BIGINT UNSIGNED NULL,
  sample_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hosted_game_diagnostic_fingerprint (game_id,fingerprint),
  UNIQUE KEY uq_hosted_game_diagnostic_public_id (public_id),
  KEY idx_hosted_game_diagnostic_status (game_id,status,severity,last_seen_at),
  KEY idx_hosted_game_diagnostic_release (game_id,release_public_id,last_seen_at),
  KEY idx_hosted_game_diagnostic_category (game_id,category,last_seen_at),
  CONSTRAINT fk_hosted_game_diagnostic_game FOREIGN KEY (game_id) REFERENCES hosted_games(id) ON DELETE CASCADE,
  CONSTRAINT fk_hosted_game_diagnostic_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hosted_game_diagnostic_occurrences (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  diagnostic_group_id BIGINT UNSIGNED NOT NULL,
  game_id BIGINT UNSIGNED NOT NULL,
  run_id BIGINT UNSIGNED NULL,
  player_user_id BIGINT UNSIGNED NULL,
  release_public_id CHAR(36) NULL,
  event_type VARCHAR(100) NOT NULL,
  context_json JSON NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hosted_game_diagnostic_occurrence_public (public_id),
  KEY idx_hosted_game_diagnostic_occurrence_group (diagnostic_group_id,occurred_at),
  KEY idx_hosted_game_diagnostic_occurrence_game (game_id,occurred_at),
  KEY idx_hosted_game_diagnostic_occurrence_player (player_user_id,occurred_at),
  CONSTRAINT fk_hosted_game_diagnostic_occurrence_group FOREIGN KEY (diagnostic_group_id) REFERENCES hosted_game_diagnostic_groups(id) ON DELETE CASCADE,
  CONSTRAINT fk_hosted_game_diagnostic_occurrence_game FOREIGN KEY (game_id) REFERENCES hosted_games(id) ON DELETE CASCADE,
  CONSTRAINT fk_hosted_game_diagnostic_occurrence_run FOREIGN KEY (run_id) REFERENCES hosted_game_runs(id) ON DELETE SET NULL,
  CONSTRAINT fk_hosted_game_diagnostic_occurrence_player FOREIGN KEY (player_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.hosted_games.analytics.view','View hosted game analytics','View merchant-owned hosted game performance, reward, release, device, and funnel analytics.',NOW()),
('merchant.hosted_games.diagnostics.manage','Manage hosted game diagnostics','View, resolve, ignore, and export merchant-owned hosted game diagnostics.',NOW()),
('admin.hosted_games.analytics.view','View hosted game analytics administration','View platform hosted game analytics and health reporting.',NOW()),
('admin.hosted_games.diagnostics.manage','Manage hosted game diagnostics administration','Resolve, ignore, and export hosted game diagnostic groups across merchants.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('merchant.hosted_games.analytics.view','merchant.hosted_games.diagnostics.manage')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.hosted_games.analytics.view','admin.hosted_games.diagnostics.manage')
WHERE r.slug IN ('admin','super_admin');
