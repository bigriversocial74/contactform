CREATE TABLE IF NOT EXISTS notification_preferences (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  notification_type VARCHAR(80) NOT NULL,
  in_app_enabled TINYINT(1) NOT NULL DEFAULT 1,
  email_enabled TINYINT(1) NOT NULL DEFAULT 1,
  sms_enabled TINYINT(1) NOT NULL DEFAULT 0,
  push_enabled TINYINT(1) NOT NULL DEFAULT 1,
  digest_mode ENUM('immediate','hourly','daily','weekly','off') NOT NULL DEFAULT 'immediate',
  quiet_hours_start TIME NULL,
  quiet_hours_end TIME NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notification_preferences_user_type (user_id,notification_type),
  CONSTRAINT fk_notification_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_delivery_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  notification_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  channel ENUM('in_app','email','sms','push','webhook') NOT NULL,
  destination_hash CHAR(64) NULL,
  status ENUM('queued','processing','sent','delivered','failed','cancelled','suppressed') NOT NULL DEFAULT 'queued',
  provider VARCHAR(80) NULL,
  provider_reference VARCHAR(190) NULL,
  attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at DATETIME NULL,
  sent_at DATETIME NULL,
  delivered_at DATETIME NULL,
  failed_at DATETIME NULL,
  failure_code VARCHAR(100) NULL,
  failure_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notification_delivery_jobs_public_id (public_id),
  KEY idx_notification_delivery_jobs_queue (status,next_attempt_at,id),
  KEY idx_notification_delivery_jobs_notification (notification_id,channel,status),
  CONSTRAINT fk_notification_delivery_jobs_notification FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
  CONSTRAINT fk_notification_delivery_jobs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operational_alerts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  alert_type VARCHAR(80) NOT NULL,
  severity ENUM('info','warning','high','critical') NOT NULL DEFAULT 'info',
  status ENUM('open','acknowledged','resolved','dismissed') NOT NULL DEFAULT 'open',
  title VARCHAR(180) NOT NULL,
  body VARCHAR(1000) NULL,
  action_url VARCHAR(500) NULL,
  gift_id BIGINT UNSIGNED NULL,
  pppm_item_id BIGINT UNSIGNED NULL,
  distribution_program_id BIGINT UNSIGNED NULL,
  claim_id BIGINT UNSIGNED NULL,
  metadata_json JSON NULL,
  acknowledged_by_user_id BIGINT UNSIGNED NULL,
  acknowledged_at DATETIME NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_operational_alerts_public_id (public_id),
  KEY idx_operational_alerts_user (user_id,status,severity,created_at),
  KEY idx_operational_alerts_merchant (merchant_user_id,status,severity,created_at),
  CONSTRAINT fk_operational_alerts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_operational_alerts_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_operational_alerts_gift FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE SET NULL,
  CONSTRAINT fk_operational_alerts_pppm FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_operational_alerts_program FOREIGN KEY (distribution_program_id) REFERENCES distribution_programs(id) ON DELETE SET NULL,
  CONSTRAINT fk_operational_alerts_claim FOREIGN KEY (claim_id) REFERENCES gift_claims(id) ON DELETE SET NULL,
  CONSTRAINT fk_operational_alerts_ack_user FOREIGN KEY (acknowledged_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_thread_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  thread_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  muted_until DATETIME NULL,
  archived_at DATETIME NULL,
  pinned_at DATETIME NULL,
  notifications_enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_message_thread_settings_thread_user (thread_id,user_id),
  CONSTRAINT fk_message_thread_settings_thread FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_thread_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('notification.preferences.manage','Manage notification preferences','Manage channel, digest, quiet-hour, and notification-type preferences.',NOW()),
('operational.alerts.view','View operational alerts','View user and merchant operational alerts.',NOW()),
('operational.alerts.manage','Manage operational alerts','Acknowledge, resolve, and dismiss operational alerts.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('notification.preferences.manage','operational.alerts.view','operational.alerts.manage')
WHERE r.slug IN ('member','merchant','admin','super_admin');
