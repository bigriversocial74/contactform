-- Merchant Orders Performance Hardening v1
-- Adds the read-path indexes required by the merchant Orders bulk query and detail drawer.
-- Safe to run repeatedly.

SET @db := DATABASE();

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='commerce_orders' AND INDEX_NAME='idx_commerce_orders_merchant_created') = 0,
  'ALTER TABLE commerce_orders ADD KEY idx_commerce_orders_merchant_created (merchant_user_id,created_at,id)',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='commerce_orders' AND INDEX_NAME='idx_commerce_orders_merchant_fulfillment_created') = 0,
  'ALTER TABLE commerce_orders ADD KEY idx_commerce_orders_merchant_fulfillment_created (merchant_user_id,fulfillment_status,created_at,id)',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pppm_items' AND INDEX_NAME='idx_pppm_items_source_order_line') = 0,
  'ALTER TABLE pppm_items ADD KEY idx_pppm_items_source_order_line (source_reference,source_line_reference,id)',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='microgift_instances' AND INDEX_NAME='idx_microgift_instances_order_item') = 0,
  'ALTER TABLE microgift_instances ADD KEY idx_microgift_instances_order_item (commerce_order_item_id,id)',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='payment_refunds' AND INDEX_NAME='idx_payment_refunds_order_status') = 0,
  'ALTER TABLE payment_refunds ADD KEY idx_payment_refunds_order_status (order_id,status,created_at,id)',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='payment_refunds' AND INDEX_NAME='idx_payment_refunds_merchant_status') = 0,
  'ALTER TABLE payment_refunds ADD KEY idx_payment_refunds_merchant_status (merchant_user_id,status,created_at,id)',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='payment_disputes' AND INDEX_NAME='idx_payment_disputes_order_created') = 0,
  'ALTER TABLE payment_disputes ADD KEY idx_payment_disputes_order_created (order_id,created_at,id)',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
