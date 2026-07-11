-- Loyalty Quest Notifications and Transactional Delivery v1
-- Additive hardening for the shared message-delivery authority.
-- Uses information_schema guards for MySQL 8 compatibility and safe repeat execution.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS message_delivery_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  job_id BIGINT UNSIGNED NOT NULL,
  attempt_no INT NOT NULL,
  provider_key VARCHAR(80) NOT NULL,
  status ENUM('success','transient_failure','permanent_failure') NOT NULL,
  error_code VARCHAR(100) NULL,
  error_message VARCHAR(500) NULL,
  provider_response_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_message_delivery_attempts_public_id (public_id),
  UNIQUE KEY uq_message_delivery_attempts_job_attempt (job_id,attempt_no),
  KEY idx_message_delivery_attempts_job (job_id,created_at),
  CONSTRAINT fk_message_delivery_attempts_job FOREIGN KEY (job_id) REFERENCES message_delivery_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_provider_callbacks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  provider_key VARCHAR(80) NOT NULL,
  provider_event_id VARCHAR(190) NOT NULL,
  job_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  payload_hash CHAR(64) NOT NULL,
  payload_json JSON NULL,
  status ENUM('processed','ignored') NOT NULL DEFAULT 'processed',
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_message_provider_callbacks_public_id (public_id),
  UNIQUE KEY uq_message_provider_callbacks_event (provider_key,provider_event_id),
  KEY idx_message_provider_callbacks_job (job_id,received_at),
  CONSTRAINT fk_message_provider_callbacks_job FOREIGN KEY (job_id) REFERENCES message_delivery_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_suppression_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  channel ENUM('in_app','email','sms','webhook') NOT NULL,
  category VARCHAR(60) NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_message_suppression (user_id,channel,category),
  KEY idx_message_suppression_active (user_id,channel,category,status),
  CONSTRAINT fk_message_suppression_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @mg_lqn_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='message_delivery_jobs' AND COLUMN_NAME='merchant_user_id')=0,
  'ALTER TABLE message_delivery_jobs ADD COLUMN merchant_user_id BIGINT UNSIGNED NULL AFTER recipient_user_id',
  'SELECT 1'
);
PREPARE mg_lqn_stmt FROM @mg_lqn_sql; EXECUTE mg_lqn_stmt; DEALLOCATE PREPARE mg_lqn_stmt;

SET @mg_lqn_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='message_delivery_jobs' AND COLUMN_NAME='campaign_id')=0,
  'ALTER TABLE message_delivery_jobs ADD COLUMN campaign_id BIGINT UNSIGNED NULL AFTER merchant_user_id',
  'SELECT 1'
);
PREPARE mg_lqn_stmt FROM @mg_lqn_sql; EXECUTE mg_lqn_stmt; DEALLOCATE PREPARE mg_lqn_stmt;

SET @mg_lqn_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='message_delivery_jobs' AND COLUMN_NAME='source_public_id')=0,
  'ALTER TABLE message_delivery_jobs ADD COLUMN source_public_id VARCHAR(190) NULL AFTER campaign_id',
  'SELECT 1'
);
PREPARE mg_lqn_stmt FROM @mg_lqn_sql; EXECUTE mg_lqn_stmt; DEALLOCATE PREPARE mg_lqn_stmt;

SET @mg_lqn_has_merchant_idx = (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='message_delivery_jobs' AND index_name='idx_message_delivery_jobs_merchant'
);
SET @mg_lqn_merchant_idx_sql = IF(
  @mg_lqn_has_merchant_idx=0,
  'ALTER TABLE message_delivery_jobs ADD KEY idx_message_delivery_jobs_merchant (merchant_user_id,status,next_attempt_at,id)',
  'SELECT 1'
);
PREPARE mg_lqn_merchant_idx_stmt FROM @mg_lqn_merchant_idx_sql;
EXECUTE mg_lqn_merchant_idx_stmt;
DEALLOCATE PREPARE mg_lqn_merchant_idx_stmt;

SET @mg_lqn_has_campaign_idx = (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='message_delivery_jobs' AND index_name='idx_message_delivery_jobs_campaign'
);
SET @mg_lqn_campaign_idx_sql = IF(
  @mg_lqn_has_campaign_idx=0,
  'ALTER TABLE message_delivery_jobs ADD KEY idx_message_delivery_jobs_campaign (campaign_id,status,created_at)',
  'SELECT 1'
);
PREPARE mg_lqn_campaign_idx_stmt FROM @mg_lqn_campaign_idx_sql;
EXECUTE mg_lqn_campaign_idx_stmt;
DEALLOCATE PREPARE mg_lqn_campaign_idx_stmt;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('loyalty_quest_notifications_transactional_delivery_v1','Loyalty Quest notification delivery attempts, callbacks, suppression, scheduling, external recipients, and merchant/campaign evidence.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
