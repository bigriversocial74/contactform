-- Microgifter merchant locations and multi-claim-code safeguards
-- Supports many locations per merchant and many active claim codes per location.

SET @mg_has_assignment_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='merchant_claim_codes' AND COLUMN_NAME='assignment_type'
);
SET @mg_sql := IF(@mg_has_assignment_type=0,
  "ALTER TABLE merchant_claim_codes ADD COLUMN assignment_type VARCHAR(32) NOT NULL DEFAULT 'location' AFTER label",
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_assignment_reference := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='merchant_claim_codes' AND COLUMN_NAME='assignment_reference'
);
SET @mg_sql := IF(@mg_has_assignment_reference=0,
  "ALTER TABLE merchant_claim_codes ADD COLUMN assignment_reference VARCHAR(120) NULL AFTER assignment_type",
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_archived_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='merchant_locations' AND COLUMN_NAME='archived_at'
);
SET @mg_sql := IF(@mg_has_archived_at=0,
  'ALTER TABLE merchant_locations ADD COLUMN archived_at DATETIME NULL AFTER status',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_archived_by := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='merchant_locations' AND COLUMN_NAME='archived_by_user_id'
);
SET @mg_sql := IF(@mg_has_archived_by=0,
  'ALTER TABLE merchant_locations ADD COLUMN archived_by_user_id BIGINT UNSIGNED NULL AFTER archived_at',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_archive_reason := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='merchant_locations' AND COLUMN_NAME='archive_reason'
);
SET @mg_sql := IF(@mg_has_archive_reason=0,
  'ALTER TABLE merchant_locations ADD COLUMN archive_reason VARCHAR(255) NULL AFTER archived_by_user_id',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_claim_assignment_idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='merchant_claim_codes' AND INDEX_NAME='idx_claim_codes_location_assignment_status'
);
SET @mg_sql := IF(@mg_has_claim_assignment_idx=0,
  'ALTER TABLE merchant_claim_codes ADD INDEX idx_claim_codes_location_assignment_status (location_id,assignment_type,status,valid_from,valid_until)',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_claim_owner_hash_idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='merchant_claim_codes' AND INDEX_NAME='idx_claim_codes_owner_hash_status'
);
SET @mg_sql := IF(@mg_has_claim_owner_hash_idx=0,
  'ALTER TABLE merchant_claim_codes ADD INDEX idx_claim_codes_owner_hash_status (merchant_user_id,code_hash,status)',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_location_archive_idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='merchant_locations' AND INDEX_NAME='idx_locations_workspace_status_primary'
);
SET @mg_sql := IF(@mg_has_location_archive_idx=0,
  'ALTER TABLE merchant_locations ADD INDEX idx_locations_workspace_status_primary (workspace_id,status,is_primary)',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

UPDATE merchant_claim_codes
SET assignment_type='location'
WHERE assignment_type IS NULL OR assignment_type='';
