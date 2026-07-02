-- Remaining SQL Diagnostics Repair - 2026-07-02
-- Purpose: clear real SQL gaps found by System SQL Diagnostics after PR #621.
-- Compatibility: avoids ALTER TABLE ... ADD COLUMN IF NOT EXISTS for older MySQL/MariaDB hosts.
-- Safe to re-run. Existing columns/tables are skipped.

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND COLUMN_NAME = 'playbook_slug') = 0,
  'ALTER TABLE admin_user_notes ADD COLUMN playbook_slug VARCHAR(80) NULL',
  'SELECT "admin_user_notes.playbook_slug exists or table missing" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND COLUMN_NAME = 'resolution_template_slug') = 0,
  'ALTER TABLE admin_user_notes ADD COLUMN resolution_template_slug VARCHAR(80) NULL',
  'SELECT "admin_user_notes.resolution_template_slug exists or table missing" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND INDEX_NAME = 'idx_admin_user_notes_playbook');
SET @has_cols := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND COLUMN_NAME IN ('playbook_slug','status','updated_at'));
SET @sql := IF(@idx = 0 AND @has_cols = 3, 'CREATE INDEX idx_admin_user_notes_playbook ON admin_user_notes(playbook_slug,status,updated_at)', 'SELECT "idx_admin_user_notes_playbook exists or columns missing" AS status');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND INDEX_NAME = 'idx_admin_user_notes_template');
SET @has_cols := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND COLUMN_NAME IN ('resolution_template_slug','status','updated_at'));
SET @sql := IF(@idx = 0 AND @has_cols = 3, 'CREATE INDEX idx_admin_user_notes_template ON admin_user_notes(resolution_template_slug,status,updated_at)', 'SELECT "idx_admin_user_notes_template exists or columns missing" AS status');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS microgift_claim_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  instance_id BIGINT UNSIGNED NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  location_id BIGINT UNSIGNED NULL,
  merchant_claim_code_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  result ENUM('approved','invalid_gift','gift_not_paid','invalid_state','gift_expired','already_claimed','merchant_mismatch','invalid_location','location_not_allowed','invalid_claim_code','unauthorized_claim_actor','rate_limited','internal_error') NOT NULL,
  reason_code VARCHAR(80) NOT NULL,
  idempotency_key VARCHAR(190) NULL,
  correlation_id VARCHAR(190) NULL,
  request_fingerprint CHAR(64) NULL,
  ip_hash CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  risk_json JSON NULL,
  metadata_json JSON NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgift_claim_attempts_public_id (public_id),
  KEY idx_microgift_claim_attempts_instance (instance_id,attempted_at),
  KEY idx_microgift_claim_attempts_location (location_id,attempted_at),
  KEY idx_microgift_claim_attempts_merchant (merchant_user_id,attempted_at),
  KEY idx_microgift_claim_attempts_actor (actor_user_id,attempted_at),
  KEY idx_microgift_claim_attempts_result (result,attempted_at),
  KEY idx_microgift_claim_attempts_correlation (correlation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS microgift_claim_escalations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  location_id BIGINT UNSIGNED NULL,
  instance_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  review_item_id BIGINT UNSIGNED NULL,
  trigger_type ENUM('rate_limit','repeated_invalid_code','merchant_mismatch','location_mismatch','internal_error','manual') NOT NULL,
  severity ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  status ENUM('open','in_review','resolved','dismissed') NOT NULL DEFAULT 'open',
  attempt_count INT UNSIGNED NOT NULL DEFAULT 1,
  summary VARCHAR(255) NOT NULL,
  details_json JSON NULL,
  first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgift_claim_escalation_public_id (public_id),
  KEY idx_microgift_claim_escalation_status (status,severity,last_seen_at),
  KEY idx_microgift_claim_escalation_merchant (merchant_user_id,status,last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS microgift_claim_rate_limits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket_key CHAR(64) NOT NULL,
  scope ENUM('actor','merchant','location','network','gift') NOT NULL,
  subject_reference VARCHAR(190) NOT NULL,
  window_started_at DATETIME NOT NULL,
  window_seconds INT UNSIGNED NOT NULL,
  attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
  limit_count INT UNSIGNED NOT NULL,
  blocked_until DATETIME NULL,
  last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgift_claim_rate_bucket (bucket_key),
  KEY idx_microgift_claim_rate_blocked (blocked_until),
  KEY idx_microgift_claim_rate_scope_subject (scope,subject_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.queue_playbooks.view','View admin queue playbooks','View support playbooks, resolution templates, and queue checklist guidance.',NOW()),
('admin.queue_playbooks.manage','Manage admin queue playbooks','Apply playbooks, resolution templates, and checklist updates to admin follow-up notes.',NOW()),
('merchant.location_claim.execute','Execute merchant location claims','Submit authorized merchant-location Microgift claim and redemption operations.',NOW()),
('merchant.location_claim.history','View merchant claim history','View merchant-location claim attempts, outcomes, and redemption history.',NOW()),
('microgift.claim_escalations.manage','Manage claim escalations','Review and resolve merchant-location claim security escalations.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.queue_playbooks.view','admin.queue_playbooks.manage','merchant.location_claim.execute','merchant.location_claim.history','microgift.claim_escalations.manage')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('remaining_sql_diagnostics_repair_20260702','Repairs queue playbook columns and merchant claim attempt/escalation tables found by system SQL diagnostics.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
