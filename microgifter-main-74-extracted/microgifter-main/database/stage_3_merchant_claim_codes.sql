-- Microgifter Stage 3 Merchant Location Claim Codes
-- Gift IDs identify gifts. Merchants define reusable claim codes per location.

CREATE TABLE IF NOT EXISTS merchant_locations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  address_line1 VARCHAR(190) NULL,
  address_line2 VARCHAR(190) NULL,
  city VARCHAR(120) NULL,
  region VARCHAR(120) NULL,
  postal_code VARCHAR(32) NULL,
  country_code CHAR(2) NOT NULL DEFAULT 'US',
  status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_locations_public_id (public_id),
  KEY idx_merchant_locations_owner_status (merchant_user_id, status),
  CONSTRAINT fk_merchant_locations_owner FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_claim_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(120) NOT NULL,
  code_hash CHAR(64) NOT NULL,
  code_last4 CHAR(4) NOT NULL,
  status ENUM('active','inactive','revoked','expired') NOT NULL DEFAULT 'active',
  valid_from DATETIME NULL,
  valid_until DATETIME NULL,
  usage_limit INT UNSIGNED NULL,
  usage_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_claim_codes_public_id (public_id),
  KEY idx_merchant_claim_codes_location_status (location_id, status),
  KEY idx_merchant_claim_codes_owner_status (merchant_user_id, status),
  CONSTRAINT fk_merchant_claim_codes_owner FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_claim_codes_location FOREIGN KEY (location_id) REFERENCES merchant_locations(id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_claim_codes_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gift_merchant_eligibility (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  gift_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gift_merchant_eligibility_gift (gift_id),
  KEY idx_gift_merchant_eligibility_merchant (merchant_user_id, location_id),
  CONSTRAINT fk_gift_merchant_eligibility_gift FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE CASCADE,
  CONSTRAINT fk_gift_merchant_eligibility_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_gift_merchant_eligibility_location FOREIGN KEY (location_id) REFERENCES merchant_locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @mg_claims_code_hash_nullable := (
  SELECT IS_NULLABLE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gift_claims' AND COLUMN_NAME = 'code_hash'
  LIMIT 1
);
SET @mg_sql := IF(
  @mg_claims_code_hash_nullable = 'NO',
  'ALTER TABLE gift_claims MODIFY code_hash CHAR(64) NULL',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_claims_code_last4_nullable := (
  SELECT IS_NULLABLE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gift_claims' AND COLUMN_NAME = 'code_last4'
  LIMIT 1
);
SET @mg_sql := IF(
  @mg_claims_code_last4_nullable = 'NO',
  'ALTER TABLE gift_claims MODIFY code_last4 CHAR(4) NULL',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_claim_location_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gift_claims' AND COLUMN_NAME = 'location_id'
);
SET @mg_sql := IF(
  @mg_has_claim_location_id = 0,
  'ALTER TABLE gift_claims ADD COLUMN location_id BIGINT UNSIGNED NULL AFTER claimant_user_id',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_claim_code_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gift_claims' AND COLUMN_NAME = 'merchant_claim_code_id'
);
SET @mg_sql := IF(
  @mg_has_claim_code_id = 0,
  'ALTER TABLE gift_claims ADD COLUMN merchant_claim_code_id BIGINT UNSIGNED NULL AFTER location_id',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_claim_verified_by := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gift_claims' AND COLUMN_NAME = 'verified_by_user_id'
);
SET @mg_sql := IF(
  @mg_has_claim_verified_by = 0,
  'ALTER TABLE gift_claims ADD COLUMN verified_by_user_id BIGINT UNSIGNED NULL AFTER merchant_claim_code_id',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_claim_redeemed_by := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gift_claims' AND COLUMN_NAME = 'redeemed_by_user_id'
);
SET @mg_sql := IF(
  @mg_has_claim_redeemed_by = 0,
  'ALTER TABLE gift_claims ADD COLUMN redeemed_by_user_id BIGINT UNSIGNED NULL AFTER verified_by_user_id',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

INSERT IGNORE INTO permissions (slug, name, description, created_at) VALUES
('merchant.locations.manage', 'Manage merchant locations', 'Create and manage merchant locations.', NOW()),
('merchant.claim_codes.manage', 'Manage merchant claim codes', 'Create, rotate, disable, and revoke merchant claim codes.', NOW()),
('merchant.gifts.redeem', 'Redeem merchant gifts', 'Verify and redeem eligible gifts at authorized merchant locations.', NOW());

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('merchant.locations.manage','merchant.claim_codes.manage','merchant.gifts.redeem')
WHERE r.slug IN ('merchant','admin','super_admin');