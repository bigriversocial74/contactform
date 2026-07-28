-- HomeServer pairing, entitlement, device lifecycle, and update authorization contract v1.
-- Non-destructive MySQL 8 / MariaDB 10.11 compatible migration.

-- A single physical installation may carry multiple isolated Microgifter/provider
-- connections. Preserve installation lookup while removing the legacy one-row-only
-- constraint. Package limits count distinct installation_id values in application code.
SET @mg_hs_installation_unique_exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=DATABASE()
    AND table_name='homeserver_devices'
    AND index_name='uq_homeserver_devices_installation'
);
SET @mg_hs_drop_installation_unique_sql := IF(
  @mg_hs_installation_unique_exists > 0,
  'ALTER TABLE homeserver_devices DROP INDEX uq_homeserver_devices_installation',
  'SELECT 1'
);
PREPARE mg_hs_drop_installation_unique_stmt FROM @mg_hs_drop_installation_unique_sql;
EXECUTE mg_hs_drop_installation_unique_stmt;
DEALLOCATE PREPARE mg_hs_drop_installation_unique_stmt;

SET @mg_hs_installation_index_exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=DATABASE()
    AND table_name='homeserver_devices'
    AND index_name='idx_homeserver_devices_installation'
);
SET @mg_hs_add_installation_index_sql := IF(
  @mg_hs_installation_index_exists = 0,
  'ALTER TABLE homeserver_devices ADD KEY idx_homeserver_devices_installation (installation_id,owner_user_id,status)',
  'SELECT 1'
);
PREPARE mg_hs_add_installation_index_stmt FROM @mg_hs_add_installation_index_sql;
EXECUTE mg_hs_add_installation_index_stmt;
DEALLOCATE PREPARE mg_hs_add_installation_index_stmt;

CREATE TABLE IF NOT EXISTS homeserver_provider_connections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  device_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  local_connection_id CHAR(36) NULL,
  contract_version VARCHAR(16) NOT NULL DEFAULT 'v1',
  lifecycle_state ENUM('pairing_pending','active','offline','grace','suspended','revoked','replacing','error') NOT NULL DEFAULT 'pairing_pending',
  subscription_state ENUM('active','grace','suspended','canceled','unknown') NOT NULL DEFAULT 'unknown',
  requested_capabilities_json JSON NOT NULL,
  granted_capabilities_json JSON NOT NULL,
  denied_capabilities_json JSON NOT NULL,
  merchant_scope_json JSON NOT NULL,
  site_scope_json JSON NOT NULL,
  current_lease_id CHAR(36) NULL,
  entitlement_expires_at DATETIME NULL,
  update_eligible TINYINT(1) NOT NULL DEFAULT 0,
  update_channels_json JSON NOT NULL,
  last_heartbeat_at DATETIME NULL,
  last_entitlement_refresh_at DATETIME NULL,
  last_credential_rotation_at DATETIME NULL,
  last_update_authorization_at DATETIME NULL,
  replacement_state ENUM('none','pending','paired','completed','failed') NOT NULL DEFAULT 'none',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_homeserver_provider_connections_public (public_id),
  UNIQUE KEY uq_homeserver_provider_connections_device (device_id),
  UNIQUE KEY uq_homeserver_provider_connections_local (local_connection_id),
  KEY idx_homeserver_provider_connections_owner_state (owner_user_id,lifecycle_state,updated_at),
  CONSTRAINT fk_homeserver_provider_connections_device FOREIGN KEY (device_id) REFERENCES homeserver_devices(id) ON DELETE CASCADE,
  CONSTRAINT fk_homeserver_provider_connections_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homeserver_pairing_exchanges_v1 (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  request_id CHAR(36) NOT NULL,
  pairing_code_id BIGINT UNSIGNED NOT NULL,
  device_id BIGINT UNSIGNED NULL,
  provider_connection_id BIGINT UNSIGNED NULL,
  request_fingerprint_hash CHAR(64) NOT NULL,
  response_ciphertext MEDIUMTEXT NULL,
  response_nonce VARCHAR(80) NULL,
  response_expires_at DATETIME NULL,
  state ENUM('pending','completed','failed','expired') NOT NULL DEFAULT 'pending',
  error_code VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_homeserver_pairing_exchanges_public (public_id),
  UNIQUE KEY uq_homeserver_pairing_exchanges_request (request_id),
  KEY idx_homeserver_pairing_exchanges_state_expiry (state,response_expires_at),
  CONSTRAINT fk_homeserver_pairing_exchanges_code FOREIGN KEY (pairing_code_id) REFERENCES homeserver_pairing_codes(id) ON DELETE CASCADE,
  CONSTRAINT fk_homeserver_pairing_exchanges_device FOREIGN KEY (device_id) REFERENCES homeserver_devices(id) ON DELETE SET NULL,
  CONSTRAINT fk_homeserver_pairing_exchanges_connection FOREIGN KEY (provider_connection_id) REFERENCES homeserver_provider_connections(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homeserver_device_credentials (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_id BIGINT UNSIGNED NOT NULL,
  credential_version INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  token_last_four CHAR(4) NOT NULL,
  state ENUM('current','previous','revoked') NOT NULL DEFAULT 'current',
  valid_until DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_homeserver_device_credentials_token (token_hash),
  UNIQUE KEY uq_homeserver_device_credentials_version (device_id,credential_version),
  KEY idx_homeserver_device_credentials_device_state (device_id,state,valid_until),
  CONSTRAINT fk_homeserver_device_credentials_device FOREIGN KEY (device_id) REFERENCES homeserver_devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homeserver_credential_rotations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  request_id CHAR(36) NOT NULL,
  device_id BIGINT UNSIGNED NOT NULL,
  request_hash CHAR(64) NOT NULL,
  credential_version INT UNSIGNED NOT NULL,
  response_ciphertext TEXT NOT NULL,
  response_nonce VARCHAR(80) NOT NULL,
  response_expires_at DATETIME NOT NULL,
  state ENUM('completed','expired','failed') NOT NULL DEFAULT 'completed',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_homeserver_credential_rotations_public (public_id),
  UNIQUE KEY uq_homeserver_credential_rotations_request (request_id),
  KEY idx_homeserver_credential_rotations_device (device_id,created_at),
  CONSTRAINT fk_homeserver_credential_rotations_device FOREIGN KEY (device_id) REFERENCES homeserver_devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homeserver_entitlement_leases_v1 (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  provider_connection_id BIGINT UNSIGNED NOT NULL,
  device_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  account_id VARCHAR(190) NOT NULL,
  schema_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  subscription_state ENUM('active','grace','suspended','canceled','unknown') NOT NULL,
  granted_capabilities_json JSON NOT NULL,
  denied_capabilities_json JSON NOT NULL,
  merchant_scope_json JSON NOT NULL,
  site_scope_json JSON NOT NULL,
  device_allowance_json JSON NOT NULL,
  update_eligibility TINYINT(1) NOT NULL DEFAULT 0,
  allowed_update_channels_json JSON NOT NULL,
  minimum_homeserver_version VARCHAR(40) NULL,
  signing_key_id VARCHAR(120) NOT NULL,
  payload_json JSON NOT NULL,
  signature_base64 VARCHAR(160) NOT NULL,
  issued_at DATETIME NOT NULL,
  not_before_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  state ENUM('active','superseded','expired','revoked') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_homeserver_entitlement_leases_public (public_id),
  KEY idx_homeserver_entitlement_leases_connection_state (provider_connection_id,state,expires_at),
  KEY idx_homeserver_entitlement_leases_device (device_id,expires_at),
  CONSTRAINT fk_homeserver_entitlement_leases_connection FOREIGN KEY (provider_connection_id) REFERENCES homeserver_provider_connections(id) ON DELETE CASCADE,
  CONSTRAINT fk_homeserver_entitlement_leases_device FOREIGN KEY (device_id) REFERENCES homeserver_devices(id) ON DELETE CASCADE,
  CONSTRAINT fk_homeserver_entitlement_leases_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homeserver_update_authorizations_v1 (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  request_id CHAR(36) NOT NULL,
  provider_connection_id BIGINT UNSIGNED NOT NULL,
  device_id BIGINT UNSIGNED NOT NULL,
  update_id VARCHAR(190) NOT NULL,
  version VARCHAR(40) NOT NULL,
  update_class ENUM('bootstrap','security','maintenance','feature','preview','recovery') NOT NULL,
  release_channel ENUM('stable','beta','preview') NOT NULL,
  decision ENUM('authorized','denied','not_required') NOT NULL,
  reason_code VARCHAR(120) NULL,
  issued_at DATETIME NOT NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_homeserver_update_authorizations_public (public_id),
  UNIQUE KEY uq_homeserver_update_authorizations_request (request_id),
  KEY idx_homeserver_update_authorizations_device (device_id,created_at),
  KEY idx_homeserver_update_authorizations_update (update_id,decision,expires_at),
  CONSTRAINT fk_homeserver_update_authorizations_connection FOREIGN KEY (provider_connection_id) REFERENCES homeserver_provider_connections(id) ON DELETE CASCADE,
  CONSTRAINT fk_homeserver_update_authorizations_device FOREIGN KEY (device_id) REFERENCES homeserver_devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homeserver_update_receipts_v1 (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  request_id CHAR(36) NOT NULL,
  provider_connection_id BIGINT UNSIGNED NOT NULL,
  device_id BIGINT UNSIGNED NOT NULL,
  receipt_key VARCHAR(190) NOT NULL,
  receipt_type VARCHAR(100) NOT NULL,
  update_id VARCHAR(190) NULL,
  version VARCHAR(40) NULL,
  disposition VARCHAR(80) NULL,
  payload_hash CHAR(64) NOT NULL,
  payload_json JSON NOT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_homeserver_update_receipts_public (public_id),
  UNIQUE KEY uq_homeserver_update_receipts_idempotency (device_id,receipt_key),
  KEY idx_homeserver_update_receipts_update (update_id,received_at),
  CONSTRAINT fk_homeserver_update_receipts_connection FOREIGN KEY (provider_connection_id) REFERENCES homeserver_provider_connections(id) ON DELETE CASCADE,
  CONSTRAINT fk_homeserver_update_receipts_device FOREIGN KEY (device_id) REFERENCES homeserver_devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homeserver_device_replacements_v1 (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  request_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  old_device_id BIGINT UNSIGNED NOT NULL,
  old_provider_connection_id BIGINT UNSIGNED NOT NULL,
  pairing_code_id BIGINT UNSIGNED NOT NULL,
  new_device_id BIGINT UNSIGNED NULL,
  new_provider_connection_id BIGINT UNSIGNED NULL,
  requested_device_name VARCHAR(128) NOT NULL,
  response_ciphertext TEXT NOT NULL,
  response_nonce VARCHAR(80) NOT NULL,
  response_expires_at DATETIME NOT NULL,
  state ENUM('pending','paired','completed','failed','canceled','expired') NOT NULL DEFAULT 'pending',
  expires_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  failure_code VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_homeserver_device_replacements_public (public_id),
  UNIQUE KEY uq_homeserver_device_replacements_request (request_id),
  KEY idx_homeserver_device_replacements_owner_state (owner_user_id,state,expires_at),
  CONSTRAINT fk_homeserver_device_replacements_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_homeserver_device_replacements_old_device FOREIGN KEY (old_device_id) REFERENCES homeserver_devices(id) ON DELETE RESTRICT,
  CONSTRAINT fk_homeserver_device_replacements_old_connection FOREIGN KEY (old_provider_connection_id) REFERENCES homeserver_provider_connections(id) ON DELETE RESTRICT,
  CONSTRAINT fk_homeserver_device_replacements_code FOREIGN KEY (pairing_code_id) REFERENCES homeserver_pairing_codes(id) ON DELETE RESTRICT,
  CONSTRAINT fk_homeserver_device_replacements_new_device FOREIGN KEY (new_device_id) REFERENCES homeserver_devices(id) ON DELETE SET NULL,
  CONSTRAINT fk_homeserver_device_replacements_new_connection FOREIGN KEY (new_provider_connection_id) REFERENCES homeserver_provider_connections(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homeserver_connection_receipts_v1 (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  provider_connection_id BIGINT UNSIGNED NULL,
  device_id BIGINT UNSIGNED NULL,
  owner_user_id BIGINT UNSIGNED NULL,
  request_id CHAR(36) NULL,
  event_type VARCHAR(100) NOT NULL,
  previous_state VARCHAR(40) NULL,
  new_state VARCHAR(40) NULL,
  result_category ENUM('success','warning','error','denied') NOT NULL,
  error_category VARCHAR(120) NULL,
  metadata_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_homeserver_connection_receipts_public (public_id),
  KEY idx_homeserver_connection_receipts_connection (provider_connection_id,created_at),
  KEY idx_homeserver_connection_receipts_owner (owner_user_id,created_at),
  CONSTRAINT fk_homeserver_connection_receipts_connection FOREIGN KEY (provider_connection_id) REFERENCES homeserver_provider_connections(id) ON DELETE SET NULL,
  CONSTRAINT fk_homeserver_connection_receipts_device FOREIGN KEY (device_id) REFERENCES homeserver_devices(id) ON DELETE SET NULL,
  CONSTRAINT fk_homeserver_connection_receipts_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
