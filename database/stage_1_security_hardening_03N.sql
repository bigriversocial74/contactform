-- Microgifter Stage 1 Security/Stability Hardening Migration 03N
-- Run after:
--   1. database/stage_1_identity.sql
--   2. database/stage_1_repair_03M.sql

CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  migration_key VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_schema_migrations_key (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  action VARCHAR(80) NOT NULL,
  identifier_hash CHAR(64) NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  first_seen_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL,
  locked_until DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rate_limits_action_identifier (action, identifier_hash),
  KEY idx_rate_limits_locked_until (locked_until),
  KEY idx_rate_limits_last_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  severity ENUM('debug','info','warning','error','critical') NOT NULL DEFAULT 'info',
  event_type VARCHAR(120) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  request_id VARCHAR(80) NULL,
  message VARCHAR(255) NOT NULL,
  context_json JSON NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_security_logs_event_type (event_type),
  KEY idx_security_logs_user_id (user_id),
  KEY idx_security_logs_request_id (request_id),
  KEY idx_security_logs_created_at (created_at),
  CONSTRAINT fk_security_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug, name) VALUES
('admin.health.view', 'View detailed admin health checks'),
('security.logs.view', 'View security logs'),
('security.rate_limits.manage', 'Manage rate limits'),
('security.sessions.manage', 'Manage user sessions');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.slug IN ('admin.health.view', 'security.logs.view', 'security.rate_limits.manage', 'security.sessions.manage')
WHERE r.slug IN ('admin','super_admin');

INSERT IGNORE INTO schema_migrations (migration_key, description) VALUES
('03N_security_stability_hardening', 'Adds rate limits, security logs, migration tracking, and security permissions.');
