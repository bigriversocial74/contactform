-- Microgifter Loyalty Quest Abuse, Fraud, and Integrity Controls v1
-- Additive integrity schema. No raw IP address or device identifier is stored.
-- Uses information_schema guards for MySQL 8 compatibility and safe repeat execution.

START TRANSACTION;

SET @mg_lqi_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='loyalty_quest_evidence' AND COLUMN_NAME='evidence_fingerprint')=0,
  'ALTER TABLE loyalty_quest_evidence ADD COLUMN evidence_fingerprint CHAR(64) NULL AFTER reference_id',
  'SELECT 1'
);
PREPARE mg_lqi_stmt FROM @mg_lqi_sql; EXECUTE mg_lqi_stmt; DEALLOCATE PREPARE mg_lqi_stmt;

SET @mg_lqi_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='loyalty_quest_evidence' AND COLUMN_NAME='ip_hash')=0,
  'ALTER TABLE loyalty_quest_evidence ADD COLUMN ip_hash CHAR(64) NULL AFTER evidence_fingerprint',
  'SELECT 1'
);
PREPARE mg_lqi_stmt FROM @mg_lqi_sql; EXECUTE mg_lqi_stmt; DEALLOCATE PREPARE mg_lqi_stmt;

SET @mg_lqi_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='loyalty_quest_evidence' AND COLUMN_NAME='device_hash')=0,
  'ALTER TABLE loyalty_quest_evidence ADD COLUMN device_hash CHAR(64) NULL AFTER ip_hash',
  'SELECT 1'
);
PREPARE mg_lqi_stmt FROM @mg_lqi_sql; EXECUTE mg_lqi_stmt; DEALLOCATE PREPARE mg_lqi_stmt;

SET @mg_lqi_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='loyalty_quest_evidence' AND COLUMN_NAME='integrity_score')=0,
  'ALTER TABLE loyalty_quest_evidence ADD COLUMN integrity_score SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER device_hash',
  'SELECT 1'
);
PREPARE mg_lqi_stmt FROM @mg_lqi_sql; EXECUTE mg_lqi_stmt; DEALLOCATE PREPARE mg_lqi_stmt;

SET @mg_lqi_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='loyalty_quest_evidence' AND COLUMN_NAME='integrity_status')=0,
  'ALTER TABLE loyalty_quest_evidence ADD COLUMN integrity_status ENUM(''clear'',''review'',''blocked'',''resolved'') NOT NULL DEFAULT ''clear'' AFTER integrity_score',
  'SELECT 1'
);
PREPARE mg_lqi_stmt FROM @mg_lqi_sql; EXECUTE mg_lqi_stmt; DEALLOCATE PREPARE mg_lqi_stmt;

SET @mg_lqi_sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='loyalty_quest_evidence' AND INDEX_NAME='idx_lq_evidence_fingerprint')=0,
  'CREATE INDEX idx_lq_evidence_fingerprint ON loyalty_quest_evidence (campaign_id,evidence_fingerprint,created_at)',
  'SELECT 1'
);
PREPARE mg_lqi_stmt FROM @mg_lqi_sql; EXECUTE mg_lqi_stmt; DEALLOCATE PREPARE mg_lqi_stmt;

SET @mg_lqi_sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='loyalty_quest_evidence' AND INDEX_NAME='idx_lq_evidence_ip_velocity')=0,
  'CREATE INDEX idx_lq_evidence_ip_velocity ON loyalty_quest_evidence (campaign_id,ip_hash,created_at)',
  'SELECT 1'
);
PREPARE mg_lqi_stmt FROM @mg_lqi_sql; EXECUTE mg_lqi_stmt; DEALLOCATE PREPARE mg_lqi_stmt;

SET @mg_lqi_sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='loyalty_quest_evidence' AND INDEX_NAME='idx_lq_evidence_device_velocity')=0,
  'CREATE INDEX idx_lq_evidence_device_velocity ON loyalty_quest_evidence (campaign_id,device_hash,created_at)',
  'SELECT 1'
);
PREPARE mg_lqi_stmt FROM @mg_lqi_sql; EXECUTE mg_lqi_stmt; DEALLOCATE PREPARE mg_lqi_stmt;

SET @mg_lqi_sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='loyalty_quest_evidence' AND INDEX_NAME='idx_lq_evidence_integrity_review')=0,
  'CREATE INDEX idx_lq_evidence_integrity_review ON loyalty_quest_evidence (merchant_user_id,integrity_status,integrity_score,created_at)',
  'SELECT 1'
);
PREPARE mg_lqi_stmt FROM @mg_lqi_sql; EXECUTE mg_lqi_stmt; DEALLOCATE PREPARE mg_lqi_stmt;

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
  KEY idx_lq_integrity_attempt_user (participant_user_id,action_type,created_at),
  KEY idx_lq_integrity_attempt_ip (ip_hash,action_type,created_at),
  KEY idx_lq_integrity_attempt_device (device_hash,action_type,created_at),
  KEY idx_lq_integrity_attempt_campaign (campaign_id,action_type,created_at),
  KEY idx_lq_integrity_attempt_request (request_hash),
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
  UNIQUE KEY uq_lq_integrity_signal_dedupe (campaign_id,participant_user_id,evidence_id,signal_type,source_hash),
  KEY idx_lq_integrity_signal_merchant (merchant_user_id,status,severity,created_at),
  KEY idx_lq_integrity_signal_participant (participant_user_id,status,created_at),
  KEY idx_lq_integrity_signal_evidence (evidence_id,status,created_at),
  CONSTRAINT fk_lq_integrity_signal_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_lq_integrity_signal_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_lq_integrity_signal_user FOREIGN KEY (participant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_lq_integrity_signal_participation FOREIGN KEY (participation_id) REFERENCES loyalty_quest_participations(id) ON DELETE SET NULL,
  CONSTRAINT fk_lq_integrity_signal_evidence FOREIGN KEY (evidence_id) REFERENCES loyalty_quest_evidence(id) ON DELETE SET NULL,
  CONSTRAINT fk_lq_integrity_signal_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('loyalty_quest_integrity_controls_v1','Loyalty Quest request throttling, evidence fingerprints, integrity scoring, review routing, and audited resolution.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
