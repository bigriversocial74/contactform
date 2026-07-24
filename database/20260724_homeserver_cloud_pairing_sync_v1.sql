-- Microgifter HomeServer cloud pairing and synchronization v1
-- Additive MySQL 8 migration. Cloud remains authoritative for all commerce state.

CREATE TABLE IF NOT EXISTS homeserver_devices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  installation_id CHAR(36) NOT NULL,
  server_name VARCHAR(128) NOT NULL,
  version VARCHAR(32) NOT NULL,
  public_key_base64 VARCHAR(64) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  token_last_four CHAR(4) NOT NULL,
  scopes_json JSON NOT NULL,
  status ENUM('active','revoked') NOT NULL DEFAULT 'active',
  metadata_json JSON NULL,
  paired_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_homeserver_devices_public (public_id),
  UNIQUE KEY uq_homeserver_devices_installation (installation_id),
  UNIQUE KEY uq_homeserver_devices_token (token_hash),
  KEY idx_homeserver_devices_owner (owner_user_id, status, updated_at),
  CONSTRAINT fk_homeserver_devices_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homeserver_pairing_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  code_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  consumed_device_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_homeserver_pairing_codes_public (public_id),
  UNIQUE KEY uq_homeserver_pairing_codes_hash (code_hash),
  KEY idx_homeserver_pairing_codes_owner (owner_user_id, expires_at),
  CONSTRAINT fk_homeserver_pairing_codes_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_homeserver_pairing_codes_device FOREIGN KEY (consumed_device_id) REFERENCES homeserver_devices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homeserver_request_nonces (
  device_id BIGINT UNSIGNED NOT NULL,
  nonce VARCHAR(80) NOT NULL,
  requested_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (device_id, nonce),
  KEY idx_homeserver_request_nonces_expiry (expires_at),
  CONSTRAINT fk_homeserver_request_nonces_device FOREIGN KEY (device_id) REFERENCES homeserver_devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homeserver_sync_receipts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  device_id BIGINT UNSIGNED NOT NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  operation_type VARCHAR(100) NOT NULL,
  request_hash CHAR(64) NOT NULL,
  disposition ENUM('accepted','rejected','review') NOT NULL,
  reason_code VARCHAR(100) NULL,
  response_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_homeserver_sync_receipts_public (public_id),
  UNIQUE KEY uq_homeserver_sync_receipts_idempotency (device_id, idempotency_key),
  KEY idx_homeserver_sync_receipts_device (device_id, created_at),
  CONSTRAINT fk_homeserver_sync_receipts_device FOREIGN KEY (device_id) REFERENCES homeserver_devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
