-- Microgifter Stage 3 Agent Persistence
-- Server-authoritative saved-agent lifecycle and immutable history.

CREATE TABLE IF NOT EXISTS agents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  category VARCHAR(40) NULL,
  config_json JSON NULL,
  runtime_status ENUM('paused','running') NOT NULL DEFAULT 'paused',
  lifecycle_status ENUM('active','archived','deleted') NOT NULL DEFAULT 'active',
  version_no INT UNSIGNED NOT NULL DEFAULT 1,
  started_at DATETIME NULL,
  paused_at DATETIME NULL,
  archived_at DATETIME NULL,
  restored_at DATETIME NULL,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agents_public_id (public_id),
  KEY idx_agents_user_lifecycle (user_id, lifecycle_status, updated_at),
  KEY idx_agents_user_runtime (user_id, runtime_status),
  CONSTRAINT fk_agents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT chk_agents_name_nonempty CHECK (CHAR_LENGTH(TRIM(name)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agent_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('created','updated','started','paused','archived','restored','deleted') NOT NULL,
  agent_name_snapshot VARCHAR(80) NOT NULL,
  category_snapshot VARCHAR(40) NULL,
  config_snapshot_json JSON NULL,
  runtime_status_snapshot ENUM('paused','running') NOT NULL,
  lifecycle_status_snapshot ENUM('active','archived','deleted') NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_agent_history_agent_created (agent_id, created_at),
  KEY idx_agent_history_user_created (user_id, created_at),
  CONSTRAINT fk_agent_history_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE RESTRICT,
  CONSTRAINT fk_agent_history_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug, name, description, created_at) VALUES
('agent.create', 'Create agents', 'Create saved agent workspaces.', NOW()),
('agent.update', 'Update agents', 'Rename and update owned agent workspaces.', NOW()),
('agent.runtime.manage', 'Manage agent runtime', 'Start and pause owned agents.', NOW()),
('agent.archive', 'Archive agents', 'Archive and restore owned agents.', NOW()),
('agent.delete', 'Delete agents', 'Soft-delete owned agents while preserving history.', NOW());

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('agent.create','agent.update','agent.runtime.manage','agent.archive','agent.delete')
WHERE r.slug IN ('customer','merchant','admin','super_admin');