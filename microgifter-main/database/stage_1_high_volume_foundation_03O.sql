-- Microgifter Stage 1 High-Volume Data Foundation 03O
-- Import after:
-- 1. database/stage_1_identity.sql
-- 2. database/stage_1_repair_03M.sql
-- 3. database/stage_1_security_hardening_03N.sql
-- 4. database/stage_1_security_hardening_03N_3.sql

CREATE TABLE IF NOT EXISTS accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(26) NOT NULL,
  name VARCHAR(180) NOT NULL,
  slug VARCHAR(180) NULL,
  account_type VARCHAR(40) NOT NULL DEFAULT 'personal',
  status VARCHAR(40) NOT NULL DEFAULT 'active',
  owner_user_id BIGINT UNSIGNED NULL,
  metadata_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_accounts_public_id (public_id),
  UNIQUE KEY uq_accounts_slug (slug),
  KEY idx_accounts_owner_status (owner_user_id, status),
  KEY idx_accounts_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  member_role VARCHAR(60) NOT NULL DEFAULT 'member',
  status VARCHAR(40) NOT NULL DEFAULT 'active',
  metadata_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_account_members_account_user (account_id, user_id),
  KEY idx_account_members_user_status (user_id, status),
  KEY idx_account_members_account_status (account_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS outbox_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(26) NOT NULL,
  account_id BIGINT UNSIGNED NULL,
  store_id BIGINT UNSIGNED NULL,
  owner_user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(120) NOT NULL,
  aggregate_type VARCHAR(80) NULL,
  aggregate_id VARCHAR(80) NULL,
  payload_json JSON NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'pending',
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts INT UNSIGNED NOT NULL DEFAULT 10,
  available_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  locked_at TIMESTAMP NULL DEFAULT NULL,
  locked_by VARCHAR(120) NULL,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  failed_at TIMESTAMP NULL DEFAULT NULL,
  last_error TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_outbox_events_public_id (public_id),
  KEY idx_outbox_status_available (status, available_at, id),
  KEY idx_outbox_account_status (account_id, status, created_at),
  KEY idx_outbox_owner_status (owner_user_id, status, created_at),
  KEY idx_outbox_aggregate (aggregate_type, aggregate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS idempotency_keys (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  idempotency_key VARCHAR(160) NOT NULL,
  scope_type VARCHAR(40) NOT NULL,
  scope_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  request_hash CHAR(64) NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'reserved',
  response_status SMALLINT UNSIGNED NULL,
  response_json JSON NULL,
  resource_type VARCHAR(80) NULL,
  resource_id VARCHAR(80) NULL,
  expires_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_idempotency_scope_key (scope_type, scope_id, idempotency_key),
  KEY idx_idempotency_expires (expires_at),
  KEY idx_idempotency_resource (resource_type, resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS read_model_refreshes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  model_type VARCHAR(80) NOT NULL,
  scope_type VARCHAR(40) NOT NULL,
  scope_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  source_type VARCHAR(80) NULL,
  source_id VARCHAR(80) NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'pending',
  requested_by_event_id BIGINT UNSIGNED NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  available_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  last_error TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_read_model_status_available (status, available_at, id),
  KEY idx_read_model_scope (model_type, scope_type, scope_id),
  KEY idx_read_model_source (source_type, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug, name, description, created_at) VALUES
('accounts.view', 'View accounts', 'View account records connected to the current user.', NOW()),
('accounts.manage', 'Manage accounts', 'Manage account settings and memberships.', NOW()),
('outbox.view', 'View outbox', 'View operational outbox events.', NOW()),
('outbox.manage', 'Manage outbox', 'Retry, cancel, or inspect operational outbox events.', NOW()),
('idempotency.view', 'View idempotency keys', 'View duplicate-protection request records.', NOW()),
('read_models.manage', 'Manage read models', 'Manage read-model refresh records.', NOW());

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('accounts.view')
WHERE r.slug IN ('customer', 'merchant', 'admin', 'super_admin');

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('accounts.manage')
WHERE r.slug IN ('merchant', 'admin', 'super_admin');

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('outbox.view', 'outbox.manage', 'idempotency.view', 'read_models.manage')
WHERE r.slug IN ('admin', 'super_admin');
