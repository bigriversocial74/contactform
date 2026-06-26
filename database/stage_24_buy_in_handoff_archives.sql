-- Stage 24 Buy-In handoff archives
-- Additive archive-only table. Stores reviewed preflight handoff checklist hashes.

CREATE TABLE IF NOT EXISTS share_market_handoff_archives (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  execution_attempt_id BIGINT UNSIGNED NOT NULL,
  approval_request_id BIGINT UNSIGNED NOT NULL,
  acknowledgement_id BIGINT UNSIGNED NULL,
  evidence_candidate_id BIGINT UNSIGNED NULL,
  handoff_hash CHAR(64) NOT NULL,
  handoff_json JSON NOT NULL,
  handoff_ready TINYINT(1) NOT NULL DEFAULT 0,
  acknowledged_package_hash CHAR(64) NULL,
  current_package_hash CHAR(64) NULL,
  drift_status ENUM('matching','drifted','missing','unknown') NOT NULL DEFAULT 'unknown',
  reviewer_note TEXT NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sm_handoff_archive_public_id (public_id),
  KEY idx_sm_handoff_archive_attempt (execution_attempt_id,created_at),
  KEY idx_sm_handoff_archive_request (approval_request_id,created_at),
  KEY idx_sm_handoff_archive_ack (acknowledgement_id,created_at),
  KEY idx_sm_handoff_archive_candidate (evidence_candidate_id,created_at),
  KEY idx_sm_handoff_archive_hash (handoff_hash),
  KEY idx_sm_handoff_archive_creator (created_by_user_id,created_at),
  CONSTRAINT fk_sm_handoff_archive_attempt FOREIGN KEY (execution_attempt_id) REFERENCES share_market_execution_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_sm_handoff_archive_request FOREIGN KEY (approval_request_id) REFERENCES share_market_approval_requests(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sm_handoff_archive_ack FOREIGN KEY (acknowledgement_id) REFERENCES share_market_evidence_acknowledgements(id) ON DELETE SET NULL,
  CONSTRAINT fk_sm_handoff_archive_candidate FOREIGN KEY (evidence_candidate_id) REFERENCES share_market_evidence_candidates(id) ON DELETE SET NULL,
  CONSTRAINT fk_sm_handoff_archive_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_24_buy_in_handoff_archives','Buy-In preflight handoff archive registry.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
