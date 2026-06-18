-- Stage 3 PPPM Core Architecture Refactor

CREATE TABLE IF NOT EXISTS pppm_sources (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NULL,
  source_type VARCHAR(80) NOT NULL,
  provider VARCHAR(120) NOT NULL,
  name VARCHAR(160) NOT NULL,
  status ENUM('active','inactive','revoked') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pppm_sources_public_id (public_id),
  KEY idx_pppm_sources_owner_status (owner_user_id, status),
  CONSTRAINT fk_pppm_sources_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pppm_source_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  source_id BIGINT UNSIGNED NOT NULL,
  external_event_id VARCHAR(190) NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  payload_json JSON NULL,
  payload_hash CHAR(64) NOT NULL,
  processing_status ENUM('received','validated','processed','failed','ignored') NOT NULL DEFAULT 'received',
  failure_code VARCHAR(80) NULL,
  failure_message VARCHAR(500) NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pppm_source_events_public_id (public_id),
  UNIQUE KEY uq_pppm_source_external_event (source_id, external_event_id),
  KEY idx_pppm_source_events_status_received (processing_status, received_at),
  CONSTRAINT fk_pppm_source_events_source FOREIGN KEY (source_id) REFERENCES pppm_sources(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pppm_issuance_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  source_id BIGINT UNSIGNED NOT NULL,
  source_event_id BIGINT UNSIGNED NULL,
  issuer_user_id BIGINT UNSIGNED NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  source_reference VARCHAR(190) NULL,
  source_line_reference VARCHAR(190) NULL,
  item_type ENUM('gift','prize','reward','voucher','entitlement','reservation','credit','other') NOT NULL DEFAULT 'gift',
  funding_type ENUM('customer_purchase','merchant_funded','sponsor_funded','platform_funded','promotional','earned_reward','free','other') NOT NULL DEFAULT 'other',
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  unit_value_cents INT UNSIGNED NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  recipient_user_id BIGINT UNSIGNED NULL,
  recipient_external_id VARCHAR(190) NULL,
  recipient_name VARCHAR(160) NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NULL,
  terms_snapshot_json JSON NULL,
  metadata_json JSON NULL,
  status ENUM('pending','validated','issuing','issued','failed','cancelled') NOT NULL DEFAULT 'pending',
  issued_count INT UNSIGNED NOT NULL DEFAULT 0,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pppm_issuance_requests_public_id (public_id),
  KEY idx_pppm_issuance_source_status (source_id, status, requested_at),
  CONSTRAINT fk_pppm_issuance_source FOREIGN KEY (source_id) REFERENCES pppm_sources(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_issuance_event FOREIGN KEY (source_event_id) REFERENCES pppm_source_events(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_issuance_issuer FOREIGN KEY (issuer_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_issuance_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_issuance_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT chk_pppm_issuance_quantity CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pppm_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(32) NOT NULL,
  issuance_request_id BIGINT UNSIGNED NOT NULL,
  source_id BIGINT UNSIGNED NOT NULL,
  unit_sequence INT UNSIGNED NOT NULL,
  item_type ENUM('gift','prize','reward','voucher','entitlement','reservation','credit','other') NOT NULL,
  funding_type ENUM('customer_purchase','merchant_funded','sponsor_funded','platform_funded','promotional','earned_reward','free','other') NOT NULL,
  issuer_user_id BIGINT UNSIGNED NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  owner_user_id BIGINT UNSIGNED NULL,
  recipient_user_id BIGINT UNSIGNED NULL,
  recipient_external_id VARCHAR(190) NULL,
  source_reference VARCHAR(190) NULL,
  source_line_reference VARCHAR(190) NULL,
  title_snapshot VARCHAR(160) NOT NULL,
  description_snapshot TEXT NULL,
  value_cents_snapshot INT UNSIGNED NOT NULL DEFAULT 0,
  currency_snapshot CHAR(3) NOT NULL DEFAULT 'USD',
  terms_snapshot_json JSON NULL,
  metadata_snapshot_json JSON NULL,
  status ENUM('created','available','assigned','scheduled','sent','delivered','viewed','claim_pending','verified','redeemed','expired','cancelled','refunded','voided') NOT NULL DEFAULT 'created',
  version_no INT UNSIGNED NOT NULL DEFAULT 1,
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  assigned_at DATETIME NULL,
  sent_at DATETIME NULL,
  delivered_at DATETIME NULL,
  viewed_at DATETIME NULL,
  claimed_at DATETIME NULL,
  redeemed_at DATETIME NULL,
  expires_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pppm_items_public_id (public_id),
  UNIQUE KEY uq_pppm_items_request_unit (issuance_request_id, unit_sequence),
  KEY idx_pppm_items_owner_status (owner_user_id, status, updated_at),
  KEY idx_pppm_items_recipient_status (recipient_user_id, status, updated_at),
  KEY idx_pppm_items_merchant_status (merchant_user_id, status, updated_at),
  CONSTRAINT fk_pppm_items_request FOREIGN KEY (issuance_request_id) REFERENCES pppm_issuance_requests(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_items_source FOREIGN KEY (source_id) REFERENCES pppm_sources(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_items_issuer FOREIGN KEY (issuer_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_items_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_items_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_items_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pppm_item_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pppm_item_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(100) NOT NULL,
  from_status VARCHAR(40) NULL,
  to_status VARCHAR(40) NULL,
  source_event_id BIGINT UNSIGNED NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pppm_item_events_item_created (pppm_item_id, created_at, id),
  CONSTRAINT fk_pppm_item_events_item FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_item_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_pppm_item_events_source_event FOREIGN KEY (source_event_id) REFERENCES pppm_source_events(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pppm_item_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pppm_item_id BIGINT UNSIGNED NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  status_snapshot VARCHAR(40) NOT NULL,
  owner_user_id_snapshot BIGINT UNSIGNED NULL,
  recipient_user_id_snapshot BIGINT UNSIGNED NULL,
  merchant_user_id_snapshot BIGINT UNSIGNED NULL,
  title_snapshot VARCHAR(160) NOT NULL,
  value_cents_snapshot INT UNSIGNED NOT NULL,
  currency_snapshot CHAR(3) NOT NULL,
  terms_snapshot_json JSON NULL,
  metadata_snapshot_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pppm_item_snapshots_version (pppm_item_id, version_no),
  CONSTRAINT fk_pppm_item_snapshots_item FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pppm_legacy_gift_map (
  gift_id BIGINT UNSIGNED NOT NULL,
  pppm_item_id BIGINT UNSIGNED NOT NULL,
  mapped_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (gift_id),
  UNIQUE KEY uq_pppm_legacy_gift_item (pppm_item_id),
  CONSTRAINT fk_pppm_legacy_map_gift FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pppm_legacy_map_item FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pppm_demand_facts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pppm_item_id BIGINT UNSIGNED NOT NULL,
  fact_date DATE NOT NULL,
  source_type VARCHAR(80) NOT NULL,
  item_type VARCHAR(40) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  recipient_region VARCHAR(120) NULL,
  expected_value_cents INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(40) NOT NULL,
  expected_at DATETIME NULL,
  fulfilled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pppm_demand_fact_item_date (pppm_item_id, fact_date),
  KEY idx_pppm_demand_source_date (source_type, fact_date),
  KEY idx_pppm_demand_merchant_date (merchant_user_id, fact_date),
  CONSTRAINT fk_pppm_demand_fact_item FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_pppm_demand_fact_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug, name, description, created_at) VALUES
('pppm.sources.manage', 'Manage PPPM sources', 'Create and manage PPPM input sources.', NOW()),
('pppm.ingest', 'Ingest PPPM events', 'Submit idempotent source events and issuance requests.', NOW()),
('pppm.items.view', 'View PPPM items', 'View authorized PPPM items and event history.', NOW()),
('pppm.items.manage', 'Manage PPPM items', 'Manage assignment, delivery, and lifecycle transitions.', NOW());

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r JOIN permissions p ON p.slug IN ('pppm.sources.manage','pppm.ingest','pppm.items.view','pppm.items.manage')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r JOIN permissions p ON p.slug = 'pppm.items.view'
WHERE r.slug = 'customer';