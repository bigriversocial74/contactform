-- Microgifter MCP external agent authorization Phase 2A.
-- Import after database/20260720_microgifter_mcp_automation_foundation_v1.sql.
-- OAuth 2.1 authorization code + PKCE, rotating refresh tokens, consent, and token evidence.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS mcp_oauth_client_registrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  mcp_client_id BIGINT UNSIGNED NOT NULL,
  client_id VARCHAR(255) NOT NULL,
  client_name VARCHAR(180) NOT NULL,
  client_uri VARCHAR(500) NULL,
  logo_uri VARCHAR(500) NULL,
  status ENUM('active','paused','disabled','revoked') NOT NULL DEFAULT 'active',
  registration_type ENUM('dynamic','preregistered') NOT NULL DEFAULT 'dynamic',
  redirect_uris_json JSON NOT NULL,
  grant_types_json JSON NOT NULL,
  response_types_json JSON NOT NULL,
  token_endpoint_auth_method ENUM('none') NOT NULL DEFAULT 'none',
  registration_access_token_hash CHAR(64) NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  last_used_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mcp_oauth_client_reg_public_id (public_id),
  UNIQUE KEY uq_mcp_oauth_client_reg_client_id (client_id),
  UNIQUE KEY uq_mcp_oauth_client_reg_mcp_client (mcp_client_id),
  KEY idx_mcp_oauth_client_reg_status (status,registration_type,updated_at),
  CONSTRAINT fk_mcp_oauth_client_reg_client FOREIGN KEY (mcp_client_id) REFERENCES mcp_clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_mcp_oauth_client_reg_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mcp_oauth_authorization_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  client_registration_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  workspace_type VARCHAR(40) NULL,
  workspace_id BIGINT UNSIGNED NULL,
  redirect_uri VARCHAR(1000) NOT NULL,
  resource_uri VARCHAR(500) NOT NULL,
  state_value VARCHAR(512) NOT NULL,
  scope_json JSON NOT NULL,
  code_challenge VARCHAR(128) NOT NULL,
  code_challenge_method ENUM('S256') NOT NULL DEFAULT 'S256',
  status ENUM('pending','approved','denied','expired','consumed') NOT NULL DEFAULT 'pending',
  expires_at DATETIME NOT NULL,
  decided_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mcp_oauth_auth_req_public_id (public_id),
  KEY idx_mcp_oauth_auth_req_pending (status,expires_at,created_at),
  KEY idx_mcp_oauth_auth_req_user (user_id,status,created_at),
  CONSTRAINT fk_mcp_oauth_auth_req_client FOREIGN KEY (client_registration_id) REFERENCES mcp_oauth_client_registrations(id) ON DELETE CASCADE,
  CONSTRAINT fk_mcp_oauth_auth_req_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mcp_oauth_consents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  client_registration_id BIGINT UNSIGNED NOT NULL,
  connection_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  workspace_key VARCHAR(120) NOT NULL,
  workspace_type VARCHAR(40) NULL,
  workspace_id BIGINT UNSIGNED NULL,
  scope_json JSON NOT NULL,
  scope_fingerprint CHAR(64) NOT NULL,
  status ENUM('active','revoked') NOT NULL DEFAULT 'active',
  consented_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  revocation_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mcp_oauth_consents_public_id (public_id),
  UNIQUE KEY uq_mcp_oauth_consents_subject (client_registration_id,user_id,workspace_key),
  UNIQUE KEY uq_mcp_oauth_consents_connection (connection_id),
  KEY idx_mcp_oauth_consents_user (user_id,status,updated_at),
  KEY idx_mcp_oauth_consents_client (client_registration_id,status,updated_at),
  CONSTRAINT fk_mcp_oauth_consents_client FOREIGN KEY (client_registration_id) REFERENCES mcp_oauth_client_registrations(id) ON DELETE CASCADE,
  CONSTRAINT fk_mcp_oauth_consents_connection FOREIGN KEY (connection_id) REFERENCES mcp_connections(id) ON DELETE CASCADE,
  CONSTRAINT fk_mcp_oauth_consents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mcp_oauth_authorization_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  authorization_request_id BIGINT UNSIGNED NOT NULL,
  client_registration_id BIGINT UNSIGNED NOT NULL,
  connection_id BIGINT UNSIGNED NOT NULL,
  code_hash CHAR(64) NOT NULL,
  redirect_uri VARCHAR(1000) NOT NULL,
  resource_uri VARCHAR(500) NOT NULL,
  scope_json JSON NOT NULL,
  code_challenge VARCHAR(128) NOT NULL,
  code_challenge_method ENUM('S256') NOT NULL DEFAULT 'S256',
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mcp_oauth_codes_public_id (public_id),
  UNIQUE KEY uq_mcp_oauth_codes_hash (code_hash),
  KEY idx_mcp_oauth_codes_exchange (client_registration_id,consumed_at,expires_at),
  CONSTRAINT fk_mcp_oauth_codes_request FOREIGN KEY (authorization_request_id) REFERENCES mcp_oauth_authorization_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_mcp_oauth_codes_client FOREIGN KEY (client_registration_id) REFERENCES mcp_oauth_client_registrations(id) ON DELETE CASCADE,
  CONSTRAINT fk_mcp_oauth_codes_connection FOREIGN KEY (connection_id) REFERENCES mcp_connections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mcp_oauth_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  client_registration_id BIGINT UNSIGNED NOT NULL,
  connection_id BIGINT UNSIGNED NOT NULL,
  token_family_id CHAR(36) NOT NULL,
  token_type ENUM('access','refresh') NOT NULL,
  token_hash CHAR(64) NOT NULL,
  parent_token_id BIGINT UNSIGNED NULL,
  resource_uri VARCHAR(500) NOT NULL,
  scope_json JSON NOT NULL,
  connection_token_version INT UNSIGNED NOT NULL,
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  last_used_at DATETIME NULL,
  revoked_at DATETIME NULL,
  revocation_reason VARCHAR(120) NULL,
  replaced_by_token_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mcp_oauth_tokens_public_id (public_id),
  UNIQUE KEY uq_mcp_oauth_tokens_hash (token_hash),
  KEY idx_mcp_oauth_tokens_lookup (token_type,token_hash,revoked_at,expires_at),
  KEY idx_mcp_oauth_tokens_family (token_family_id,revoked_at,expires_at),
  KEY idx_mcp_oauth_tokens_connection (connection_id,token_type,revoked_at,expires_at),
  CONSTRAINT fk_mcp_oauth_tokens_client FOREIGN KEY (client_registration_id) REFERENCES mcp_oauth_client_registrations(id) ON DELETE CASCADE,
  CONSTRAINT fk_mcp_oauth_tokens_connection FOREIGN KEY (connection_id) REFERENCES mcp_connections(id) ON DELETE CASCADE,
  CONSTRAINT fk_mcp_oauth_tokens_parent FOREIGN KEY (parent_token_id) REFERENCES mcp_oauth_tokens(id) ON DELETE SET NULL,
  CONSTRAINT fk_mcp_oauth_tokens_replaced_by FOREIGN KEY (replaced_by_token_id) REFERENCES mcp_oauth_tokens(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  '20260720_mcp_external_agent_authorization_phase2a_v1',
  'MCP OAuth client registration, authorization requests, consent, authorization codes, hashed access tokens, and rotating refresh token families.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
