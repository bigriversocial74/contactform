-- Stage 12 message delivery and campaign email opt-out support

CREATE TABLE IF NOT EXISTS message_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  event_key VARCHAR(190) NOT NULL,
  event_fingerprint CHAR(64) NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  category VARCHAR(60) NOT NULL DEFAULT 'transactional',
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_message_events_public_id (public_id),
  UNIQUE KEY uq_message_events_key (event_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_delivery_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  message_event_id BIGINT UNSIGNED NOT NULL,
  recipient_user_id BIGINT UNSIGNED NULL,
  channel ENUM('in_app','email','sms','webhook') NOT NULL,
  template_key VARCHAR(120) NOT NULL,
  status ENUM('queued','processing','retrying','delivered','failed','dead_letter','suppressed') NOT NULL DEFAULT 'queued',
  attempt_count INT NOT NULL DEFAULT 0,
  max_attempts INT NOT NULL DEFAULT 3,
  next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  provider_message_id VARCHAR(190) NULL,
  last_error VARCHAR(500) NULL,
  recipient_snapshot_json JSON NULL,
  payload_snapshot_json JSON NULL,
  delivered_at DATETIME NULL,
  failed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_message_delivery_jobs_public_id (public_id),
  UNIQUE KEY uq_message_delivery_jobs_event_channel (message_event_id,channel,recipient_user_id),
  KEY idx_message_delivery_jobs_claim (status,next_attempt_at,id),
  CONSTRAINT fk_message_delivery_jobs_event FOREIGN KEY (message_event_id) REFERENCES message_events(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_delivery_jobs_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_email_suppressions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NULL,
  email_hash CHAR(64) NOT NULL,
  scope ENUM('campaign','merchant') NOT NULL DEFAULT 'campaign',
  reason VARCHAR(120) NOT NULL DEFAULT 'unsubscribe',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campaign_email_suppressions_public_id (public_id),
  UNIQUE KEY uq_campaign_email_suppressions_scope (merchant_user_id,campaign_id,email_hash,scope),
  KEY idx_campaign_email_suppressions_lookup (merchant_user_id,email_hash,status),
  CONSTRAINT fk_campaign_email_suppressions_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_campaign_email_suppressions_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_12_message_delivery_campaign_suppression','Message delivery jobs and campaign email opt-out records.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
