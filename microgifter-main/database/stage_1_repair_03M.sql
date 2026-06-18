-- Microgifter Stage 1 Repair Migration 03M
-- Run after database/stage_1_identity.sql if the baseline schema was already imported.

CREATE TABLE IF NOT EXISTS user_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  session_hash CHAR(64) NOT NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  last_seen_at DATETIME NULL,
  expires_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_sessions_hash (session_hash),
  KEY idx_user_sessions_user (user_id),
  KEY idx_user_sessions_expires (expires_at),
  CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_actors (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(80) NOT NULL,
  name VARCHAR(160) NOT NULL,
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_system_actors_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_fee_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  fee_key VARCHAR(80) NOT NULL,
  fee_value DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
  fee_type ENUM('percentage','fixed','placeholder') NOT NULL DEFAULT 'placeholder',
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_platform_fee_key (fee_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug, name) VALUES
('user.profile.update', 'Update own user profile'),
('admin.users.manage', 'Manage users'),
('admin.roles.manage', 'Manage user roles'),
('system.health.view', 'View system health');

INSERT IGNORE INTO system_actors (slug, name, description) VALUES
('platform', 'Microgifter Platform', 'Internal platform/system actor for Stage 1 events.'),
('auth', 'Authentication System', 'System actor for authentication and account lifecycle events.');

INSERT IGNORE INTO platform_fee_settings (fee_key, fee_value, fee_type, is_active, notes) VALUES
('stage_1_placeholder', 0.0000, 'placeholder', 0, 'Placeholder required by Stage 1 plan; real fees are configured in later commerce stages.');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug = 'user.profile.update' WHERE r.slug IN ('customer','merchant');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p WHERE r.slug IN ('admin','super_admin');
