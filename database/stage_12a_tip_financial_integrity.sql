-- Stage 12A — Universal tip financial-integrity schema hardening
-- Extends the existing Stage 12 foundation without creating parallel payment,
-- wallet, ledger, webhook, payout, or reconciliation systems.

SET @mg_has_payment_intents := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_intents'
);

SET @mg_has_pi_source_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_intents' AND COLUMN_NAME = 'source_type'
);
SET @mg_sql := IF(
  @mg_has_payment_intents = 1 AND @mg_has_pi_source_type = 0,
  "ALTER TABLE payment_intents ADD COLUMN source_type VARCHAR(80) NOT NULL DEFAULT 'commerce_order' AFTER order_id",
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_pi_source_reference := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_intents' AND COLUMN_NAME = 'source_reference'
);
SET @mg_sql := IF(
  @mg_has_payment_intents = 1 AND @mg_has_pi_source_reference = 0,
  'ALTER TABLE payment_intents ADD COLUMN source_reference VARCHAR(190) NULL AFTER source_type',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_pi_order_required := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_intents' AND COLUMN_NAME = 'order_id' AND IS_NULLABLE = 'NO'
);
SET @mg_sql := IF(
  @mg_has_payment_intents = 1 AND @mg_pi_order_required = 1,
  'ALTER TABLE payment_intents MODIFY COLUMN order_id BIGINT UNSIGNED NULL',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_sql := IF(
  @mg_has_payment_intents = 1,
  "UPDATE payment_intents pi INNER JOIN commerce_orders o ON o.id = pi.order_id SET pi.source_type = 'commerce_order', pi.source_reference = o.public_id WHERE pi.order_id IS NOT NULL AND (pi.source_reference IS NULL OR pi.source_reference = '')",
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_pi_source_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_intents' AND INDEX_NAME = 'idx_payment_intents_source'
);
SET @mg_sql := IF(
  @mg_has_payment_intents = 1 AND @mg_has_pi_source_index = 0,
  'ALTER TABLE payment_intents ADD KEY idx_payment_intents_source (source_type, source_reference, provider_key, status)',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_tips := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tips'
);

SET @mg_has_tip_owner_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tips' AND COLUMN_NAME = 'recipient_wallet_owner_type'
);
SET @mg_sql := IF(
  @mg_has_tips = 1 AND @mg_has_tip_owner_type = 0,
  "ALTER TABLE tips ADD COLUMN recipient_wallet_owner_type ENUM('user','merchant','creator','organization','enterprise') NULL AFTER recipient_user_id",
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_tip_owner_user := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tips' AND COLUMN_NAME = 'recipient_wallet_owner_user_id'
);
SET @mg_sql := IF(
  @mg_has_tips = 1 AND @mg_has_tip_owner_user = 0,
  'ALTER TABLE tips ADD COLUMN recipient_wallet_owner_user_id BIGINT UNSIGNED NULL AFTER recipient_wallet_owner_type',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_tip_provider_key := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tips' AND COLUMN_NAME = 'provider_key'
);
SET @mg_sql := IF(
  @mg_has_tips = 1 AND @mg_has_tip_provider_key = 0,
  'ALTER TABLE tips ADD COLUMN provider_key VARCHAR(80) NULL AFTER funding_type',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_tip_payment_intent := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tips' AND COLUMN_NAME = 'payment_intent_id'
);
SET @mg_sql := IF(
  @mg_has_tips = 1 AND @mg_has_tip_payment_intent = 0,
  'ALTER TABLE tips ADD COLUMN payment_intent_id BIGINT UNSIGNED NULL AFTER provider_payment_id',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_tip_fingerprint := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tips' AND COLUMN_NAME = 'request_fingerprint'
);
SET @mg_sql := IF(
  @mg_has_tips = 1 AND @mg_has_tip_fingerprint = 0,
  'ALTER TABLE tips ADD COLUMN request_fingerprint CHAR(64) NULL AFTER idempotency_key',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_tip_target_snapshot := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tips' AND COLUMN_NAME = 'target_snapshot_json'
);
SET @mg_sql := IF(
  @mg_has_tips = 1 AND @mg_has_tip_target_snapshot = 0,
  'ALTER TABLE tips ADD COLUMN target_snapshot_json JSON NULL AFTER fee_snapshot_json',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_tip_settled_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tips' AND COLUMN_NAME = 'settled_at'
);
SET @mg_sql := IF(
  @mg_has_tips = 1 AND @mg_has_tip_settled_at = 0,
  'ALTER TABLE tips ADD COLUMN settled_at DATETIME NULL AFTER posted_at',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_tip_failed_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tips' AND COLUMN_NAME = 'failed_at'
);
SET @mg_sql := IF(
  @mg_has_tips = 1 AND @mg_has_tip_failed_at = 0,
  'ALTER TABLE tips ADD COLUMN failed_at DATETIME NULL AFTER settled_at',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_tip_disputed_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tips' AND COLUMN_NAME = 'disputed_at'
);
SET @mg_sql := IF(
  @mg_has_tips = 1 AND @mg_has_tip_disputed_at = 0,
  'ALTER TABLE tips ADD COLUMN disputed_at DATETIME NULL AFTER failed_at',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_tip_refunded_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tips' AND COLUMN_NAME = 'refunded_at'
);
SET @mg_sql := IF(
  @mg_has_tips = 1 AND @mg_has_tip_refunded_at = 0,
  'ALTER TABLE tips ADD COLUMN refunded_at DATETIME NULL AFTER disputed_at',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_sql := IF(
  @mg_has_tips = 1,
  "ALTER TABLE tips MODIFY COLUMN status ENUM('pending','requires_action','processing','funded','posted','failed','disputed','refunded','reversed') NOT NULL DEFAULT 'pending'",
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_sql := IF(
  @mg_has_tips = 1,
  "UPDATE tips SET recipient_wallet_owner_type = CASE target_type WHEN 'creator' THEN 'creator' WHEN 'merchant' THEN 'merchant' WHEN 'location' THEN 'merchant' WHEN 'product' THEN 'merchant' WHEN 'post' THEN 'merchant' WHEN 'gift' THEN 'merchant' WHEN 'claim' THEN 'merchant' ELSE 'user' END, recipient_wallet_owner_user_id = recipient_user_id WHERE recipient_wallet_owner_type IS NULL OR recipient_wallet_owner_user_id IS NULL",
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_sql := IF(
  @mg_has_tips = 1,
  "UPDATE tips SET provider_key = 'stripe' WHERE funding_type = 'stripe' AND (provider_key IS NULL OR provider_key = '')",
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_sql := IF(
  @mg_has_tips = 1,
  "UPDATE tips SET request_fingerprint = SHA2(CONCAT_WS('|', sender_user_id, target_type, target_reference, recipient_user_id, amount_cents, currency, funding_type), 256) WHERE request_fingerprint IS NULL OR request_fingerprint = ''",
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_sql := IF(
  @mg_has_tips = 1,
  "UPDATE tips SET target_snapshot_json = JSON_OBJECT('target_type', target_type, 'target_reference', target_reference, 'recipient_user_id', recipient_user_id, 'recipient_wallet_owner_type', recipient_wallet_owner_type, 'recipient_wallet_owner_user_id', recipient_wallet_owner_user_id) WHERE target_snapshot_json IS NULL",
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_sql := IF(
  @mg_has_tips = 1,
  "ALTER TABLE tips MODIFY COLUMN recipient_wallet_owner_type ENUM('user','merchant','creator','organization','enterprise') NOT NULL, MODIFY COLUMN recipient_wallet_owner_user_id BIGINT UNSIGNED NOT NULL",
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_tip_owner_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tips' AND INDEX_NAME = 'idx_tips_recipient_wallet_status'
);
SET @mg_sql := IF(
  @mg_has_tips = 1 AND @mg_has_tip_owner_index = 0,
  'ALTER TABLE tips ADD KEY idx_tips_recipient_wallet_status (recipient_wallet_owner_type, recipient_wallet_owner_user_id, status, created_at)',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_tip_payment_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tips' AND INDEX_NAME = 'idx_tips_payment_intent'
);
SET @mg_sql := IF(
  @mg_has_tips = 1 AND @mg_has_tip_payment_index = 0,
  'ALTER TABLE tips ADD KEY idx_tips_payment_intent (payment_intent_id)',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_tip_owner_fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tips' AND CONSTRAINT_NAME = 'fk_tips_recipient_wallet_owner'
);
SET @mg_sql := IF(
  @mg_has_tips = 1 AND @mg_has_tip_owner_fk = 0,
  'ALTER TABLE tips ADD CONSTRAINT fk_tips_recipient_wallet_owner FOREIGN KEY (recipient_wallet_owner_user_id) REFERENCES users(id) ON DELETE RESTRICT',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_tip_payment_fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tips' AND CONSTRAINT_NAME = 'fk_tips_payment_intent'
);
SET @mg_sql := IF(
  @mg_has_tips = 1 AND @mg_has_payment_intents = 1 AND @mg_has_tip_payment_fk = 0,
  'ALTER TABLE tips ADD CONSTRAINT fk_tips_payment_intent FOREIGN KEY (payment_intent_id) REFERENCES payment_intents(id) ON DELETE RESTRICT',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

CREATE TABLE IF NOT EXISTS tip_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  tip_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  source_type VARCHAR(80) NOT NULL DEFAULT 'system',
  source_reference VARCHAR(190) NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tip_events_public_id (public_id),
  UNIQUE KEY uq_tip_events_tip_idempotency (tip_id, idempotency_key),
  KEY idx_tip_events_tip_created (tip_id, created_at, id),
  KEY idx_tip_events_source (source_type, source_reference, created_at),
  CONSTRAINT fk_tip_events_tip FOREIGN KEY (tip_id) REFERENCES tips(id) ON DELETE RESTRICT,
  CONSTRAINT fk_tip_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @mg_has_tip_reversals := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tip_reversals'
);
SET @mg_has_tip_reversal_amount := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tip_reversals' AND COLUMN_NAME = 'amount_cents'
);
SET @mg_sql := IF(
  @mg_has_tip_reversals = 1 AND @mg_has_tip_reversal_amount = 0,
  'ALTER TABLE tip_reversals ADD COLUMN amount_cents BIGINT UNSIGNED NULL AFTER tip_id',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_tip_reversal_currency := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tip_reversals' AND COLUMN_NAME = 'currency'
);
SET @mg_sql := IF(
  @mg_has_tip_reversals = 1 AND @mg_has_tip_reversal_currency = 0,
  'ALTER TABLE tip_reversals ADD COLUMN currency CHAR(3) NULL AFTER amount_cents',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_tip_reversal_metadata := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tip_reversals' AND COLUMN_NAME = 'metadata_json'
);
SET @mg_sql := IF(
  @mg_has_tip_reversals = 1 AND @mg_has_tip_reversal_metadata = 0,
  'ALTER TABLE tip_reversals ADD COLUMN metadata_json JSON NULL AFTER ledger_group_id',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_sql := IF(
  @mg_has_tip_reversals = 1,
  'UPDATE tip_reversals tr INNER JOIN tips t ON t.id = tr.tip_id SET tr.amount_cents = t.amount_cents, tr.currency = t.currency WHERE tr.amount_cents IS NULL OR tr.currency IS NULL',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_sql := IF(
  @mg_has_tip_reversals = 1,
  'ALTER TABLE tip_reversals MODIFY COLUMN amount_cents BIGINT UNSIGNED NOT NULL, MODIFY COLUMN currency CHAR(3) NOT NULL',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

INSERT INTO schema_migrations (migration_key, description, checksum, applied_at)
VALUES (
  'stage_12a_tip_financial_integrity',
  'Add canonical tip recipient-wallet snapshots, generic payment-intent linkage, request fingerprints, durable tip events, and immutable reversal snapshots.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description = VALUES(description);
