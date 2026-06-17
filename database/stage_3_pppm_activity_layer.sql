-- Stage 3 PPPM Activity Compatibility Layer
-- Adds delivery, claim, redemption, merchant eligibility, and activity references.

CREATE TABLE IF NOT EXISTS pppm_deliveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  pppm_item_id BIGINT UNSIGNED NOT NULL,
  channel ENUM('email','sms','link','push','api','manual','other') NOT NULL,
  destination VARCHAR(255) NULL,
  status ENUM('queued','sent','delivered','failed','cancelled') NOT NULL DEFAULT 'queued',
  provider VARCHAR(100) NULL,
  provider_reference VARCHAR(190) NULL,
  failure_code VARCHAR(80) NULL,
  failure_message VARCHAR(500) NULL,
  queued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  delivered_at DATETIME NULL,
  failed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pppm_deliveries_public_id (public_id),
  KEY idx_pppm_deliveries_item_status (pppm_item_id, status, created_at),
  CONSTRAINT fk_pppm_deliveries_item FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pppm_merchant_eligibility (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pppm_item_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  merchant_location_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pppm_eligibility_item (pppm_item_id),
  KEY idx_pppm_eligibility_merchant_location (merchant_user_id, merchant_location_id),
  CONSTRAINT fk_pppm_eligibility_item FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_pppm_eligibility_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_eligibility_location FOREIGN KEY (merchant_location_id) REFERENCES merchant_locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pppm_claims (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  pppm_item_id BIGINT UNSIGNED NOT NULL,
  claimant_user_id BIGINT UNSIGNED NULL,
  claimant_external_id VARCHAR(190) NULL,
  status ENUM('pending','verified','redeemed','expired','cancelled','locked') NOT NULL DEFAULT 'pending',
  failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  merchant_location_id BIGINT UNSIGNED NULL,
  merchant_claim_code_id BIGINT UNSIGNED NULL,
  verified_by_user_id BIGINT UNSIGNED NULL,
  redeemed_by_user_id BIGINT UNSIGNED NULL,
  verified_at DATETIME NULL,
  redeemed_at DATETIME NULL,
  locked_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pppm_claims_public_id (public_id),
  UNIQUE KEY uq_pppm_claims_item (pppm_item_id),
  KEY idx_pppm_claims_status_expiry (status, expires_at),
  CONSTRAINT fk_pppm_claims_item FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_claims_claimant FOREIGN KEY (claimant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_claims_location FOREIGN KEY (merchant_location_id) REFERENCES merchant_locations(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_claims_code FOREIGN KEY (merchant_claim_code_id) REFERENCES merchant_claim_codes(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_claims_verified_by FOREIGN KEY (verified_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_claims_redeemed_by FOREIGN KEY (redeemed_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pppm_claim_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  claim_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  successful TINYINT(1) NOT NULL DEFAULT 0,
  ip_hash CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pppm_claim_attempts_claim_created (claim_id, created_at),
  CONSTRAINT fk_pppm_claim_attempts_claim FOREIGN KEY (claim_id) REFERENCES pppm_claims(id) ON DELETE CASCADE,
  CONSTRAINT fk_pppm_claim_attempts_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pppm_redemptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  pppm_item_id BIGINT UNSIGNED NOT NULL,
  claim_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  merchant_location_id BIGINT UNSIGNED NOT NULL,
  merchant_claim_code_id BIGINT UNSIGNED NOT NULL,
  redeemed_by_user_id BIGINT UNSIGNED NOT NULL,
  value_cents_snapshot INT UNSIGNED NOT NULL,
  currency_snapshot CHAR(3) NOT NULL,
  metadata_json JSON NULL,
  redeemed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pppm_redemptions_public_id (public_id),
  UNIQUE KEY uq_pppm_redemptions_item (pppm_item_id),
  KEY idx_pppm_redemptions_merchant_date (merchant_user_id, redeemed_at),
  CONSTRAINT fk_pppm_redemptions_item FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_redemptions_claim FOREIGN KEY (claim_id) REFERENCES pppm_claims(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_redemptions_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_redemptions_location FOREIGN KEY (merchant_location_id) REFERENCES merchant_locations(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_redemptions_code FOREIGN KEY (merchant_claim_code_id) REFERENCES merchant_claim_codes(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_redemptions_user FOREIGN KEY (redeemed_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @mg_has_thread_pppm_item := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'message_threads' AND COLUMN_NAME = 'pppm_item_id'
);
SET @mg_sql := IF(
  @mg_has_thread_pppm_item = 0,
  'ALTER TABLE message_threads ADD COLUMN pppm_item_id BIGINT UNSIGNED NULL AFTER gift_id',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_notification_pppm_item := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'pppm_item_id'
);
SET @mg_sql := IF(
  @mg_has_notification_pppm_item = 0,
  'ALTER TABLE notifications ADD COLUMN pppm_item_id BIGINT UNSIGNED NULL AFTER gift_id',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

INSERT IGNORE INTO permissions (slug, name, description, created_at) VALUES
('pppm.redeem', 'Redeem PPPM items', 'Verify and redeem authorized PPPM items.', NOW());

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r JOIN permissions p ON p.slug = 'pppm.redeem'
WHERE r.slug IN ('merchant','admin','super_admin');