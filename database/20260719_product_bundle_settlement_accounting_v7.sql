START TRANSACTION;

CREATE TABLE IF NOT EXISTS gift_bundle_component_settlements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  bundle_order_id BIGINT UNSIGNED NOT NULL,
  component_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  gross_amount_cents BIGINT UNSIGNED NOT NULL,
  commission_amount_cents BIGINT UNSIGNED NOT NULL,
  merchant_net_amount_cents BIGINT UNSIGNED NOT NULL,
  refunded_amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  reversed_amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  payable_amount_cents BIGINT UNSIGNED NOT NULL,
  settlement_policy VARCHAR(100) NOT NULL,
  readiness_status ENUM('pending','eligible','held','blocked','released','reversed') NOT NULL DEFAULT 'pending',
  hold_reason VARCHAR(190) NULL,
  eligible_at DATETIME NULL,
  released_at DATETIME NULL,
  reversed_at DATETIME NULL,
  source_snapshot_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bundle_component_settlement_public (public_id),
  UNIQUE KEY uq_bundle_component_settlement_component (component_id),
  KEY idx_bundle_settlement_merchant_status (merchant_user_id,readiness_status,created_at),
  KEY idx_bundle_settlement_order (bundle_order_id,created_at),
  CONSTRAINT fk_bundle_settlement_order FOREIGN KEY (bundle_order_id) REFERENCES gift_bundle_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_bundle_settlement_component FOREIGN KEY (component_id) REFERENCES gift_bundle_order_components(id) ON DELETE CASCADE,
  CONSTRAINT fk_bundle_settlement_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gift_bundle_settlement_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  settlement_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(100) NOT NULL,
  idempotency_key VARCHAR(190) NULL,
  event_data JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bundle_settlement_event_public (public_id),
  UNIQUE KEY uq_bundle_settlement_event_idempotency (idempotency_key),
  KEY idx_bundle_settlement_event_settlement (settlement_id,created_at),
  CONSTRAINT fk_bundle_settlement_event_settlement FOREIGN KEY (settlement_id) REFERENCES gift_bundle_component_settlements(id) ON DELETE CASCADE,
  CONSTRAINT fk_bundle_settlement_event_actor FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
