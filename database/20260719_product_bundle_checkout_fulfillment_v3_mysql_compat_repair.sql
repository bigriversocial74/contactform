DELIMITER $$

DROP PROCEDURE IF EXISTS mg_product_bundle_checkout_fulfillment_v3_upgrade$$
CREATE PROCEDURE mg_product_bundle_checkout_fulfillment_v3_upgrade()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gift_bundle_orders' AND COLUMN_NAME = 'payment_intent_id'
  ) THEN
    ALTER TABLE gift_bundle_orders ADD COLUMN payment_intent_id BIGINT UNSIGNED NULL AFTER commerce_order_id;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gift_bundle_orders' AND COLUMN_NAME = 'checkout_started_at'
  ) THEN
    ALTER TABLE gift_bundle_orders ADD COLUMN checkout_started_at DATETIME NULL AFTER reserved_at;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gift_bundle_orders' AND COLUMN_NAME = 'paid_at'
  ) THEN
    ALTER TABLE gift_bundle_orders ADD COLUMN paid_at DATETIME NULL AFTER checkout_started_at;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gift_bundle_orders' AND COLUMN_NAME = 'fulfillment_started_at'
  ) THEN
    ALTER TABLE gift_bundle_orders ADD COLUMN fulfillment_started_at DATETIME NULL AFTER paid_at;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gift_bundle_orders' AND COLUMN_NAME = 'fulfilled_at'
  ) THEN
    ALTER TABLE gift_bundle_orders ADD COLUMN fulfilled_at DATETIME NULL AFTER fulfillment_started_at;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gift_bundle_orders' AND INDEX_NAME = 'uq_gift_bundle_orders_payment_intent'
  ) THEN
    ALTER TABLE gift_bundle_orders ADD UNIQUE KEY uq_gift_bundle_orders_payment_intent (payment_intent_id);
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'gift_bundle_orders'
      AND CONSTRAINT_NAME = 'fk_gift_bundle_orders_payment_intent'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
  ) THEN
    ALTER TABLE gift_bundle_orders
      ADD CONSTRAINT fk_gift_bundle_orders_payment_intent
      FOREIGN KEY (payment_intent_id) REFERENCES payment_intents(id);
  END IF;
END$$

CALL mg_product_bundle_checkout_fulfillment_v3_upgrade()$$
DROP PROCEDURE IF EXISTS mg_product_bundle_checkout_fulfillment_v3_upgrade$$

DELIMITER ;
