-- Stage 4E Distribution Programs and External Input Sources

CREATE TABLE IF NOT EXISTS distribution_programs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  program_type ENUM('purchase','merchant_grant','contest','giveaway','fundraiser','workplace_reward','gaming','external_api','batch','other') NOT NULL,
  status ENUM('draft','scheduled','active','paused','completed','cancelled','archived') NOT NULL DEFAULT 'draft',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  budget_cents BIGINT UNSIGNED NULL,
  reserved_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  issued_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  max_items BIGINT UNSIGNED NULL,
  issued_items BIGINT UNSIGNED NOT NULL DEFAULT 0,
  per_recipient_limit INT UNSIGNED NULL,
  rules_json JSON NULL,
  metadata_json JSON NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_distribution_programs_public_id (public_id),
  KEY idx_distribution_programs_merchant_status (merchant_user_id,status,starts_at),
  CONSTRAINT fk_distribution_programs_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_distribution_programs_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS distribution_program_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  program_id BIGINT UNSIGNED NOT NULL,
  pppm_template_id BIGINT UNSIGNED NOT NULL,
  weight INT UNSIGNED NOT NULL DEFAULT 1,
  quantity_limit BIGINT UNSIGNED NULL,
  quantity_issued BIGINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('active','inactive','exhausted') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_distribution_program_product (program_id,pppm_template_id),
  CONSTRAINT fk_distribution_program_products_program FOREIGN KEY (program_id) REFERENCES distribution_programs(id) ON DELETE CASCADE,
  CONSTRAINT fk_distribution_program_products_template FOREIGN KEY (pppm_template_id) REFERENCES catalog_pppm_templates(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS distribution_source_connections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  program_id BIGINT UNSIGNED NULL,
  source_type ENUM('ecommerce','merchant','contest','giveaway','fundraiser','workplace','gaming','webhook','api','csv','other') NOT NULL,
  provider_key VARCHAR(120) NOT NULL,
  display_name VARCHAR(180) NOT NULL,
  status ENUM('active','paused','revoked') NOT NULL DEFAULT 'active',
  secret_hash CHAR(64) NULL,
  configuration_json JSON NULL,
  last_event_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_distribution_source_connections_public_id (public_id),
  UNIQUE KEY uq_distribution_source_provider (merchant_user_id,source_type,provider_key),
  CONSTRAINT fk_distribution_source_connections_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_distribution_source_connections_program FOREIGN KEY (program_id) REFERENCES distribution_programs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS distribution_source_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  connection_id BIGINT UNSIGNED NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  program_id BIGINT UNSIGNED NULL,
  source_type VARCHAR(40) NOT NULL,
  external_event_id VARCHAR(255) NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  idempotency_key CHAR(64) NOT NULL,
  payload_json JSON NOT NULL,
  payload_checksum CHAR(64) NOT NULL,
  status ENUM('received','validated','queued','processed','duplicate','rejected','failed') NOT NULL DEFAULT 'received',
  failure_code VARCHAR(80) NULL,
  failure_message VARCHAR(500) NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_distribution_source_events_public_id (public_id),
  UNIQUE KEY uq_distribution_source_events_idempotency (merchant_user_id,idempotency_key),
  UNIQUE KEY uq_distribution_source_external (merchant_user_id,source_type,external_event_id),
  KEY idx_distribution_source_events_queue (status,received_at),
  CONSTRAINT fk_distribution_source_events_connection FOREIGN KEY (connection_id) REFERENCES distribution_source_connections(id) ON DELETE SET NULL,
  CONSTRAINT fk_distribution_source_events_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_distribution_source_events_program FOREIGN KEY (program_id) REFERENCES distribution_programs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS distribution_recipients (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  program_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  external_recipient_id VARCHAR(255) NULL,
  email_hash CHAR(64) NULL,
  phone_hash CHAR(64) NULL,
  display_name VARCHAR(180) NULL,
  eligibility_status ENUM('pending','eligible','ineligible','selected','allocated','fulfilled','disqualified') NOT NULL DEFAULT 'pending',
  eligibility_reason VARCHAR(255) NULL,
  entries_count INT UNSIGNED NOT NULL DEFAULT 1,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_distribution_recipients_public_id (public_id),
  UNIQUE KEY uq_distribution_recipient_user (program_id,user_id),
  UNIQUE KEY uq_distribution_recipient_external (program_id,external_recipient_id),
  KEY idx_distribution_recipients_eligibility (program_id,eligibility_status,id),
  CONSTRAINT fk_distribution_recipients_program FOREIGN KEY (program_id) REFERENCES distribution_programs(id) ON DELETE CASCADE,
  CONSTRAINT fk_distribution_recipients_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS distribution_allocations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  program_id BIGINT UNSIGNED NOT NULL,
  source_event_id BIGINT UNSIGNED NULL,
  recipient_id BIGINT UNSIGNED NOT NULL,
  program_product_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  unit_value_cents INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('reserved','queued','issuing','issued','failed','cancelled','expired') NOT NULL DEFAULT 'reserved',
  allocation_method ENUM('purchase_line','direct','random','weighted_random','ranked','batch','api') NOT NULL,
  selection_proof_json JSON NULL,
  idempotency_key CHAR(64) NOT NULL,
  reserved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  issued_at DATETIME NULL,
  failure_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_distribution_allocations_public_id (public_id),
  UNIQUE KEY uq_distribution_allocations_idempotency (program_id,idempotency_key),
  KEY idx_distribution_allocations_queue (status,created_at),
  CONSTRAINT fk_distribution_allocations_program FOREIGN KEY (program_id) REFERENCES distribution_programs(id) ON DELETE RESTRICT,
  CONSTRAINT fk_distribution_allocations_event FOREIGN KEY (source_event_id) REFERENCES distribution_source_events(id) ON DELETE SET NULL,
  CONSTRAINT fk_distribution_allocations_recipient FOREIGN KEY (recipient_id) REFERENCES distribution_recipients(id) ON DELETE RESTRICT,
  CONSTRAINT fk_distribution_allocations_product FOREIGN KEY (program_product_id) REFERENCES distribution_program_products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS distribution_issuance_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  allocation_id BIGINT UNSIGNED NOT NULL,
  item_sequence INT UNSIGNED NOT NULL,
  status ENUM('queued','processing','issued','failed','dead_letter') NOT NULL DEFAULT 'queued',
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  next_attempt_at DATETIME NULL,
  locked_at DATETIME NULL,
  locked_by VARCHAR(120) NULL,
  pppm_item_id BIGINT UNSIGNED NULL,
  request_json JSON NOT NULL,
  failure_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_distribution_issuance_jobs_public_id (public_id),
  UNIQUE KEY uq_distribution_issuance_allocation_sequence (allocation_id,item_sequence),
  KEY idx_distribution_issuance_jobs_queue (status,next_attempt_at,created_at),
  CONSTRAINT fk_distribution_issuance_jobs_allocation FOREIGN KEY (allocation_id) REFERENCES distribution_allocations(id) ON DELETE RESTRICT,
  CONSTRAINT fk_distribution_issuance_jobs_item FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS distribution_webhook_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  connection_id BIGINT UNSIGNED NULL,
  source_event_id BIGINT UNSIGNED NULL,
  direction ENUM('inbound','outbound') NOT NULL,
  endpoint_hash CHAR(64) NULL,
  event_type VARCHAR(100) NOT NULL,
  status ENUM('received','accepted','rejected','queued','delivered','failed') NOT NULL,
  http_status SMALLINT UNSIGNED NULL,
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  request_checksum CHAR(64) NULL,
  response_checksum CHAR(64) NULL,
  failure_message VARCHAR(500) NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_distribution_webhook_attempts_public_id (public_id),
  KEY idx_distribution_webhook_attempts_event (source_event_id,occurred_at),
  CONSTRAINT fk_distribution_webhook_connection FOREIGN KEY (connection_id) REFERENCES distribution_source_connections(id) ON DELETE SET NULL,
  CONSTRAINT fk_distribution_webhook_event FOREIGN KEY (source_event_id) REFERENCES distribution_source_events(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS distribution_daily_metrics (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  metric_date DATE NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  program_id BIGINT UNSIGNED NOT NULL,
  source_type VARCHAR(40) NOT NULL,
  received_events BIGINT UNSIGNED NOT NULL DEFAULT 0,
  duplicate_events BIGINT UNSIGNED NOT NULL DEFAULT 0,
  rejected_events BIGINT UNSIGNED NOT NULL DEFAULT 0,
  allocations BIGINT UNSIGNED NOT NULL DEFAULT 0,
  items_queued BIGINT UNSIGNED NOT NULL DEFAULT 0,
  items_issued BIGINT UNSIGNED NOT NULL DEFAULT 0,
  issued_value_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  unique_recipients BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_distribution_daily_metrics (metric_date,program_id,source_type),
  CONSTRAINT fk_distribution_metrics_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_distribution_metrics_program FOREIGN KEY (program_id) REFERENCES distribution_programs(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('distribution.programs.manage','Manage distribution programs','Create and operate distribution programs.',NOW()),
('distribution.sources.manage','Manage distribution sources','Configure external and internal input sources.',NOW()),
('distribution.events.ingest','Ingest distribution events','Submit idempotent source events.',NOW()),
('distribution.allocations.manage','Manage allocations','Select recipients and allocate products.',NOW()),
('distribution.analytics.view','View distribution analytics','View program and source analytics.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('distribution.programs.manage','distribution.sources.manage','distribution.events.ingest','distribution.allocations.manage','distribution.analytics.view')
WHERE r.slug IN ('merchant','admin','super_admin');
