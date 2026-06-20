-- V1 Stage C: checkout session to payment intent authority
-- Every checkout session resolves one specific payment intent. Legacy rows are
-- backfilled to the nearest compatible intent for the same order and provider.

SET @mg_has_checkout_session_intent := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'checkout_sessions'
    AND COLUMN_NAME = 'payment_intent_id'
);
SET @mg_sql := IF(
  @mg_has_checkout_session_intent = 0,
  'ALTER TABLE checkout_sessions ADD COLUMN payment_intent_id BIGINT UNSIGNED NULL AFTER order_id',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

UPDATE checkout_sessions cs
SET cs.payment_intent_id = (
  SELECT pi.id
  FROM payment_intents pi
  WHERE pi.order_id = cs.order_id
    AND pi.provider_key = cs.provider_key
  ORDER BY
    CASE WHEN pi.created_at <= cs.created_at THEN 0 ELSE 1 END,
    ABS(TIMESTAMPDIFF(SECOND, pi.created_at, cs.created_at)),
    pi.id DESC
  LIMIT 1
)
WHERE cs.payment_intent_id IS NULL;

SET @mg_has_checkout_session_intent_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'checkout_sessions'
    AND INDEX_NAME = 'idx_checkout_sessions_payment_intent'
);
SET @mg_sql := IF(
  @mg_has_checkout_session_intent_index = 0,
  'ALTER TABLE checkout_sessions ADD KEY idx_checkout_sessions_payment_intent (payment_intent_id)',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_checkout_session_intent_fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'checkout_sessions'
    AND CONSTRAINT_NAME = 'fk_checkout_sessions_payment_intent'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @mg_sql := IF(
  @mg_has_checkout_session_intent_fk = 0,
  'ALTER TABLE checkout_sessions ADD CONSTRAINT fk_checkout_sessions_payment_intent FOREIGN KEY (payment_intent_id) REFERENCES payment_intents(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;
