-- Microgifter Loyalty Quest Abuse, Fraud, and Integrity Controls v1
-- Additive integrity schema. No raw IP address or device identifier is stored.

START TRANSACTION;

ALTER TABLE loyalty_quest_evidence
  ADD COLUMN IF NOT EXISTS evidence_fingerprint CHAR(64) NULL AFTER reference_id,
  ADD COLUMN IF NOT EXISTS ip_hash CHAR(64) NULL AFTER evidence_fingerprint,
  ADD COLUMN IF NOT EXISTS device_hash CHAR(64) NULL AFTER ip_hash,
  ADD COLUMN IF NOT EXISTS integrity_score SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER device_hash,
  ADD COLUMN IF NOT EXISTS integrity_status ENUM('clear','review','blocked','resolved') NOT NULL DEFAULT 'clear' AFTER integrity_score;

CREATE INDEX IF NOT EXISTS idx_lq_evidence_fingerprint
  ON loyalty_quest_evidence (campaign_id, evidence_fingerprint, created_at);
CREATE INDEX IF NOT EXISTS idx_lq_evidence_ip_velocity
  ON loyalty_quest_evidence (campaign_id, ip_hash, created_at);
CREATE INDEX IF NOT EXISTS idx_lq_evidence_device_velocity
  ON loyalty_quest_evidence (campaign_id, device_hash, created_at);
CREATE INDEX IF NOT EXISTS idx_lq_evidence_integrity_review
  ON loyalty_quest_evidence (merchant_user_id, integrity_status, integrity_score, created_at);

CREATE TABLE IF NOT EXISTS loyalty_quest_integrity_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  campaign_id BIGINT UNSIGNED NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  participant_user_id BIGINT UNSIGNED NOT NULL,
  action_type ENUM('start','submit') NOT NULL,
  outcome ENUM('allowed','review','blocked','failed') NOT NULL DEFAULT 'allowed',
  ip_hash CHAR(64) NOT NULL,
  device_hash CHAR(64) NOT NULL,
  request_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lq_integrity_attempt_public_id (public_id),
  KEY idx_lq_integrity_attempt_user (participant_user_id, action_type, created_at),
  KEY idx_lq_integrity_attempt_ip (ip_hash, action_type, created_at),
  KEY idx_lq_integrity_attempt_device (device_hash, action_type, created_at),
  KEY idx_lq_integrity_attempt_campaign (campaign_id, action_type, created_at),
  CONSTRAINT fk_lq_integrity_attempt_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_lq_integrity_attempt_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_lq_integrity_attempt_user FOREIGN KEY (participant_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyalty_quest_integrity_signals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  participant_user_id BIGINT UNSIGNED NOT NULL,
  participation_id BIGINT UNSIGNED NULL,
  evidence_id BIGINT UNSIGNED NULL,
  signal_type VARCHAR(80) NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL,
  score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('open','acknowledged','cleared','confirmed') NOT NULL DEFAULT 'open',
  source_hash CHAR(64) NOT NULL,
  context_json JSON NULL,
  resolved_by_user_id BIGINT UNSIGNED NULL,
  resolution_note VARCHAR(1000) NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lq_integrity_signal_public_id (public_id),
  UNIQUE KEY uq_lq_integrity_signal_dedupe (campaign_id, participant_user_id, signal_type, source_hash),
  KEY idx_lq_integrity_signal_merchant (merchant_user_id, status, severity, created_at),
  KEY idx_lq_integrity_signal_participant (participant_user_id, status, created_at),
  KEY idx_lq_integrity_signal_evidence (evidence_id, status, created_at),
  CONSTRAINT fk_lq_integrity_signal_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_lq_integrity_signal_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_lq_integrity_signal_user FOREIGN KEY (participant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_lq_integrity_signal_participation FOREIGN KEY (participation_id) REFERENCES loyalty_quest_participations(id) ON DELETE SET NULL,
  CONSTRAINT fk_lq_integrity_signal_evidence FOREIGN KEY (evidence_id) REFERENCES loyalty_quest_evidence(id) ON DELETE SET NULL,
  CONSTRAINT fk_lq_integrity_signal_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_name, checksum, applied_at)
VALUES ('loyalty_quest_integrity_controls_v1', SHA2('loyalty_quest_integrity_controls_v1',256), NOW())
ON DUPLICATE KEY UPDATE applied_at=VALUES(applied_at);

COMMIT;
