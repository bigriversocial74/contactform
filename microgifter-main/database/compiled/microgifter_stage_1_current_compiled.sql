-- Microgifter Stage 1 Current Compiled Schema
-- Generated from Stage 1 identity, repair, security, high-volume, and delivery migrations.
-- Use this file for a NEW/FRESH database install.
-- Do not import this on top of an existing database that already has Stage 1 tables.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(160) NOT NULL,
  display_name VARCHAR(160) NULL,
  status ENUM('active','disabled','pending') NOT NULL DEFAULT 'active',
  email_verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_profiles (
  user_id BIGINT UNSIGNED NOT NULL,
  avatar_url VARCHAR(500) NULL,
  headline VARCHAR(180) NULL,
  bio TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_user_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_password_reset_user (user_id),
  KEY idx_password_reset_token (token_hash),
  CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_verification_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_email_verify_user (user_id),
  KEY idx_email_verify_token (token_hash),
  CONSTRAINT fk_email_verify_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(80) NOT NULL,
  name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(120) NOT NULL,
  name VARCHAR(160) NOT NULL,
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissions_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(80) NOT NULL DEFAULT 'system',
  metadata_json JSON NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_user (user_id),
  KEY idx_audit_action (action),
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_type VARCHAR(120) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_events_type (event_type),
  KEY idx_events_user (user_id),
  KEY idx_events_created (created_at),
  CONSTRAINT fk_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  migration_key VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  checksum CHAR(64) NULL,
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

CREATE TABLE IF NOT EXISTS accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  name VARCHAR(180) NOT NULL,
  slug VARCHAR(180) NULL,
  account_type VARCHAR(40) NOT NULL DEFAULT 'personal',
  status VARCHAR(40) NOT NULL DEFAULT 'active',
  owner_user_id BIGINT UNSIGNED NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_accounts_public_id (public_id),
  UNIQUE KEY uq_accounts_slug (slug),
  KEY idx_accounts_owner_status (owner_user_id, status),
  KEY idx_accounts_status_created (status, created_at),
  CONSTRAINT fk_accounts_owner_user FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_members (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  member_role VARCHAR(60) NOT NULL DEFAULT 'member',
  status VARCHAR(40) NOT NULL DEFAULT 'active',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_account_members_account_user (account_id, user_id),
  KEY idx_account_members_user_status (user_id, status),
  KEY idx_account_members_account_status (account_id, status),
  CONSTRAINT fk_account_members_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_account_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS outbox_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
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
  available_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  locked_at DATETIME NULL DEFAULT NULL,
  locked_by VARCHAR(120) NULL,
  completed_at DATETIME NULL DEFAULT NULL,
  failed_at DATETIME NULL DEFAULT NULL,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_outbox_events_public_id (public_id),
  KEY idx_outbox_status_available (status, available_at, id),
  KEY idx_outbox_account_status (account_id, status, created_at),
  KEY idx_outbox_owner_status (owner_user_id, status, created_at),
  KEY idx_outbox_aggregate (aggregate_type, aggregate_id),
  CONSTRAINT fk_outbox_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
  CONSTRAINT fk_outbox_owner_user FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS idempotency_keys (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  idempotency_key VARCHAR(160) NOT NULL,
  scope_type VARCHAR(40) NOT NULL,
  scope_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  request_hash CHAR(64) NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'reserved',
  response_status SMALLINT UNSIGNED NULL,
  response_json JSON NULL,
  resource_type VARCHAR(80) NULL,
  resource_id VARCHAR(80) NULL,
  expires_at DATETIME NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_idempotency_scope_key (scope_type, scope_id, idempotency_key),
  KEY idx_idempotency_expires (expires_at),
  KEY idx_idempotency_resource (resource_type, resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS read_model_refreshes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  model_type VARCHAR(80) NOT NULL,
  scope_type VARCHAR(40) NOT NULL,
  scope_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  source_type VARCHAR(80) NULL,
  source_id VARCHAR(80) NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'pending',
  requested_by_event_id BIGINT UNSIGNED NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  available_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL DEFAULT NULL,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_read_model_status_available (status, available_at, id),
  KEY idx_read_model_scope (model_type, scope_type, scope_id),
  KEY idx_read_model_source (source_type, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_event_types (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_key VARCHAR(120) NOT NULL,
  category VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL,
  is_terminal TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_delivery_event_types_key (event_key),
  KEY idx_delivery_event_types_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_status_transitions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  from_status VARCHAR(80) NOT NULL,
  to_status VARCHAR(80) NOT NULL,
  event_key VARCHAR(120) NOT NULL,
  requires_actor TINYINT(1) NOT NULL DEFAULT 0,
  requires_idempotency TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_delivery_transition (from_status, to_status, event_key),
  KEY idx_delivery_transition_event (event_key),
  CONSTRAINT fk_delivery_transition_event FOREIGN KEY (event_key) REFERENCES delivery_event_types(event_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  aggregate_type VARCHAR(80) NOT NULL,
  aggregate_id BIGINT UNSIGNED NULL,
  aggregate_public_id CHAR(26) NULL,
  account_id BIGINT UNSIGNED NULL,
  store_id BIGINT UNSIGNED NULL,
  owner_user_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  event_key VARCHAR(120) NOT NULL,
  from_status VARCHAR(80) NULL,
  to_status VARCHAR(80) NULL,
  idempotency_key VARCHAR(160) NULL,
  request_id VARCHAR(80) NULL,
  source VARCHAR(80) NOT NULL DEFAULT 'api',
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_delivery_events_public_id (public_id),
  UNIQUE KEY uq_delivery_events_idempotency (aggregate_type, aggregate_public_id, event_key, idempotency_key),
  KEY idx_delivery_events_aggregate (aggregate_type, aggregate_id, id),
  KEY idx_delivery_events_public_aggregate (aggregate_type, aggregate_public_id, id),
  KEY idx_delivery_events_account (account_id, id),
  KEY idx_delivery_events_owner (owner_user_id, id),
  KEY idx_delivery_events_event_key (event_key, id),
  KEY idx_delivery_events_created (created_at),
  CONSTRAINT fk_delivery_events_event_key FOREIGN KEY (event_key) REFERENCES delivery_event_types(event_key),
  CONSTRAINT fk_delivery_events_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
  CONSTRAINT fk_delivery_events_owner_user FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_delivery_events_actor_user FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT IGNORE INTO roles (slug, name) VALUES
('customer', 'Customer'),
('merchant', 'Merchant'),
('admin', 'Admin'),
('super_admin', 'Super Admin');

INSERT INTO permissions (slug, name, description) VALUES
('agent.test', 'Create test agent', NULL),
('agent.workspace.view', 'View agent workspace', NULL),
('agent.inbox.view', 'View agent inbox', NULL),
('agent.manage', 'Manage agent workspace', NULL),
('messages.read', 'Read messages', NULL),
('merchant.manage', 'Manage merchant workspace', NULL),
('admin.users.view', 'View users', NULL),
('admin.audit.view', 'View audit logs', NULL),
('user.profile.update', 'Update own user profile', NULL),
('admin.users.manage', 'Manage users', NULL),
('admin.roles.manage', 'Manage user roles', NULL),
('system.health.view', 'View system health', NULL),
('admin.health.view', 'View detailed admin health checks', NULL),
('security.logs.view', 'View security logs', NULL),
('admin.security_logs.view', 'View admin security logs', 'View security log records through the admin API.'),
('security.rate_limits.manage', 'Manage rate limits', NULL),
('security.sessions.manage', 'Manage user sessions', NULL),
('user.sessions.manage', 'Manage own sessions', 'Allow a user to list and manage their own sessions.'),
('admin.sessions.view', 'View user sessions', 'Allow an admin to view user session records.'),
('admin.sessions.revoke', 'Manage user sessions', 'Allow an admin to revoke user session records.'),
('accounts.view', 'View accounts', 'View account records connected to the current user.'),
('accounts.manage', 'Manage accounts', 'Manage account settings and memberships.'),
('outbox.view', 'View outbox', 'View operational outbox events.'),
('outbox.manage', 'Manage outbox', 'Retry, cancel, or inspect operational outbox events.'),
('idempotency.view', 'View idempotency keys', 'View duplicate-protection request records.'),
('read_models.manage', 'Manage read models', 'Manage read-model refresh records.')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT IGNORE INTO system_actors (slug, name, description) VALUES
('platform', 'Microgifter Platform', 'Internal platform/system actor for Stage 1 events.'),
('auth', 'Authentication System', 'System actor for authentication and account lifecycle events.');

INSERT IGNORE INTO platform_fee_settings (fee_key, fee_value, fee_type, is_active, notes) VALUES
('stage_1_placeholder', 0.0000, 'placeholder', 0, 'Placeholder required by Stage 1 plan; real fees are configured in later commerce stages.');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('agent.test') WHERE r.slug = 'customer';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('agent.test','agent.workspace.view','agent.inbox.view','agent.manage','messages.read','merchant.manage','user.profile.update','user.sessions.manage','accounts.view') WHERE r.slug = 'customer';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('agent.test','agent.workspace.view','agent.inbox.view','agent.manage','messages.read','merchant.manage','user.profile.update','user.sessions.manage','accounts.view','accounts.manage') WHERE r.slug = 'merchant';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p WHERE r.slug = 'admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p WHERE r.slug = 'super_admin';

INSERT IGNORE INTO schema_migrations (migration_key, description) VALUES
('stage_1_identity', 'Compiled baseline identity schema included.'),
('03M_stage1_repair', 'Compiled Stage 1 repair tables included.'),
('03N_security_stability_hardening', 'Compiled rate limits, security logs, and security permissions included.'),
('03N_3_global_session_enforcement', 'Compiled session-management permissions included.'),
('03O_high_volume_data_foundation', 'Compiled account, outbox, idempotency, and read model tables included.'),
('03R_delivery_event_contracts', 'Compiled delivery event contract tables included.');

INSERT IGNORE INTO delivery_event_types (event_key, category, description, is_terminal) VALUES
('gift.created', 'gift', 'Gift created as durable state.', 0),
('gift.updated', 'gift', 'Gift details updated.', 0),
('gift.validated', 'delivery', 'Gift passed delivery validation.', 0),
('gift.sent', 'delivery', 'Gift entered sent state.', 0),
('gift.delivery.delayed', 'delivery', 'Gift delivery was delayed.', 0),
('gift.delivery.failed', 'delivery', 'Gift delivery attempt failed.', 0),
('gift.delivery.retried', 'delivery', 'Gift delivery was retried.', 0),
('gift.notification.queued', 'notification', 'Gift notification was queued.', 0),
('gift.notification.sent', 'notification', 'Gift notification was sent.', 0),
('gift.notification.failed', 'notification', 'Gift notification failed.', 0),
('gift.notification.opened', 'notification', 'Gift notification was opened.', 0),
('gift.opened', 'recipient', 'Recipient opened gift.', 0),
('gift.viewed', 'recipient', 'Recipient viewed gift content.', 0),
('gift.claim.started', 'claim', 'Gift claim started.', 0),
('gift.claim.verified', 'claim', 'Gift claim verified.', 0),
('gift.claim.confirmed', 'claim', 'Gift claim confirmed.', 0),
('gift.claim.rejected', 'claim', 'Gift claim rejected.', 1),
('gift.fulfillment.started', 'fulfillment', 'Fulfillment started.', 0),
('gift.fulfilled', 'fulfillment', 'Gift fulfilled.', 1),
('gift.fulfillment.failed', 'fulfillment', 'Gift fulfillment failed.', 0),
('gift.verification.required', 'verification', 'Verification required.', 0),
('gift.verification.passed', 'verification', 'Verification passed.', 0),
('gift.verification.failed', 'verification', 'Verification failed.', 0),
('gift.fraud_review.required', 'verification', 'Fraud review required.', 0),
('gift.fraud_review.cleared', 'verification', 'Fraud review cleared.', 0),
('gift.fraud_review.blocked', 'verification', 'Fraud review blocked.', 1),
('gift.expired', 'gift', 'Gift expired.', 1),
('gift.cancelled', 'gift', 'Gift cancelled.', 1),
('gift.failed', 'gift', 'Gift failed.', 1);

INSERT IGNORE INTO delivery_status_transitions (from_status, to_status, event_key, requires_actor, requires_idempotency) VALUES
('draft', 'created', 'gift.created', 1, 1),
('created', 'validated', 'gift.validated', 0, 1),
('validated', 'sent', 'gift.sent', 1, 1),
('sent', 'opened', 'gift.opened', 0, 1),
('opened', 'claim_started', 'gift.claim.started', 0, 1),
('claim_started', 'claim_verified', 'gift.claim.verified', 0, 1),
('claim_verified', 'claimed', 'gift.claim.confirmed', 0, 1),
('claimed', 'fulfilled', 'gift.fulfilled', 1, 1),
('created', 'cancelled', 'gift.cancelled', 1, 1),
('validated', 'cancelled', 'gift.cancelled', 1, 1),
('sent', 'cancelled', 'gift.cancelled', 1, 1),
('created', 'expired', 'gift.expired', 0, 1),
('validated', 'expired', 'gift.expired', 0, 1),
('sent', 'expired', 'gift.expired', 0, 1),
('opened', 'expired', 'gift.expired', 0, 1),
('claim_started', 'expired', 'gift.expired', 0, 1),
('created', 'failed', 'gift.failed', 0, 1),
('validated', 'failed', 'gift.failed', 0, 1),
('sent', 'failed', 'gift.failed', 0, 1),
('claim_started', 'failed', 'gift.failed', 0, 1);
