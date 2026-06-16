-- Stage 3 Commerce Microgift Fulfillment Compatibility
-- Adds merchant ownership to order items so paid commerce lines can be safely
-- validated as Microgift issuance sources. The column remains nullable for
-- legacy fixtures/imports; fulfillment backfills missing values from orders.

SET @mg_has_order_item_merchant := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'commerce_order_items'
    AND COLUMN_NAME = 'merchant_user_id'
);
SET @mg_sql := IF(
  @mg_has_order_item_merchant = 0,
  'ALTER TABLE commerce_order_items ADD COLUMN merchant_user_id BIGINT UNSIGNED NULL AFTER product_version_id',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

UPDATE commerce_order_items oi
INNER JOIN commerce_orders o ON o.id = oi.order_id
SET oi.merchant_user_id = o.merchant_user_id
WHERE oi.merchant_user_id IS NULL;

SET @mg_has_order_item_merchant_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'commerce_order_items'
    AND INDEX_NAME = 'idx_commerce_order_items_merchant'
);
SET @mg_sql := IF(
  @mg_has_order_item_merchant_index = 0,
  'ALTER TABLE commerce_order_items ADD KEY idx_commerce_order_items_merchant (merchant_user_id, order_id)',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_order_item_merchant_fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'commerce_order_items'
    AND CONSTRAINT_NAME = 'fk_commerce_order_items_merchant'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @mg_sql := IF(
  @mg_has_order_item_merchant_fk = 0,
  'ALTER TABLE commerce_order_items ADD CONSTRAINT fk_commerce_order_items_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;
