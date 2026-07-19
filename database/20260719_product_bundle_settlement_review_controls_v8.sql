START TRANSACTION;

ALTER TABLE gift_bundle_component_settlements
  ADD COLUMN review_status ENUM('unreviewed','approved','held','blocked','release_ready') NOT NULL DEFAULT 'unreviewed' AFTER readiness_status,
  ADD COLUMN review_reason VARCHAR(255) NULL AFTER review_status,
  ADD COLUMN reviewed_by_user_id BIGINT UNSIGNED NULL AFTER review_reason,
  ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by_user_id,
  ADD COLUMN release_gate_passed_at DATETIME NULL AFTER reviewed_at,
  ADD KEY idx_bundle_settlement_review_queue (review_status,readiness_status,created_at),
  ADD CONSTRAINT fk_bundle_settlement_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id);

CREATE TABLE IF NOT EXISTS gift_bundle_settlement_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  settlement_id BIGINT UNSIGNED NOT NULL,
  reviewer_user_id BIGINT UNSIGNED NOT NULL,
  action ENUM('approve','hold','block','mark_release_ready','reopen') NOT NULL,
  previous_status VARCHAR(40) NOT NULL,
  resulting_status VARCHAR(40) NOT NULL,
  reason VARCHAR(255) NULL,
  review_snapshot_json JSON NOT NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bundle_settlement_review_public_id (public_id),
  UNIQUE KEY uq_bundle_settlement_review_idempotency (idempotency_key),
  KEY idx_bundle_settlement_reviews_settlement (settlement_id,created_at),
  CONSTRAINT fk_bundle_settlement_reviews_settlement FOREIGN KEY (settlement_id) REFERENCES gift_bundle_component_settlements(id) ON DELETE CASCADE,
  CONSTRAINT fk_bundle_settlement_reviews_reviewer FOREIGN KEY (reviewer_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
