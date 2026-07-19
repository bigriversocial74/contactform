START TRANSACTION;

CREATE TABLE IF NOT EXISTS gift_bundle_settlement_transfers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  settlement_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  provider_key VARCHAR(40) NOT NULL DEFAULT 'stripe',
  provider_account_reference VARCHAR(255) NOT NULL,
  provider_transfer_reference VARCHAR(255) NULL,
  amount_cents BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL,
  transfer_status ENUM('created','submitted','succeeded','failed','cancelled','reversed') NOT NULL DEFAULT 'created',
  idempotency_key VARCHAR(190) NOT NULL,
  failure_code VARCHAR(100) NULL,
  failure_message VARCHAR(500) NULL,
  request_snapshot_json JSON NOT NULL,
  response_snapshot_json JSON NULL,
  initiated_by_user_id BIGINT UNSIGNED NOT NULL,
  submitted_at DATETIME NULL,
  succeeded_at DATETIME NULL,
  failed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bundle_transfer_public_id (public_id),
  UNIQUE KEY uq_bundle_transfer_settlement (settlement_id),
  UNIQUE KEY uq_bundle_transfer_idempotency (idempotency_key),
  UNIQUE KEY uq_bundle_transfer_provider_ref (provider_key,provider_transfer_reference),
  KEY idx_bundle_transfer_merchant_status (merchant_user_id,transfer_status,created_at),
  CONSTRAINT fk_bundle_transfer_settlement FOREIGN KEY (settlement_id) REFERENCES gift_bundle_component_settlements(id) ON DELETE CASCADE,
  CONSTRAINT fk_bundle_transfer_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id),
  CONSTRAINT fk_bundle_transfer_actor FOREIGN KEY (initiated_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('20260719_product_bundle_stripe_transfers_v9','Guarded, idempotent Stripe Connect transfer execution records for release-ready Product Bundle settlements.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
