-- Stage 18AC Claim Voucher QR Token Ledger
-- First-party QR redemption security for customer voucher scans.

CREATE TABLE IF NOT EXISTS claim_voucher_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  action_item_id BIGINT UNSIGNED NOT NULL,
  instance_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
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
  UNIQUE KEY uq_claim_voucher_tokens_public_id (public_id),
  UNIQUE KEY uq_claim_voucher_tokens_hash (token_hash),
  KEY idx_claim_voucher_tokens_action_item (action_item_id,status,expires_at),
  KEY idx_claim_voucher_tokens_user_status (user_id,status,created_at),
  KEY idx_claim_voucher_tokens_instance_status (instance_id,status,created_at),
  KEY idx_claim_voucher_tokens_scanner (scanner_user_id,scanner_location_id,last_scanned_at),
  CONSTRAINT fk_claim_voucher_tokens_action_item FOREIGN KEY (action_item_id) REFERENCES microgift_inbox_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_claim_voucher_tokens_instance FOREIGN KEY (instance_id) REFERENCES microgift_instances(id) ON DELETE CASCADE,
  CONSTRAINT fk_claim_voucher_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_claim_voucher_tokens_scanner_user FOREIGN KEY (scanner_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_claim_voucher_tokens_scanner_location FOREIGN KEY (scanner_location_id) REFERENCES merchant_locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_18ac_claim_voucher_tokens',
  'Adds first-party, database-backed customer voucher QR tokens for merchant scanner redemption.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);
