-- Stage 18AD Scanner Trust Operations
-- Adds merchant scanner confirmation, redemption receipt, and risk/audit infrastructure.

CREATE TABLE IF NOT EXISTS scanner_redemption_receipts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  receipt_type ENUM('microgift','legacy_gift') NOT NULL DEFAULT 'microgift',
  gift_public_id VARCHAR(80) NOT NULL,
  redemption_public_id CHAR(36) NULL,
  claim_public_id CHAR(36) NULL,
  customer_user_id BIGINT UNSIGNED NULL,
  sender_user_id BIGINT UNSIGNED NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  scanner_user_id BIGINT UNSIGNED NOT NULL,
  scanner_location_id BIGINT UNSIGNED NULL,
  location_public_id CHAR(36) NULL,
  location_name VARCHAR(160) NULL,
  claim_code_last4 VARCHAR(12) NULL,
  amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  status ENUM('issued','completed','voided','disputed') NOT NULL DEFAULT 'completed',
  receipt_payload_json JSON NULL,
  redeemed_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_scanner_redemption_receipts_public_id (public_id),
  KEY idx_scanner_receipts_gift (gift_public_id,created_at),
  KEY idx_scanner_receipts_customer (customer_user_id,created_at),
  KEY idx_scanner_receipts_merchant (merchant_user_id,scanner_location_id,created_at),
  KEY idx_scanner_receipts_redemption (redemption_public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scanner_risk_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
  risk_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  gift_public_id VARCHAR(80) NULL,
  voucher_token_public_id CHAR(36) NULL,
  receipt_public_id CHAR(36) NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  scanner_user_id BIGINT UNSIGNED NULL,
  scanner_location_id BIGINT UNSIGNED NULL,
  location_public_id CHAR(36) NULL,
  scan_hash CHAR(64) NULL,
  ip_hash CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  details_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_scanner_risk_events_public_id (public_id),
  KEY idx_scanner_risk_events_created (created_at),
  KEY idx_scanner_risk_events_severity (severity,risk_score,created_at),
  KEY idx_scanner_risk_events_gift (gift_public_id,created_at),
  KEY idx_scanner_risk_events_merchant (merchant_user_id,scanner_location_id,created_at),
  KEY idx_scanner_risk_events_token (voucher_token_public_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_18ad_scanner_trust_operations',
  'Adds scanner redemption receipts and scanner risk events for merchant QR redemption trust operations.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);
