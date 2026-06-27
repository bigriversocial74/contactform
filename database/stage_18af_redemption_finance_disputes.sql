-- Stage 18AF Redemption Finance and Disputes

CREATE TABLE IF NOT EXISTS redemption_settlement_ledger (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  receipt_public_id CHAR(36) NOT NULL,
  receipt_type ENUM('microgift','legacy_gift') NOT NULL DEFAULT 'microgift',
  gift_public_id VARCHAR(80) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  scanner_location_id BIGINT UNSIGNED NULL,
  location_public_id CHAR(36) NULL,
  amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
  platform_fee_cents INT UNSIGNED NOT NULL DEFAULT 0,
  merchant_net_cents INT UNSIGNED NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  settlement_status ENUM('pending','ready','settled','held','voided','reversed') NOT NULL DEFAULT 'pending',
  payout_reference VARCHAR(120) NULL,
  settled_at DATETIME NULL,
  hold_reason VARCHAR(255) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_redemption_settlement_ledger_public_id (public_id),
  UNIQUE KEY uq_redemption_settlement_ledger_receipt (receipt_public_id),
  KEY idx_redemption_settlement_merchant (merchant_user_id,settlement_status,created_at),
  KEY idx_redemption_settlement_location (scanner_location_id,settlement_status,created_at),
  KEY idx_redemption_settlement_gift (gift_public_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS redemption_disputes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  receipt_public_id CHAR(36) NOT NULL,
  settlement_public_id CHAR(36) NULL,
  dispute_type ENUM('customer_dispute','merchant_void','admin_review','refund_request','duplicate_scan','other') NOT NULL DEFAULT 'other',
  reason VARCHAR(255) NOT NULL,
  status ENUM('open','merchant_review','admin_review','voided','refunded','reversed','dismissed','resolved') NOT NULL DEFAULT 'open',
  opened_by_user_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  customer_user_id BIGINT UNSIGNED NULL,
  admin_user_id BIGINT UNSIGNED NULL,
  merchant_response TEXT NULL,
  admin_notes TEXT NULL,
  resolution VARCHAR(255) NULL,
  voided_at DATETIME NULL,
  refunded_at DATETIME NULL,
  reversed_at DATETIME NULL,
  resolved_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_redemption_disputes_public_id (public_id),
  KEY idx_redemption_disputes_receipt (receipt_public_id,status),
  KEY idx_redemption_disputes_merchant (merchant_user_id,status,created_at),
  KEY idx_redemption_disputes_customer (customer_user_id,status,created_at),
  KEY idx_redemption_disputes_status (status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_18af_redemption_finance_disputes','Adds redemption settlement ledger and dispute workflow tables.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
