-- Stage 13B compatibility migration for existing installations

ALTER TABLE subscriptions
  MODIFY COLUMN status ENUM('pending_payment','trialing','active','past_due','paused','cancel_pending','canceled','expired') NOT NULL;

SET @has_initial_payment_required := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscriptions' AND COLUMN_NAME='initial_payment_required'
);
SET @sql := IF(
  @has_initial_payment_required=0,
  'ALTER TABLE subscriptions ADD COLUMN initial_payment_required TINYINT(1) NOT NULL DEFAULT 0 AFTER trial_ends_at',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_funded_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscriptions' AND COLUMN_NAME='funded_at'
);
SET @sql := IF(
  @has_funded_at=0,
  'ALTER TABLE subscriptions ADD COLUMN funded_at DATETIME NULL AFTER initial_payment_required',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_activated_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscriptions' AND COLUMN_NAME='activated_at'
);
SET @sql := IF(
  @has_activated_at=0,
  'ALTER TABLE subscriptions ADD COLUMN activated_at DATETIME NULL AFTER funded_at',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_13b_initial_subscription_funding','Initial payment gating, funded activation, compatibility reconciliation, and duplicate-success protection.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
