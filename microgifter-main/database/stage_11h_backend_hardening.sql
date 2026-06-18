-- Stage 11H — Backend hardening for Action Center and merchant claim-code storage
-- Adds merchant_locations.claim_code through an ordered migration instead of request-time DDL.

SET @mg_has_merchant_locations := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations'
);

SET @mg_has_location_claim_code := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations' AND COLUMN_NAME = 'claim_code'
);

SET @mg_sql := IF(
  @mg_has_merchant_locations = 1 AND @mg_has_location_claim_code = 0,
  'ALTER TABLE merchant_locations ADD COLUMN claim_code VARCHAR(80) NULL',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_location_code := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations' AND COLUMN_NAME = 'location_code'
);

SET @mg_sql := IF(
  @mg_has_merchant_locations = 1 AND @mg_has_location_code = 1,
  "UPDATE merchant_locations SET claim_code = UPPER(location_code) WHERE (claim_code IS NULL OR claim_code = '') AND location_code IS NOT NULL AND location_code <> ''",
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_workspace_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations' AND COLUMN_NAME = 'workspace_id'
);
SET @mg_has_workspace_claim_index := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations' AND INDEX_NAME = 'uq_merchant_locations_workspace_claim_code'
);
SET @mg_sql := IF(
  @mg_has_workspace_id = 1 AND @mg_has_workspace_claim_index = 0,
  'ALTER TABLE merchant_locations ADD UNIQUE KEY uq_merchant_locations_workspace_claim_code (workspace_id, claim_code)',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_merchant_user_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations' AND COLUMN_NAME = 'merchant_user_id'
);
SET @mg_has_merchant_claim_index := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations' AND INDEX_NAME = 'uq_merchant_locations_merchant_claim_code'
);
SET @mg_sql := IF(
  @mg_has_merchant_user_id = 1 AND @mg_has_merchant_claim_index = 0,
  'ALTER TABLE merchant_locations ADD UNIQUE KEY uq_merchant_locations_merchant_claim_code (merchant_user_id, claim_code)',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

INSERT INTO schema_migrations (migration_key, description, checksum, applied_at)
VALUES (
  'stage_11h_backend_hardening',
  'Move merchant claim-code storage into ordered schema migration and support Action Center backend hardening.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description = VALUES(description);
