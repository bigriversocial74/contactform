-- Stage 22 Buy-In evidence candidate registry
-- Additive only. Stores selected evidence package hashes and reviewer notes without moving value or changing market state.

CREATE TABLE IF NOT EXISTS share_market_evidence_candidates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  execution_attempt_id BIGINT UNSIGNED NOT NULL,
  approval_request_id BIGINT UNSIGNED NOT NULL,
  package_hash CHAR(64) NOT NULL,
  package_json JSON NOT NULL,
  status ENUM('active','superseded','revoked') NOT NULL DEFAULT 'active',
  reviewer_note TEXT NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  superseded_at DATETIME NULL,
  revoked_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sm_evidence_candidates_public_id (public_id),
  KEY idx_sm_evidence_candidates_attempt (execution_attempt_id,status,created_at),
  KEY idx_sm_evidence_candidates_request (approval_request_id,status,created_at),
  KEY idx_sm_evidence_candidates_hash (package_hash),
  KEY idx_sm_evidence_candidates_creator (created_by_user_id,created_at),
  CONSTRAINT fk_sm_evidence_candidates_attempt FOREIGN KEY (execution_attempt_id) REFERENCES share_market_execution_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_sm_evidence_candidates_request FOREIGN KEY (approval_request_id) REFERENCES share_market_approval_requests(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sm_evidence_candidates_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_22_buy_in_evidence_candidates','Buy-In evidence candidate registry for archived evidence package hashes and reviewer notes.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
