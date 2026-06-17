-- Stage 10B — Merchant Location Claim Authority and Attempt Ledger

CREATE TABLE IF NOT EXISTS merchant_location_staff (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('manager','staff') NOT NULL DEFAULT 'staff',
  status ENUM('active','inactive','revoked') NOT NULL DEFAULT 'active',
  assigned_by_user_id BIGINT UNSIGNED NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_location_staff_assignment (location_id,user_id),
  KEY idx_merchant_location_staff_actor (user_id,status),
  KEY idx_merchant_location_staff_merchant (merchant_user_id,location_id,status),
  CONSTRAINT fk_merchant_location_staff_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_location_staff_location FOREIGN KEY (location_id) REFERENCES merchant_locations(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_location_staff_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_location_staff_assigner FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS microgift_claim_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  instance_id BIGINT UNSIGNED NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  location_id BIGINT UNSIGNED NULL,
  merchant_claim_code_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  result ENUM('approved','invalid_gift','gift_not_paid','invalid_state','gift_expired','already_claimed','merchant_mismatch','invalid_location','location_not_allowed','invalid_claim_code','unauthorized_claim_actor','rate_limited','internal_error') NOT NULL,
  reason_code VARCHAR(80) NOT NULL,
  idempotency_key VARCHAR(190) NULL,
  correlation_id VARCHAR(190) NULL,
  request_fingerprint CHAR(64) NULL,
  ip_hash CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  risk_json JSON NULL,
  metadata_json JSON NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgift_claim_attempts_public_id (public_id),
  KEY idx_microgift_claim_attempts_instance (instance_id,attempted_at),
  KEY idx_microgift_claim_attempts_location (location_id,attempted_at),
  KEY idx_microgift_claim_attempts_merchant (merchant_user_id,attempted_at),
  KEY idx_microgift_claim_attempts_actor (actor_user_id,attempted_at),
  KEY idx_microgift_claim_attempts_result (result,attempted_at),
  KEY idx_microgift_claim_attempts_correlation (correlation_id),
  CONSTRAINT fk_microgift_claim_attempts_instance FOREIGN KEY (instance_id) REFERENCES microgift_instances(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_claim_attempts_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_claim_attempts_location FOREIGN KEY (location_id) REFERENCES merchant_locations(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_claim_attempts_code FOREIGN KEY (merchant_claim_code_id) REFERENCES merchant_claim_codes(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_claim_attempts_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @mg_has_redemption_location_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='microgift_redemptions' AND COLUMN_NAME='location_id'
);
SET @mg_sql := IF(@mg_has_redemption_location_id=0,
  'ALTER TABLE microgift_redemptions ADD COLUMN location_id BIGINT UNSIGNED NULL AFTER merchant_user_id',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_redemption_claim_code_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='microgift_redemptions' AND COLUMN_NAME='merchant_claim_code_id'
);
SET @mg_sql := IF(@mg_has_redemption_claim_code_id=0,
  'ALTER TABLE microgift_redemptions ADD COLUMN merchant_claim_code_id BIGINT UNSIGNED NULL AFTER location_id',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_claim_location_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='microgift_claims' AND COLUMN_NAME='location_id'
);
SET @mg_sql := IF(@mg_has_claim_location_id=0,
  'ALTER TABLE microgift_claims ADD COLUMN location_id BIGINT UNSIGNED NULL AFTER claimant_user_id',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_claim_code_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='microgift_claims' AND COLUMN_NAME='merchant_claim_code_id'
);
SET @mg_sql := IF(@mg_has_claim_code_id=0,
  'ALTER TABLE microgift_claims ADD COLUMN merchant_claim_code_id BIGINT UNSIGNED NULL AFTER location_id',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.location_staff.manage','Manage location staff','Assign and revoke staff authority for merchant locations.',NOW()),
('merchant.location_claim.verify','Verify location claims','Verify merchant-location claim authority and claim codes.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('merchant.location_staff.manage','merchant.location_claim.verify')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_10b_location_claim_authority','Merchant location claim authority, staff assignments, immutable attempt ledger, and canonical references.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);