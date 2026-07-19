START TRANSACTION;

CREATE TABLE IF NOT EXISTS gift_bundle_settlement_adjustments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  settlement_id BIGINT UNSIGNED NOT NULL,
  transfer_id BIGINT UNSIGNED NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  adjustment_type ENUM('refund','partial_refund','dispute','reversal_request','reversal','recovery') NOT NULL,
  adjustment_status ENUM('created','review_required','approved','dispatch_pending','submitted','succeeded','failed','cancelled') NOT NULL DEFAULT 'created',
  amount_cents BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL,
  reason VARCHAR(500) NOT NULL,
  source_reference VARCHAR(255) NULL,
  provider_reversal_reference VARCHAR(255) NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  request_snapshot_json JSON NOT NULL,
  response_snapshot_json JSON NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  approved_by_user_id BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  submitted_at DATETIME NULL,
  succeeded_at DATETIME NULL,
  failed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bundle_adjustment_public_id (public_id),
  UNIQUE KEY uq_bundle_adjustment_idempotency (idempotency_key),
  UNIQUE KEY uq_bundle_adjustment_provider_ref (provider_reversal_reference),
  KEY idx_bundle_adjustment_settlement (settlement_id,adjustment_status,created_at),
  KEY idx_bundle_adjustment_merchant (merchant_user_id,adjustment_status,created_at),
  CONSTRAINT fk_bundle_adjustment_settlement FOREIGN KEY (settlement_id) REFERENCES gift_bundle_component_settlements(id) ON DELETE CASCADE,
  CONSTRAINT fk_bundle_adjustment_transfer FOREIGN KEY (transfer_id) REFERENCES gift_bundle_settlement_transfers(id) ON DELETE SET NULL,
  CONSTRAINT fk_bundle_adjustment_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id),
  CONSTRAINT fk_bundle_adjustment_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id),
  CONSTRAINT fk_bundle_adjustment_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('20260719_product_bundle_refunds_reversals_v10','Product Bundle refund adjustments, dispute holds, reversal requests, and provider reconciliation records.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
