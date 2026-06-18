-- Stage 10F — Architecture, Deployment, and Action Center Readiness Reconciliation

CREATE TABLE IF NOT EXISTS microgift_claim_attempt_security (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  attempt_id BIGINT UNSIGNED NOT NULL,
  request_fingerprint CHAR(64) NULL,
  ip_hash CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  risk_json JSON NULL,
  metadata_json JSON NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgift_claim_attempt_security_attempt (attempt_id),
  KEY idx_microgift_claim_attempt_security_expiry (expires_at),
  CONSTRAINT fk_microgift_claim_attempt_security_attempt FOREIGN KEY (attempt_id) REFERENCES microgift_claim_attempts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO microgift_claim_attempt_security
(attempt_id,request_fingerprint,ip_hash,user_agent_hash,risk_json,metadata_json,expires_at,created_at)
SELECT id,request_fingerprint,ip_hash,user_agent_hash,risk_json,metadata_json,DATE_ADD(attempted_at,INTERVAL 365 DAY),created_at
FROM microgift_claim_attempts
WHERE request_fingerprint IS NOT NULL OR ip_hash IS NOT NULL OR user_agent_hash IS NOT NULL OR risk_json IS NOT NULL OR metadata_json IS NOT NULL;

SET @mg_has_folder := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='microgift_inbox_items' AND COLUMN_NAME='folder');
SET @mg_sql := IF(@mg_has_folder=0,"ALTER TABLE microgift_inbox_items ADD COLUMN folder ENUM('inbox','sent','claimed') NOT NULL DEFAULT 'inbox' AFTER user_id",'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_sender := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='microgift_inbox_items' AND COLUMN_NAME='sender_user_id');
SET @mg_sql := IF(@mg_has_sender=0,'ALTER TABLE microgift_inbox_items ADD COLUMN sender_user_id BIGINT UNSIGNED NULL AFTER folder','SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_recipient := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='microgift_inbox_items' AND COLUMN_NAME='recipient_user_id');
SET @mg_sql := IF(@mg_has_recipient=0,'ALTER TABLE microgift_inbox_items ADD COLUMN recipient_user_id BIGINT UNSIGNED NULL AFTER sender_user_id','SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_sent_at := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='microgift_inbox_items' AND COLUMN_NAME='sent_at');
SET @mg_sql := IF(@mg_has_sent_at=0,'ALTER TABLE microgift_inbox_items ADD COLUMN sent_at DATETIME NULL AFTER first_received_at','SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_read_at := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='microgift_inbox_items' AND COLUMN_NAME='read_at');
SET @mg_sql := IF(@mg_has_read_at=0,'ALTER TABLE microgift_inbox_items ADD COLUMN read_at DATETIME NULL AFTER redeemed_at','SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_archived_at := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='microgift_inbox_items' AND COLUMN_NAME='archived_at');
SET @mg_sql := IF(@mg_has_archived_at=0,'ALTER TABLE microgift_inbox_items ADD COLUMN archived_at DATETIME NULL AFTER read_at','SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

ALTER TABLE microgift_inbox_items MODIFY COLUMN state ENUM('received','claimable','redeemable','claimed','redeemed','expired','revoked') NOT NULL DEFAULT 'received';
UPDATE microgift_inbox_items SET state='redeemable' WHERE state='claimable';
UPDATE microgift_inbox_items SET state='redeemed',folder='claimed' WHERE state='claimed';
UPDATE microgift_inbox_items SET folder='claimed' WHERE state='redeemed';

SET @mg_has_folder_index := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='microgift_inbox_items' AND INDEX_NAME='idx_microgift_inbox_user_folder_state');
SET @mg_sql := IF(@mg_has_folder_index=0,'CREATE INDEX idx_microgift_inbox_user_folder_state ON microgift_inbox_items(user_id,folder,state,updated_at)','SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('action_center.view','View Action Center','View user-scoped Inbox, Sent, and Claimed gift activity.',NOW()),
('action_center.manage','Manage Action Center','Mark Action Center items read or archived without changing gift ownership.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN ('action_center.view','action_center.manage') WHERE r.slug IN ('customer','merchant','admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_10f_architecture_deployment_action_center','Action Center folder model, immutable attempt audit separation, deployment reconciliation, and Stage 11 readiness.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
