-- Stage 25 PWA Notifications + Microgifter Notification Integration
-- Extends the existing notifications layer with browser push subscriptions and
-- per-subscription PWA delivery tracking. Uses CREATE TABLE IF NOT EXISTS and
-- avoids ADD COLUMN IF NOT EXISTS for older MySQL compatibility.

CREATE TABLE IF NOT EXISTS pwa_push_subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  endpoint_hash CHAR(64) NOT NULL,
  endpoint_url TEXT NOT NULL,
  subscription_json TEXT NOT NULL,
  user_agent_hash CHAR(64) NULL,
  status ENUM('active','revoked','expired','failed') NOT NULL DEFAULT 'active',
  subscribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NULL,
  last_success_at DATETIME NULL,
  failed_at DATETIME NULL,
  revoked_at DATETIME NULL,
  last_error_code VARCHAR(100) NULL,
  last_error_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pwa_push_subscriptions_public_id (public_id),
  UNIQUE KEY uq_pwa_push_subscriptions_user_endpoint (user_id,endpoint_hash),
  KEY idx_pwa_push_subscriptions_user_status (user_id,status,last_seen_at),
  KEY idx_pwa_push_subscriptions_status (status,updated_at),
  CONSTRAINT fk_pwa_push_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pwa_notification_deliveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  notification_id BIGINT UNSIGNED NOT NULL,
  delivery_job_id BIGINT UNSIGNED NULL,
  subscription_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  endpoint_hash CHAR(64) NOT NULL,
  payload_json TEXT NOT NULL,
  status ENUM('queued','sent','delivered','opened','failed','suppressed','cancelled') NOT NULL DEFAULT 'queued',
  attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_attempt_at DATETIME NULL,
  sent_at DATETIME NULL,
  delivered_at DATETIME NULL,
  opened_at DATETIME NULL,
  failed_at DATETIME NULL,
  failure_code VARCHAR(100) NULL,
  failure_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pwa_notification_deliveries_public_id (public_id),
  UNIQUE KEY uq_pwa_notification_deliveries_notification_subscription (notification_id,subscription_id),
  KEY idx_pwa_notification_deliveries_queue (status,last_attempt_at,id),
  KEY idx_pwa_notification_deliveries_user (user_id,status,created_at),
  KEY idx_pwa_notification_deliveries_job (delivery_job_id),
  CONSTRAINT fk_pwa_notification_deliveries_notification FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
  CONSTRAINT fk_pwa_notification_deliveries_job FOREIGN KEY (delivery_job_id) REFERENCES notification_delivery_jobs(id) ON DELETE SET NULL,
  CONSTRAINT fk_pwa_notification_deliveries_subscription FOREIGN KEY (subscription_id) REFERENCES pwa_push_subscriptions(id) ON DELETE CASCADE,
  CONSTRAINT fk_pwa_notification_deliveries_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('pwa.notifications.manage','Manage PWA notifications','Register and revoke browser push subscriptions for the authenticated account.',NOW()),
('admin.pwa_notifications.test','Test PWA notifications','Send a safe PWA push test notification to the current admin account.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug='pwa.notifications.manage'
WHERE r.slug IN ('customer','member','merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug='admin.pwa_notifications.test'
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_25_pwa_notifications',
  'Adds authenticated PWA push subscriptions and browser delivery tracking bridged to existing Microgifter notifications.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);
