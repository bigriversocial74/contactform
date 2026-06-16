-- Stage 13C: reconcile subscription access with Stage 12D tip payment recovery.

SET @mg_has_subscription_recovery_status := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscriptions' AND COLUMN_NAME='recovery_status'
);
SET @mg_sql := IF(@mg_has_subscription_recovery_status=0,
  "ALTER TABLE subscriptions ADD COLUMN recovery_status ENUM('clear','disputed','refunded','chargeback') NOT NULL DEFAULT 'clear' AFTER last_failure_message",
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_subscription_recovery_attempt := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscriptions' AND COLUMN_NAME='recovery_attempt_id'
);
SET @mg_sql := IF(@mg_has_subscription_recovery_attempt=0,
  'ALTER TABLE subscriptions ADD COLUMN recovery_attempt_id BIGINT UNSIGNED NULL AFTER recovery_status',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_subscription_recovery_reference := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscriptions' AND COLUMN_NAME='recovery_reference'
);
SET @mg_sql := IF(@mg_has_subscription_recovery_reference=0,
  'ALTER TABLE subscriptions ADD COLUMN recovery_reference VARCHAR(190) NULL AFTER recovery_attempt_id',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_subscription_pre_recovery_status := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscriptions' AND COLUMN_NAME='pre_recovery_status'
);
SET @mg_sql := IF(@mg_has_subscription_pre_recovery_status=0,
  'ALTER TABLE subscriptions ADD COLUMN pre_recovery_status VARCHAR(40) NULL AFTER recovery_reference',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_subscription_pre_recovery_billing := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscriptions' AND COLUMN_NAME='pre_recovery_next_billing_at'
);
SET @mg_sql := IF(@mg_has_subscription_pre_recovery_billing=0,
  'ALTER TABLE subscriptions ADD COLUMN pre_recovery_next_billing_at DATETIME NULL AFTER pre_recovery_status',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_subscription_recovery_started := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscriptions' AND COLUMN_NAME='recovery_started_at'
);
SET @mg_sql := IF(@mg_has_subscription_recovery_started=0,
  'ALTER TABLE subscriptions ADD COLUMN recovery_started_at DATETIME NULL AFTER pre_recovery_next_billing_at',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_subscription_recovery_resolved := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscriptions' AND COLUMN_NAME='recovery_resolved_at'
);
SET @mg_sql := IF(@mg_has_subscription_recovery_resolved=0,
  'ALTER TABLE subscriptions ADD COLUMN recovery_resolved_at DATETIME NULL AFTER recovery_started_at',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_subscription_access_suspended := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscriptions' AND COLUMN_NAME='access_suspended_at'
);
SET @mg_sql := IF(@mg_has_subscription_access_suspended=0,
  'ALTER TABLE subscriptions ADD COLUMN access_suspended_at DATETIME NULL AFTER recovery_resolved_at',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_subscription_recovery_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscriptions' AND INDEX_NAME='idx_subscriptions_recovery'
);
SET @mg_sql := IF(@mg_has_subscription_recovery_index=0,
  'ALTER TABLE subscriptions ADD KEY idx_subscriptions_recovery (recovery_status,recovery_attempt_id,updated_at)',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_attempt_recovery_status := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscription_attempts' AND COLUMN_NAME='recovery_status'
);
SET @mg_sql := IF(@mg_has_attempt_recovery_status=0,
  "ALTER TABLE subscription_attempts ADD COLUMN recovery_status ENUM('clear','disputed','partial_refund','refunded','chargeback') NOT NULL DEFAULT 'clear' AFTER failure_message",
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_attempt_recovered_amount := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscription_attempts' AND COLUMN_NAME='recovered_amount_cents'
);
SET @mg_sql := IF(@mg_has_attempt_recovered_amount=0,
  'ALTER TABLE subscription_attempts ADD COLUMN recovered_amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER recovery_status',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_attempt_recovery_reference := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscription_attempts' AND COLUMN_NAME='recovery_reference'
);
SET @mg_sql := IF(@mg_has_attempt_recovery_reference=0,
  'ALTER TABLE subscription_attempts ADD COLUMN recovery_reference VARCHAR(190) NULL AFTER recovered_amount_cents',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_attempt_recovery_started := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscription_attempts' AND COLUMN_NAME='recovery_started_at'
);
SET @mg_sql := IF(@mg_has_attempt_recovery_started=0,
  'ALTER TABLE subscription_attempts ADD COLUMN recovery_started_at DATETIME NULL AFTER recovery_reference',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_attempt_recovery_resolved := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscription_attempts' AND COLUMN_NAME='recovery_resolved_at'
);
SET @mg_sql := IF(@mg_has_attempt_recovery_resolved=0,
  'ALTER TABLE subscription_attempts ADD COLUMN recovery_resolved_at DATETIME NULL AFTER recovery_started_at',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_attempt_recovery_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscription_attempts' AND INDEX_NAME='idx_subscription_attempts_recovery'
);
SET @mg_sql := IF(@mg_has_attempt_recovery_index=0,
  'ALTER TABLE subscription_attempts ADD KEY idx_subscription_attempts_recovery (recovery_status,recovery_reference,updated_at)',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

CREATE TABLE IF NOT EXISTS subscription_payment_recoveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  subscription_id BIGINT UNSIGNED NOT NULL,
  subscription_attempt_id BIGINT UNSIGNED NOT NULL,
  tip_recovery_id BIGINT UNSIGNED NOT NULL,
  recovery_type ENUM('refund','dispute_opened','dispute_won','dispute_lost','chargeback') NOT NULL,
  provider_reference VARCHAR(190) NOT NULL,
  amount_cents BIGINT UNSIGNED NOT NULL,
  recovered_amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  previous_subscription_status VARCHAR(40) NOT NULL,
  resulting_subscription_status VARCHAR(40) NOT NULL,
  previous_recovery_status VARCHAR(40) NOT NULL,
  resulting_recovery_status VARCHAR(40) NOT NULL,
  access_action ENUM('unchanged','suspended','restored','revoked') NOT NULL,
  payload_json JSON NULL,
  processed_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subscription_recoveries_public_id (public_id),
  UNIQUE KEY uq_subscription_recoveries_tip_recovery (tip_recovery_id),
  KEY idx_subscription_recoveries_subscription (subscription_id,created_at,id),
  KEY idx_subscription_recoveries_attempt (subscription_attempt_id,recovery_type,created_at),
  KEY idx_subscription_recoveries_provider (provider_reference,recovery_type),
  CONSTRAINT fk_subscription_recoveries_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
  CONSTRAINT fk_subscription_recoveries_attempt FOREIGN KEY (subscription_attempt_id) REFERENCES subscription_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_subscription_recoveries_tip_recovery FOREIGN KEY (tip_recovery_id) REFERENCES tip_payment_recoveries(id) ON DELETE RESTRICT,
  CONSTRAINT chk_subscription_recoveries_amount CHECK (amount_cents > 0),
  CONSTRAINT chk_subscription_recoveries_accumulated CHECK (recovered_amount_cents >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_13c_subscription_recovery_reconciliation',
  'Reconcile Stage 12D tip refunds, disputes, and chargebacks into Stage 13 subscription access and Stage 14 eligibility.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);
