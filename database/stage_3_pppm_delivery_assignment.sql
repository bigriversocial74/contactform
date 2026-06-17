-- Stage 3 PPPM Delivery and Assignment Operations

CREATE TABLE IF NOT EXISTS pppm_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  pppm_item_id BIGINT UNSIGNED NOT NULL,
  assignment_type ENUM('direct','transfer','system','api') NOT NULL DEFAULT 'direct',
  from_user_id BIGINT UNSIGNED NULL,
  to_user_id BIGINT UNSIGNED NULL,
  to_external_id VARCHAR(190) NULL,
  to_name VARCHAR(160) NULL,
  status ENUM('pending','accepted','rejected','cancelled','replaced') NOT NULL DEFAULT 'pending',
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  accepted_by_user_id BIGINT UNSIGNED NULL,
  accepted_at DATETIME NULL,
  rejected_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pppm_assignments_public_id (public_id),
  KEY idx_pppm_assignments_item_status (pppm_item_id, status),
  KEY idx_pppm_assignments_to_user_status (to_user_id, status),
  CONSTRAINT fk_pppm_assignments_item FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_assignments_from_user FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_assignments_to_user FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_assignments_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_assignments_accepted_by FOREIGN KEY (accepted_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pppm_transfer_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  pppm_item_id BIGINT UNSIGNED NOT NULL,
  assignment_id BIGINT UNSIGNED NULL,
  from_user_id BIGINT UNSIGNED NOT NULL,
  to_user_id BIGINT UNSIGNED NULL,
  to_external_id VARCHAR(190) NULL,
  transfer_token_hash CHAR(64) NULL,
  status ENUM('pending','accepted','rejected','cancelled','expired') NOT NULL DEFAULT 'pending',
  expires_at DATETIME NULL,
  accepted_at DATETIME NULL,
  rejected_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pppm_transfer_requests_public_id (public_id),
  KEY idx_pppm_transfer_item_status (pppm_item_id, status),
  KEY idx_pppm_transfer_to_user_status (to_user_id, status),
  CONSTRAINT fk_pppm_transfer_item FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_transfer_assignment FOREIGN KEY (assignment_id) REFERENCES pppm_assignments(id) ON DELETE SET NULL,
  CONSTRAINT fk_pppm_transfer_from_user FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_transfer_to_user FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pppm_delivery_schedules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  pppm_item_id BIGINT UNSIGNED NOT NULL,
  assignment_id BIGINT UNSIGNED NULL,
  channel ENUM('email','sms','link','push','api','manual','other') NOT NULL,
  destination VARCHAR(255) NULL,
  scheduled_for DATETIME NOT NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
  status ENUM('scheduled','processing','completed','cancelled','failed') NOT NULL DEFAULT 'scheduled',
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  processed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pppm_delivery_schedules_public_id (public_id),
  KEY idx_pppm_delivery_schedule_due (status, scheduled_for),
  KEY idx_pppm_delivery_schedule_item (pppm_item_id, status),
  CONSTRAINT fk_pppm_delivery_schedule_item FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_delivery_schedule_assignment FOREIGN KEY (assignment_id) REFERENCES pppm_assignments(id) ON DELETE SET NULL,
  CONSTRAINT fk_pppm_delivery_schedule_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pppm_delivery_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  delivery_id BIGINT UNSIGNED NOT NULL,
  schedule_id BIGINT UNSIGNED NULL,
  attempt_number SMALLINT UNSIGNED NOT NULL,
  provider VARCHAR(100) NULL,
  provider_reference VARCHAR(190) NULL,
  status ENUM('queued','processing','sent','delivered','failed','retry_scheduled','cancelled') NOT NULL DEFAULT 'queued',
  failure_code VARCHAR(80) NULL,
  failure_message VARCHAR(500) NULL,
  attempted_at DATETIME NULL,
  next_retry_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pppm_delivery_attempts_public_id (public_id),
  UNIQUE KEY uq_pppm_delivery_attempt_number (delivery_id, attempt_number),
  KEY idx_pppm_delivery_attempt_retry (status, next_retry_at),
  CONSTRAINT fk_pppm_delivery_attempt_delivery FOREIGN KEY (delivery_id) REFERENCES pppm_deliveries(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_delivery_attempt_schedule FOREIGN KEY (schedule_id) REFERENCES pppm_delivery_schedules(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pppm_provider_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  provider VARCHAR(100) NOT NULL,
  external_event_id VARCHAR(190) NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  delivery_id BIGINT UNSIGNED NULL,
  delivery_attempt_id BIGINT UNSIGNED NULL,
  payload_json JSON NULL,
  payload_hash CHAR(64) NOT NULL,
  processing_status ENUM('received','processed','ignored','failed') NOT NULL DEFAULT 'received',
  failure_message VARCHAR(500) NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pppm_provider_events_public_id (public_id),
  UNIQUE KEY uq_pppm_provider_external_event (provider, external_event_id),
  KEY idx_pppm_provider_events_status_received (processing_status, received_at),
  CONSTRAINT fk_pppm_provider_event_delivery FOREIGN KEY (delivery_id) REFERENCES pppm_deliveries(id) ON DELETE SET NULL,
  CONSTRAINT fk_pppm_provider_event_attempt FOREIGN KEY (delivery_attempt_id) REFERENCES pppm_delivery_attempts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug, name, description, created_at) VALUES
('pppm.assign', 'Assign PPPM items', 'Assign PPPM items to recipients.', NOW()),
('pppm.transfer', 'Transfer PPPM items', 'Initiate and accept PPPM ownership transfers.', NOW()),
('pppm.delivery.schedule', 'Schedule PPPM delivery', 'Schedule PPPM delivery operations.', NOW()),
('pppm.delivery.dispatch', 'Dispatch PPPM delivery', 'Create and process PPPM delivery attempts.', NOW());

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW() FROM roles r JOIN permissions p
ON p.slug IN ('pppm.assign','pppm.transfer','pppm.delivery.schedule','pppm.delivery.dispatch')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW() FROM roles r JOIN permissions p
ON p.slug IN ('pppm.assign','pppm.transfer','pppm.delivery.schedule')
WHERE r.slug = 'customer';