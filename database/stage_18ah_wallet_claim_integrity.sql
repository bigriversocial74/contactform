-- Stage 18AH Wallet Claim Integrity
-- Signed wallet reward QR tokens, redemption ledger, manual attempt logging, and claim-code lookup index.

CREATE TABLE IF NOT EXISTS wallet_claim_voucher_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  wallet_item_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  status ENUM('issued','scanned','redeemed','revoked','expired') NOT NULL DEFAULT 'issued',
  expires_at DATETIME NOT NULL,
  first_scanned_at DATETIME NULL,
  last_scanned_at DATETIME NULL,
  scan_count INT UNSIGNED NOT NULL DEFAULT 0,
  scanner_user_id BIGINT UNSIGNED NULL,
  scanner_location_id BIGINT UNSIGNED NULL,
  redeemed_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_ip_hash CHAR(64) NULL,
  created_user_agent_hash CHAR(64) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wallet_claim_voucher_tokens_public_id (public_id),
  UNIQUE KEY uq_wallet_claim_voucher_tokens_hash (token_hash),
  KEY idx_wallet_claim_voucher_wallet_status (wallet_item_id,status,expires_at),
  KEY idx_wallet_claim_voucher_user_status (user_id,status,created_at),
  KEY idx_wallet_claim_voucher_merchant_status (merchant_user_id,status,created_at),
  KEY idx_wallet_claim_voucher_scanner (scanner_user_id,scanner_location_id,last_scanned_at),
  CONSTRAINT fk_wallet_claim_voucher_wallet FOREIGN KEY (wallet_item_id) REFERENCES wallet_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_wallet_claim_voucher_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wallet_claim_voucher_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wallet_claim_voucher_scanner_user FOREIGN KEY (scanner_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_wallet_claim_voucher_scanner_location FOREIGN KEY (scanner_location_id) REFERENCES merchant_locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_item_redemptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  wallet_item_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NULL,
  location_reference VARCHAR(190) NULL,
  amount_cents BIGINT UNSIGNED NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  status ENUM('completed','reversed','refunded','rejected') NOT NULL DEFAULT 'completed',
  idempotency_key VARCHAR(190) NOT NULL,
  source_reference VARCHAR(190) NOT NULL,
  redeemed_at DATETIME NOT NULL,
  reversed_at DATETIME NULL,
  refund_reference VARCHAR(190) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wallet_item_redemptions_public_id (public_id),
  UNIQUE KEY uq_wallet_item_redemptions_idempotency (idempotency_key),
  UNIQUE KEY uq_wallet_item_redemptions_completed (wallet_item_id,status),
  KEY idx_wallet_item_redemptions_wallet_status (wallet_item_id,status,redeemed_at),
  KEY idx_wallet_item_redemptions_merchant (merchant_user_id,redeemed_at),
  KEY idx_wallet_item_redemptions_user (user_id,redeemed_at),
  CONSTRAINT fk_wallet_item_redemptions_wallet FOREIGN KEY (wallet_item_id) REFERENCES wallet_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wallet_item_redemptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_wallet_item_redemptions_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wallet_item_redemptions_location FOREIGN KEY (location_id) REFERENCES merchant_locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS action_center_voucher_claim_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  action_item_id VARCHAR(120) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  wallet_item_id BIGINT UNSIGNED NULL,
  microgift_instance_id BIGINT UNSIGNED NULL,
  successful TINYINT(1) NOT NULL DEFAULT 0,
  failure_reason VARCHAR(120) NULL,
  ip_hash CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_action_center_voucher_attempts_public_id (public_id),
  KEY idx_ac_voucher_attempts_action_user (action_item_id,user_id,created_at),
  KEY idx_ac_voucher_attempts_ip (ip_hash,created_at),
  KEY idx_ac_voucher_attempts_wallet (wallet_item_id,created_at),
  KEY idx_ac_voucher_attempts_microgift (microgift_instance_id,created_at),
  CONSTRAINT fk_ac_voucher_attempts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ac_voucher_attempts_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ac_voucher_attempts_wallet FOREIGN KEY (wallet_item_id) REFERENCES wallet_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_ac_voucher_attempts_microgift FOREIGN KEY (microgift_instance_id) REFERENCES microgift_instances(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @mg_has_mcc_claim_lookup := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_claim_codes' AND INDEX_NAME = 'idx_merchant_claim_codes_lookup_hash'
);
SET @mg_sql := IF(
  @mg_has_mcc_claim_lookup = 0,
  'ALTER TABLE merchant_claim_codes ADD KEY idx_merchant_claim_codes_lookup_hash (merchant_user_id, code_hash, status, location_id)',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_18ah_wallet_claim_integrity',
  'Adds signed wallet reward QR tokens, wallet redemption ledger, manual voucher claim attempts, and merchant claim-code lookup index.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);