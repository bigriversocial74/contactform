START TRANSACTION;

CREATE TABLE IF NOT EXISTS gift_bundle_checkout_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  bundle_order_id BIGINT UNSIGNED NOT NULL,
  buyer_user_id BIGINT UNSIGNED NOT NULL,
  payment_intent_id BIGINT UNSIGNED NULL,
  provider_key VARCHAR(40) NOT NULL,
  provider_intent_reference VARCHAR(255) NULL,
  amount_cents BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL,
  checkout_status ENUM('created','requires_action','processing','succeeded','failed','cancelled') NOT NULL DEFAULT 'created',
  idempotency_key VARCHAR(190) NOT NULL,
  failure_code VARCHAR(100) NULL,
  failure_message VARCHAR(500) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gift_bundle_checkout_public_id (public_id),
  UNIQUE KEY uq_gift_bundle_checkout_idempotency (idempotency_key),
  UNIQUE KEY uq_gift_bundle_checkout_payment_intent (payment_intent_id),
  KEY idx_gift_bundle_checkout_order_status (bundle_order_id,checkout_status),
  CONSTRAINT fk_gift_bundle_checkout_order FOREIGN KEY (bundle_order_id) REFERENCES gift_bundle_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_gift_bundle_checkout_buyer FOREIGN KEY (buyer_user_id) REFERENCES users(id),
  CONSTRAINT fk_gift_bundle_checkout_intent FOREIGN KEY (payment_intent_id) REFERENCES payment_intents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gift_bundle_fulfillment_dispatches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  bundle_order_id BIGINT UNSIGNED NOT NULL,
  component_id BIGINT UNSIGNED NOT NULL,
  dispatch_type ENUM('pppm','microgift') NOT NULL,
  dispatch_status ENUM('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  idempotency_key VARCHAR(190) NOT NULL,
  attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
  external_reference VARCHAR(255) NULL,
  result_json JSON NULL,
  failure_code VARCHAR(100) NULL,
  failure_message VARCHAR(500) NULL,
  next_attempt_at DATETIME NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gift_bundle_dispatch_public_id (public_id),
  UNIQUE KEY uq_gift_bundle_dispatch_idempotency (idempotency_key),
  KEY idx_gift_bundle_dispatch_order_status (bundle_order_id,dispatch_status),
  KEY idx_gift_bundle_dispatch_component (component_id,dispatch_type),
  CONSTRAINT fk_gift_bundle_dispatch_order FOREIGN KEY (bundle_order_id) REFERENCES gift_bundle_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_gift_bundle_dispatch_component FOREIGN KEY (component_id) REFERENCES gift_bundle_order_components(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE gift_bundle_orders
  ADD COLUMN IF NOT EXISTS payment_intent_id BIGINT UNSIGNED NULL AFTER commerce_order_id,
  ADD COLUMN IF NOT EXISTS checkout_started_at DATETIME NULL AFTER reserved_at,
  ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL AFTER checkout_started_at,
  ADD COLUMN IF NOT EXISTS fulfillment_started_at DATETIME NULL AFTER paid_at,
  ADD COLUMN IF NOT EXISTS fulfilled_at DATETIME NULL AFTER fulfillment_started_at,
  ADD UNIQUE KEY IF NOT EXISTS uq_gift_bundle_orders_payment_intent (payment_intent_id),
  ADD CONSTRAINT fk_gift_bundle_orders_payment_intent FOREIGN KEY (payment_intent_id) REFERENCES payment_intents(id);

COMMIT;