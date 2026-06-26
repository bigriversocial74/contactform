-- Stage 23 Buy-In final reviewer acknowledgements
-- Additive review-only table. Stores reviewer acknowledgement of a saved evidence candidate.

CREATE TABLE IF NOT EXISTS share_market_evidence_acknowledgements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  execution_attempt_id BIGINT UNSIGNED NOT NULL,
  approval_request_id BIGINT UNSIGNED NOT NULL,
  evidence_candidate_id BIGINT UNSIGNED NOT NULL,
  package_hash CHAR(64) NOT NULL,
  reviewer_role ENUM('operator','engineering','security','legal','product_owner','executive','other') NOT NULL DEFAULT 'operator',
  acknowledgement_status ENUM('acknowledged','revoked') NOT NULL DEFAULT 'acknowledged',
  reviewer_note TEXT NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sm_evidence_ack_public_id (public_id),
  KEY idx_sm_evidence_ack_attempt (execution_attempt_id,acknowledgement_status,created_at),
  KEY idx_sm_evidence_ack_candidate (evidence_candidate_id,acknowledgement_status,created_at),
  KEY idx_sm_evidence_ack_request (approval_request_id,acknowledgement_status,created_at),
  KEY idx_sm_evidence_ack_hash (package_hash),
  KEY idx_sm_evidence_ack_creator (created_by_user_id,created_at),
  CONSTRAINT fk_sm_evidence_ack_attempt FOREIGN KEY (execution_attempt_id) REFERENCES share_market_execution_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_sm_evidence_ack_request FOREIGN KEY (approval_request_id) REFERENCES share_market_approval_requests(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sm_evidence_ack_candidate FOREIGN KEY (evidence_candidate_id) REFERENCES share_market_evidence_candidates(id) ON DELETE CASCADE,
  CONSTRAINT fk_sm_evidence_ack_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_23_buy_in_final_reviewer_acknowledgements','Buy-In final reviewer acknowledgement registry for saved evidence candidates.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
