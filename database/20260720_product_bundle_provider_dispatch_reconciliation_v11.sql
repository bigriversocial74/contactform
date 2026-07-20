START TRANSACTION;

ALTER TABLE gift_bundle_settlement_transfers
  ADD COLUMN dispatch_attempt_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER transfer_status,
  ADD COLUMN next_dispatch_at DATETIME NULL AFTER dispatch_attempt_count,
  ADD COLUMN dispatch_locked_at DATETIME NULL AFTER next_dispatch_at,
  ADD COLUMN dispatch_lock_token CHAR(36) NULL AFTER dispatch_locked_at,
  ADD COLUMN last_reconciled_at DATETIME NULL AFTER failed_at,
  ADD KEY idx_bundle_transfer_dispatch (transfer_status,next_dispatch_at,dispatch_locked_at);

CREATE TABLE IF NOT EXISTS gift_bundle_provider_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  provider_key VARCHAR(40) NOT NULL DEFAULT 'stripe',
  provider_event_reference VARCHAR(255) NOT NULL,
  event_type VARCHAR(120) NOT NULL,
  transfer_id BIGINT UNSIGNED NULL,
  provider_transfer_reference VARCHAR(255) NULL,
  payload_json JSON NOT NULL,
  processing_status ENUM('received','processed','ignored','failed') NOT NULL DEFAULT 'received',
  failure_message VARCHAR(500) NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bundle_provider_event_reference (provider_key,provider_event_reference),
  KEY idx_bundle_provider_event_transfer (transfer_id,received_at),
  KEY idx_bundle_provider_event_status (processing_status,received_at),
  CONSTRAINT fk_bundle_provider_event_transfer FOREIGN KEY (transfer_id) REFERENCES gift_bundle_settlement_transfers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('20260720_product_bundle_provider_dispatch_reconciliation_v11','CLI-only Stripe transfer dispatch, retry/backoff state, provider event ingestion, and settlement reconciliation for Product Bundles.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
