START TRANSACTION;

ALTER TABLE gift_bundle_settlement_adjustments
  ADD COLUMN dispatch_attempt_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER response_snapshot_json,
  ADD COLUMN next_dispatch_at DATETIME NULL AFTER dispatch_attempt_count,
  ADD COLUMN dispatch_locked_at DATETIME NULL AFTER next_dispatch_at,
  ADD COLUMN dispatch_lock_token CHAR(36) NULL AFTER dispatch_locked_at,
  ADD COLUMN last_reconciled_at DATETIME NULL AFTER dispatch_lock_token,
  ADD COLUMN failure_code VARCHAR(100) NULL AFTER last_reconciled_at,
  ADD COLUMN failure_message VARCHAR(500) NULL AFTER failure_code,
  ADD KEY idx_bundle_adjustment_dispatch (adjustment_status,next_dispatch_at,dispatch_locked_at);

CREATE TABLE IF NOT EXISTS gift_bundle_provider_dead_letters (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  provider_key VARCHAR(40) NOT NULL,
  source_type ENUM('transfer','reversal','webhook','reconciliation') NOT NULL,
  source_public_id CHAR(36) NULL,
  provider_reference VARCHAR(255) NULL,
  failure_code VARCHAR(100) NOT NULL,
  failure_message VARCHAR(500) NOT NULL,
  payload_json JSON NULL,
  status ENUM('open','retrying','resolved','ignored') NOT NULL DEFAULT 'open',
  retry_count INT UNSIGNED NOT NULL DEFAULT 0,
  next_retry_at DATETIME NULL,
  resolved_by_user_id BIGINT UNSIGNED NULL,
  resolution_note VARCHAR(500) NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bundle_dead_letter_public (public_id),
  KEY idx_bundle_dead_letter_status (status,next_retry_at,created_at),
  KEY idx_bundle_dead_letter_source (source_type,source_public_id),
  CONSTRAINT fk_bundle_dead_letter_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gift_bundle_settlement_incidents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  settlement_id BIGINT UNSIGNED NULL,
  transfer_id BIGINT UNSIGNED NULL,
  adjustment_id BIGINT UNSIGNED NULL,
  incident_type ENUM('overpayment_risk','provider_mismatch','stale_dispatch','retry_exhausted','negative_payable','manual_review') NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  status ENUM('open','investigating','resolved','dismissed') NOT NULL DEFAULT 'open',
  summary VARCHAR(500) NOT NULL,
  evidence_json JSON NULL,
  opened_by_user_id BIGINT UNSIGNED NULL,
  resolved_by_user_id BIGINT UNSIGNED NULL,
  resolution_note VARCHAR(500) NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bundle_incident_public (public_id),
  KEY idx_bundle_incident_status (status,severity,created_at),
  CONSTRAINT fk_bundle_incident_settlement FOREIGN KEY (settlement_id) REFERENCES gift_bundle_component_settlements(id) ON DELETE SET NULL,
  CONSTRAINT fk_bundle_incident_transfer FOREIGN KEY (transfer_id) REFERENCES gift_bundle_settlement_transfers(id) ON DELETE SET NULL,
  CONSTRAINT fk_bundle_incident_adjustment FOREIGN KEY (adjustment_id) REFERENCES gift_bundle_settlement_adjustments(id) ON DELETE SET NULL,
  CONSTRAINT fk_bundle_incident_opener FOREIGN KEY (opened_by_user_id) REFERENCES users(id),
  CONSTRAINT fk_bundle_incident_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('20260720_product_bundle_production_hardening_v12','Product Bundle reversal dispatch, dead letters, incidents, retry controls, and production safeguards.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
