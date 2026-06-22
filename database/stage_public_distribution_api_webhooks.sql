-- Stage API-5 Developer Webhooks and Lifecycle Callbacks

CREATE TABLE IF NOT EXISTS developer_webhook_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  app_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  source_event_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(100) NOT NULL,
  aggregate_type VARCHAR(80) NULL,
  aggregate_public_id VARCHAR(80) NULL,
  payload_json JSON NOT NULL,
  status ENUM('queued','processing','delivered','failed','dead_letter','skipped') NOT NULL DEFAULT 'queued',
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 8,
  next_attempt_at DATETIME NULL,
  last_attempt_at DATETIME NULL,
  delivered_at DATETIME NULL,
  failure_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_developer_webhook_events_public_id (public_id),
  KEY idx_developer_webhook_events_queue (status,next_attempt_at,created_at),
  KEY idx_developer_webhook_events_app (app_id,status,created_at),
  KEY idx_developer_webhook_events_aggregate (aggregate_type,aggregate_public_id),
  CONSTRAINT fk_developer_webhook_events_app FOREIGN KEY (app_id) REFERENCES merchant_developer_apps(id) ON DELETE CASCADE,
  CONSTRAINT fk_developer_webhook_events_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_developer_webhook_events_source_event FOREIGN KEY (source_event_id) REFERENCES distribution_source_events(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS developer_webhook_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  webhook_event_id BIGINT UNSIGNED NOT NULL,
  app_id BIGINT UNSIGNED NOT NULL,
  endpoint_hash CHAR(64) NOT NULL,
  attempt_number SMALLINT UNSIGNED NOT NULL,
  status ENUM('processing','delivered','failed') NOT NULL DEFAULT 'processing',
  http_status SMALLINT UNSIGNED NULL,
  request_checksum CHAR(64) NOT NULL,
  response_checksum CHAR(64) NULL,
  failure_message VARCHAR(500) NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_developer_webhook_attempts_public_id (public_id),
  UNIQUE KEY uq_developer_webhook_attempt_number (webhook_event_id,attempt_number),
  KEY idx_developer_webhook_attempts_app (app_id,created_at),
  CONSTRAINT fk_developer_webhook_attempts_event FOREIGN KEY (webhook_event_id) REFERENCES developer_webhook_events(id) ON DELETE CASCADE,
  CONSTRAINT fk_developer_webhook_attempts_app FOREIGN KEY (app_id) REFERENCES merchant_developer_apps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.developer_webhooks.test','Test developer webhooks','Queue test webhook events for merchant developer apps.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('merchant.developer_webhooks.test')
WHERE r.slug IN ('merchant','admin','super_admin');
