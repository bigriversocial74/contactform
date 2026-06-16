-- Stage 10C — Atomic Claim/Redemption and Inbox Integration

CREATE TABLE IF NOT EXISTS microgift_inbox_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  instance_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  state ENUM('received','claimable','claimed','redeemed','expired','revoked') NOT NULL DEFAULT 'received',
  claim_id BIGINT UNSIGNED NULL,
  redemption_id BIGINT UNSIGNED NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  location_id BIGINT UNSIGNED NULL,
  can_tip TINYINT(1) NOT NULL DEFAULT 0,
  first_received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  claimed_at DATETIME NULL,
  redeemed_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgift_inbox_public_id (public_id),
  UNIQUE KEY uq_microgift_inbox_instance_user (instance_id,user_id),
  KEY idx_microgift_inbox_user_state (user_id,state,updated_at),
  KEY idx_microgift_inbox_merchant_location (merchant_user_id,location_id,redeemed_at),
  CONSTRAINT fk_microgift_inbox_instance FOREIGN KEY (instance_id) REFERENCES microgift_instances(id) ON DELETE CASCADE,
  CONSTRAINT fk_microgift_inbox_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_microgift_inbox_claim FOREIGN KEY (claim_id) REFERENCES microgift_claims(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_inbox_redemption FOREIGN KEY (redemption_id) REFERENCES microgift_redemptions(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_inbox_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_inbox_location FOREIGN KEY (location_id) REFERENCES merchant_locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @mg_has_attempt_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='microgift_redemptions' AND COLUMN_NAME='claim_attempt_id'
);
SET @mg_sql := IF(@mg_has_attempt_id=0,
  'ALTER TABLE microgift_redemptions ADD COLUMN claim_attempt_id BIGINT UNSIGNED NULL AFTER merchant_claim_code_id',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_can_tip := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='microgift_redemptions' AND COLUMN_NAME='can_tip'
);
SET @mg_sql := IF(@mg_has_can_tip=0,
  'ALTER TABLE microgift_redemptions ADD COLUMN can_tip TINYINT(1) NOT NULL DEFAULT 0 AFTER claim_attempt_id',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_10c_atomic_claim_redemption_inbox','Atomic merchant-location redemption, durable attempt outcomes, and inbox claimed/redeemed read model.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
