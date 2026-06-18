SET @mg_has_payment_refunds := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_refunds'
);
SET @mg_has_payment_disputes := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_disputes'
);
SET @mg_has_payout_holds := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payout_holds'
);

SET @mg_sql := IF(@mg_has_payment_refunds = 1,
  'ALTER TABLE payment_refunds MODIFY COLUMN order_id BIGINT UNSIGNED NULL, MODIFY COLUMN merchant_user_id BIGINT UNSIGNED NULL, MODIFY COLUMN requested_by_user_id BIGINT UNSIGNED NULL',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_refund_source_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_refunds' AND COLUMN_NAME = 'source_type'
);
SET @mg_sql := IF(@mg_has_payment_refunds = 1 AND @mg_has_refund_source_type = 0,
  "ALTER TABLE payment_refunds ADD COLUMN source_type VARCHAR(80) NOT NULL DEFAULT 'order' AFTER order_id",
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_refund_source_reference := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_refunds' AND COLUMN_NAME = 'source_reference'
);
SET @mg_sql := IF(@mg_has_payment_refunds = 1 AND @mg_has_refund_source_reference = 0,
  'ALTER TABLE payment_refunds ADD COLUMN source_reference VARCHAR(190) NULL AFTER source_type',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_refund_tip_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_refunds' AND COLUMN_NAME = 'tip_id'
);
SET @mg_sql := IF(@mg_has_payment_refunds = 1 AND @mg_has_refund_tip_id = 0,
  'ALTER TABLE payment_refunds ADD COLUMN tip_id BIGINT UNSIGNED NULL AFTER payment_intent_id',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_refund_ledger_group := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_refunds' AND COLUMN_NAME = 'ledger_group_id'
);
SET @mg_sql := IF(@mg_has_payment_refunds = 1 AND @mg_has_refund_ledger_group = 0,
  'ALTER TABLE payment_refunds ADD COLUMN ledger_group_id BIGINT UNSIGNED NULL AFTER tip_id',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_refund_provider_event := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_refunds' AND COLUMN_NAME = 'provider_event_id'
);
SET @mg_sql := IF(@mg_has_payment_refunds = 1 AND @mg_has_refund_provider_event = 0,
  'ALTER TABLE payment_refunds ADD COLUMN provider_event_id VARCHAR(190) NULL AFTER provider_refund_reference',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_refund_source_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_refunds' AND INDEX_NAME = 'idx_payment_refunds_source'
);
SET @mg_sql := IF(@mg_has_payment_refunds = 1 AND @mg_has_refund_source_index = 0,
  'ALTER TABLE payment_refunds ADD KEY idx_payment_refunds_source (source_type,source_reference,status,created_at), ADD KEY idx_payment_refunds_tip (tip_id,status,created_at)',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_refund_tip_fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_refunds' AND CONSTRAINT_NAME = 'fk_payment_refunds_tip'
);
SET @mg_sql := IF(@mg_has_payment_refunds = 1 AND @mg_has_refund_tip_fk = 0,
  'ALTER TABLE payment_refunds ADD CONSTRAINT fk_payment_refunds_tip FOREIGN KEY (tip_id) REFERENCES tips(id) ON DELETE RESTRICT, ADD CONSTRAINT fk_payment_refunds_ledger_group FOREIGN KEY (ledger_group_id) REFERENCES ledger_transaction_groups(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_sql := IF(@mg_has_payment_disputes = 1,
  'ALTER TABLE payment_disputes MODIFY COLUMN order_id BIGINT UNSIGNED NULL, MODIFY COLUMN merchant_user_id BIGINT UNSIGNED NULL',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_dispute_source_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_disputes' AND COLUMN_NAME = 'source_type'
);
SET @mg_sql := IF(@mg_has_payment_disputes = 1 AND @mg_has_dispute_source_type = 0,
  "ALTER TABLE payment_disputes ADD COLUMN source_type VARCHAR(80) NOT NULL DEFAULT 'order' AFTER order_id",
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_dispute_source_reference := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_disputes' AND COLUMN_NAME = 'source_reference'
);
SET @mg_sql := IF(@mg_has_payment_disputes = 1 AND @mg_has_dispute_source_reference = 0,
  'ALTER TABLE payment_disputes ADD COLUMN source_reference VARCHAR(190) NULL AFTER source_type',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_dispute_tip_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_disputes' AND COLUMN_NAME = 'tip_id'
);
SET @mg_sql := IF(@mg_has_payment_disputes = 1 AND @mg_has_dispute_tip_id = 0,
  'ALTER TABLE payment_disputes ADD COLUMN tip_id BIGINT UNSIGNED NULL AFTER payment_intent_id',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_dispute_hold := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_disputes' AND COLUMN_NAME = 'payout_hold_id'
);
SET @mg_sql := IF(@mg_has_payment_disputes = 1 AND @mg_has_dispute_hold = 0,
  'ALTER TABLE payment_disputes ADD COLUMN payout_hold_id BIGINT UNSIGNED NULL AFTER tip_id',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_dispute_recovery_group := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_disputes' AND COLUMN_NAME = 'recovery_group_id'
);
SET @mg_sql := IF(@mg_has_payment_disputes = 1 AND @mg_has_dispute_recovery_group = 0,
  'ALTER TABLE payment_disputes ADD COLUMN recovery_group_id BIGINT UNSIGNED NULL AFTER payout_hold_id',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_dispute_provider_event := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_disputes' AND COLUMN_NAME = 'provider_event_id'
);
SET @mg_sql := IF(@mg_has_payment_disputes = 1 AND @mg_has_dispute_provider_event = 0,
  'ALTER TABLE payment_disputes ADD COLUMN provider_event_id VARCHAR(190) NULL AFTER provider_dispute_reference, ADD COLUMN metadata_json JSON NULL AFTER resolved_at',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_dispute_source_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_disputes' AND INDEX_NAME = 'idx_payment_disputes_source'
);
SET @mg_sql := IF(@mg_has_payment_disputes = 1 AND @mg_has_dispute_source_index = 0,
  'ALTER TABLE payment_disputes ADD KEY idx_payment_disputes_source (source_type,source_reference,status,created_at), ADD KEY idx_payment_disputes_tip (tip_id,status,created_at)',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_dispute_tip_fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_disputes' AND CONSTRAINT_NAME = 'fk_payment_disputes_tip'
);
SET @mg_sql := IF(@mg_has_payment_disputes = 1 AND @mg_has_dispute_tip_fk = 0,
  'ALTER TABLE payment_disputes ADD CONSTRAINT fk_payment_disputes_tip FOREIGN KEY (tip_id) REFERENCES tips(id) ON DELETE RESTRICT, ADD CONSTRAINT fk_payment_disputes_hold FOREIGN KEY (payout_hold_id) REFERENCES payout_holds(id) ON DELETE SET NULL, ADD CONSTRAINT fk_payment_disputes_recovery_group FOREIGN KEY (recovery_group_id) REFERENCES ledger_transaction_groups(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_hold_source_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payout_holds' AND COLUMN_NAME = 'source_type'
);
SET @mg_sql := IF(@mg_has_payout_holds = 1 AND @mg_has_hold_source_type = 0,
  'ALTER TABLE payout_holds ADD COLUMN source_type VARCHAR(80) NULL AFTER wallet_id, ADD COLUMN source_reference VARCHAR(190) NULL AFTER source_type, ADD COLUMN idempotency_key VARCHAR(190) NULL AFTER source_reference',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_hold_source_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payout_holds' AND INDEX_NAME = 'idx_payout_holds_source'
);
SET @mg_sql := IF(@mg_has_payout_holds = 1 AND @mg_has_hold_source_index = 0,
  'ALTER TABLE payout_holds ADD KEY idx_payout_holds_source (source_type,source_reference,status), ADD UNIQUE KEY uq_payout_holds_idempotency (idempotency_key)',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

CREATE TABLE IF NOT EXISTS tip_payment_recoveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  tip_id BIGINT UNSIGNED NOT NULL,
  payment_intent_id BIGINT UNSIGNED NOT NULL,
  recovery_type ENUM('refund','dispute_opened','dispute_won','dispute_lost','chargeback') NOT NULL,
  provider_reference VARCHAR(190) NOT NULL,
  provider_event_id VARCHAR(190) NOT NULL,
  amount_cents BIGINT UNSIGNED NOT NULL,
  net_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  fee_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL,
  status ENUM('received','held','recovered','released','ignored','failed') NOT NULL DEFAULT 'received',
  payment_refund_id BIGINT UNSIGNED NULL,
  payment_dispute_id BIGINT UNSIGNED NULL,
  ledger_group_id BIGINT UNSIGNED NULL,
  payout_hold_id BIGINT UNSIGNED NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  payload_json JSON NULL,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tip_recoveries_public_id (public_id),
  UNIQUE KEY uq_tip_recoveries_tip_idempotency (tip_id,idempotency_key),
  UNIQUE KEY uq_tip_recoveries_provider_event (provider_event_id),
  KEY idx_tip_recoveries_tip (tip_id,recovery_type,status,created_at),
  KEY idx_tip_recoveries_provider (provider_reference,recovery_type,status),
  CONSTRAINT fk_tip_recoveries_tip FOREIGN KEY (tip_id) REFERENCES tips(id) ON DELETE RESTRICT,
  CONSTRAINT fk_tip_recoveries_intent FOREIGN KEY (payment_intent_id) REFERENCES payment_intents(id) ON DELETE RESTRICT,
  CONSTRAINT fk_tip_recoveries_refund FOREIGN KEY (payment_refund_id) REFERENCES payment_refunds(id) ON DELETE SET NULL,
  CONSTRAINT fk_tip_recoveries_dispute FOREIGN KEY (payment_dispute_id) REFERENCES payment_disputes(id) ON DELETE SET NULL,
  CONSTRAINT fk_tip_recoveries_ledger FOREIGN KEY (ledger_group_id) REFERENCES ledger_transaction_groups(id) ON DELETE SET NULL,
  CONSTRAINT fk_tip_recoveries_hold FOREIGN KEY (payout_hold_id) REFERENCES payout_holds(id) ON DELETE SET NULL,
  CONSTRAINT chk_tip_recoveries_amount CHECK (amount_cents > 0),
  CONSTRAINT chk_tip_recoveries_components CHECK (net_cents + fee_cents = amount_cents)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key, description, checksum, applied_at)
VALUES (
  'stage_12d_tip_recovery',
  'Generalize refunds, disputes, and payout holds for tip sources and add durable tip payment recovery records.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description = VALUES(description);
