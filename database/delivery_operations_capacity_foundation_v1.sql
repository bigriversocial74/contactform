-- Microgifter Delivery Operations & Capacity Foundation v1
-- Extends the existing notification_delivery_jobs outbox without creating a parallel reward authority.

SET @db := DATABASE();

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_delivery_jobs' AND COLUMN_NAME='job_key') = 0,
  'ALTER TABLE notification_delivery_jobs ADD COLUMN job_key CHAR(64) NULL AFTER public_id',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_delivery_jobs' AND COLUMN_NAME='merchant_user_id') = 0,
  'ALTER TABLE notification_delivery_jobs ADD COLUMN merchant_user_id BIGINT UNSIGNED NULL AFTER user_id',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_delivery_jobs' AND COLUMN_NAME='priority') = 0,
  'ALTER TABLE notification_delivery_jobs ADD COLUMN priority TINYINT UNSIGNED NOT NULL DEFAULT 5 AFTER channel',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_delivery_jobs' AND COLUMN_NAME='lease_token') = 0,
  'ALTER TABLE notification_delivery_jobs ADD COLUMN lease_token CHAR(64) NULL AFTER attempt_count',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_delivery_jobs' AND COLUMN_NAME='lease_expires_at') = 0,
  'ALTER TABLE notification_delivery_jobs ADD COLUMN lease_expires_at DATETIME NULL AFTER lease_token',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_delivery_jobs' AND COLUMN_NAME='last_attempt_at') = 0,
  'ALTER TABLE notification_delivery_jobs ADD COLUMN last_attempt_at DATETIME NULL AFTER lease_expires_at',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_delivery_jobs' AND COLUMN_NAME='accepted_at') = 0,
  'ALTER TABLE notification_delivery_jobs ADD COLUMN accepted_at DATETIME NULL AFTER last_attempt_at',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_delivery_jobs' AND COLUMN_NAME='suppressed_at') = 0,
  'ALTER TABLE notification_delivery_jobs ADD COLUMN suppressed_at DATETIME NULL AFTER failed_at',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_delivery_jobs' AND COLUMN_NAME='cancelled_at') = 0,
  'ALTER TABLE notification_delivery_jobs ADD COLUMN cancelled_at DATETIME NULL AFTER suppressed_at',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_delivery_jobs' AND COLUMN_NAME='dead_lettered_at') = 0,
  'ALTER TABLE notification_delivery_jobs ADD COLUMN dead_lettered_at DATETIME NULL AFTER cancelled_at',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_delivery_jobs' AND COLUMN_NAME='max_attempts') = 0,
  'ALTER TABLE notification_delivery_jobs ADD COLUMN max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 8 AFTER dead_lettered_at',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_delivery_jobs' AND COLUMN_NAME='source_type') = 0,
  'ALTER TABLE notification_delivery_jobs ADD COLUMN source_type VARCHAR(80) NULL AFTER max_attempts',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_delivery_jobs' AND COLUMN_NAME='source_public_id') = 0,
  'ALTER TABLE notification_delivery_jobs ADD COLUMN source_public_id VARCHAR(190) NULL AFTER source_type',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_delivery_jobs' AND COLUMN_NAME='metadata_json') = 0,
  'ALTER TABLE notification_delivery_jobs ADD COLUMN metadata_json JSON NULL AFTER source_public_id',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

ALTER TABLE notification_delivery_jobs
  MODIFY COLUMN status ENUM('queued','processing','retry_scheduled','provider_accepted','sent','delivered','failed','dead_letter','cancelled','suppressed') NOT NULL DEFAULT 'queued';

UPDATE notification_delivery_jobs
   SET job_key = SHA2(CONCAT('legacy:', notification_id, ':', channel, ':', COALESCE(destination_hash,'primary'), ':', id), 256)
 WHERE job_key IS NULL;

UPDATE notification_delivery_jobs j
JOIN notifications n ON n.id=j.notification_id
   SET j.merchant_user_id=NULLIF(CAST(JSON_UNQUOTE(JSON_EXTRACT(n.context_json,'$.merchant_user_id')) AS UNSIGNED),0)
 WHERE j.merchant_user_id IS NULL
   AND n.context_json IS NOT NULL
   AND JSON_EXTRACT(n.context_json,'$.merchant_user_id') IS NOT NULL;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_delivery_jobs' AND INDEX_NAME='uq_notification_delivery_jobs_job_key') = 0,
  'ALTER TABLE notification_delivery_jobs ADD UNIQUE KEY uq_notification_delivery_jobs_job_key (job_key)',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_delivery_jobs' AND INDEX_NAME='idx_notification_delivery_jobs_lease') = 0,
  'ALTER TABLE notification_delivery_jobs ADD KEY idx_notification_delivery_jobs_lease (status,lease_expires_at,next_attempt_at,priority,id)',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_delivery_jobs' AND INDEX_NAME='idx_notification_delivery_jobs_merchant_queue') = 0,
  'ALTER TABLE notification_delivery_jobs ADD KEY idx_notification_delivery_jobs_merchant_queue (merchant_user_id,status,next_attempt_at,id)',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='notification_delivery_jobs' AND CONSTRAINT_NAME='fk_notification_delivery_jobs_merchant') = 0,
  'ALTER TABLE notification_delivery_jobs ADD CONSTRAINT fk_notification_delivery_jobs_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

ALTER TABLE notification_preferences
  MODIFY COLUMN email_enabled TINYINT(1) NOT NULL DEFAULT 0,
  MODIFY COLUMN sms_enabled TINYINT(1) NOT NULL DEFAULT 0,
  MODIFY COLUMN push_enabled TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS mg_delivery_worker_state (
  id TINYINT UNSIGNED NOT NULL,
  paused TINYINT(1) NOT NULL DEFAULT 0,
  pause_reason VARCHAR(500) NULL,
  paused_at DATETIME NULL,
  paused_by_user_id BIGINT UNSIGNED NULL,
  cleared_at DATETIME NULL,
  cleared_by_user_id BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_mg_delivery_worker_state_paused_by FOREIGN KEY (paused_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_mg_delivery_worker_state_cleared_by FOREIGN KEY (cleared_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO mg_delivery_worker_state (id,paused) VALUES (1,0);

CREATE TABLE IF NOT EXISTS mg_delivery_worker_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  mode ENUM('observe','process') NOT NULL DEFAULT 'observe',
  status ENUM('running','completed','partial','paused','failed','skipped') NOT NULL DEFAULT 'running',
  worker_name VARCHAR(120) NOT NULL,
  batch_limit SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  selected_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  processed_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  delivered_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  accepted_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  retry_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  suppressed_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  dead_letter_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  failed_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  duration_ms INT UNSIGNED NULL,
  metadata_json JSON NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_delivery_worker_runs_public_id (public_id),
  KEY idx_mg_delivery_worker_runs_started (started_at,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_delivery_provider_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  delivery_job_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('attempted','accepted','delivered','opened','retry_scheduled','failed','dead_lettered','suppressed','cancelled','recovered') NOT NULL,
  provider VARCHAR(80) NULL,
  provider_reference VARCHAR(190) NULL,
  event_key CHAR(64) NOT NULL,
  response_code VARCHAR(100) NULL,
  message VARCHAR(500) NULL,
  metadata_json JSON NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_delivery_provider_events_public_id (public_id),
  UNIQUE KEY uq_mg_delivery_provider_events_event_key (event_key),
  KEY idx_mg_delivery_provider_events_job (delivery_job_id,occurred_at),
  CONSTRAINT fk_mg_delivery_provider_events_job FOREIGN KEY (delivery_job_id) REFERENCES notification_delivery_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('delivery.operations.view','View delivery operations','View delivery queue health, worker runs, channel status, and dead-letter records.',NOW()),
('delivery.operations.manage','Manage delivery operations','Retry, cancel, recover, and clear guarded delivery-worker pauses.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('delivery.operations.view','delivery.operations.manage')
WHERE r.slug IN ('admin','super_admin');
