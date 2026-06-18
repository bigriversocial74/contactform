CREATE TABLE IF NOT EXISTS purchase_signal_records (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NULL,
  product_id BIGINT UNSIGNED NULL,
  asset_type ENUM('merchant','location','product','category','service','event','other') NOT NULL DEFAULT 'merchant',
  asset_reference VARCHAR(190) NULL,
  signal_type ENUM('future_visit','purchase_intent','committed_demand','gift_interest','repeat_visit','reservation_interest') NOT NULL,
  status ENUM('outstanding','redeemed','expired','canceled') NOT NULL DEFAULT 'outstanding',
  quantity DECIMAL(12,4) NOT NULL DEFAULT 1,
  estimated_value_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  confidence_score DECIMAL(7,6) NOT NULL DEFAULT 0.500000,
  expected_from DATETIME NOT NULL,
  expected_to DATETIME NULL,
  source_type VARCHAR(80) NOT NULL DEFAULT 'manual',
  source_reference VARCHAR(190) NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  redeemed_microgift_instance_id BIGINT UNSIGNED NULL,
  redeemed_redemption_id BIGINT UNSIGNED NULL,
  redeemed_at DATETIME NULL,
  canceled_at DATETIME NULL,
  expires_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_psr_public_id (public_id),
  UNIQUE KEY uq_psr_user_idempotency (user_id,idempotency_key),
  KEY idx_psr_merchant_status_window (merchant_user_id,status,expected_from,expected_to),
  KEY idx_psr_location_status_window (location_id,status,expected_from),
  KEY idx_psr_product_status_window (product_id,status,expected_from),
  KEY idx_psr_user_status (user_id,status,updated_at),
  CONSTRAINT fk_psr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_psr_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_psr_location FOREIGN KEY (location_id) REFERENCES merchant_locations(id) ON DELETE SET NULL,
  CONSTRAINT fk_psr_product FOREIGN KEY (product_id) REFERENCES catalog_products(id) ON DELETE SET NULL,
  CONSTRAINT fk_psr_microgift FOREIGN KEY (redeemed_microgift_instance_id) REFERENCES microgift_instances(id) ON DELETE SET NULL,
  CONSTRAINT fk_psr_redemption FOREIGN KEY (redeemed_redemption_id) REFERENCES microgift_redemptions(id) ON DELETE SET NULL,
  CONSTRAINT chk_psr_quantity_positive CHECK (quantity > 0),
  CONSTRAINT chk_psr_confidence_range CHECK (confidence_score >= 0 AND confidence_score <= 1),
  CONSTRAINT chk_psr_expected_window CHECK (expected_to IS NULL OR expected_to >= expected_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_signal_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  purchase_signal_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('created','updated','redeemed','expired','canceled','reopened') NOT NULL,
  from_status VARCHAR(40) NULL,
  to_status VARCHAR(40) NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_psr_events_public_id (public_id),
  KEY idx_psr_events_signal (purchase_signal_id,created_at,id),
  CONSTRAINT fk_psr_events_signal FOREIGN KEY (purchase_signal_id) REFERENCES purchase_signal_records(id) ON DELETE CASCADE,
  CONSTRAINT fk_psr_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demand_scope_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  snapshot_date DATE NOT NULL,
  horizon_days SMALLINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NULL,
  product_id BIGINT UNSIGNED NULL,
  scope_key VARCHAR(100) NOT NULL,
  outstanding_signal_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  outstanding_quantity DECIMAL(18,4) NOT NULL DEFAULT 0,
  outstanding_value_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  committed_signal_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  committed_value_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  future_visit_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  redeemed_signal_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  redeemed_value_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  unique_users BIGINT UNSIGNED NOT NULL DEFAULT 0,
  weighted_demand_score DECIMAL(18,6) NOT NULL DEFAULT 0,
  velocity_7d DECIMAL(18,6) NULL,
  velocity_30d DECIMAL(18,6) NULL,
  conversion_rate DECIMAL(12,8) NULL,
  feature_version VARCHAR(40) NOT NULL DEFAULT 'psr_v1',
  features_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_demand_scope_snapshot (snapshot_date,horizon_days,merchant_user_id,scope_key,feature_version),
  KEY idx_demand_scope_merchant_date (merchant_user_id,snapshot_date,horizon_days),
  KEY idx_demand_scope_location_date (location_id,snapshot_date,horizon_days),
  KEY idx_demand_scope_product_date (product_id,snapshot_date,horizon_days),
  CONSTRAINT fk_demand_scope_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_demand_scope_location FOREIGN KEY (location_id) REFERENCES merchant_locations(id) ON DELETE SET NULL,
  CONSTRAINT fk_demand_scope_product FOREIGN KEY (product_id) REFERENCES catalog_products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demand_agent_signals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NULL,
  product_id BIGINT UNSIGNED NULL,
  signal_key VARCHAR(100) NOT NULL,
  signal_level ENUM('info','opportunity','warning','critical') NOT NULL DEFAULT 'info',
  status ENUM('open','acknowledged','resolved','expired') NOT NULL DEFAULT 'open',
  observed_value DECIMAL(18,6) NULL,
  baseline_value DECIMAL(18,6) NULL,
  confidence_score DECIMAL(7,6) NOT NULL DEFAULT 0.500000,
  summary VARCHAR(500) NOT NULL,
  recommendation_json JSON NOT NULL,
  source_snapshot_id BIGINT UNSIGNED NULL,
  dedupe_key VARCHAR(190) NOT NULL,
  triggered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  acknowledged_at DATETIME NULL,
  resolved_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_demand_agent_public_id (public_id),
  UNIQUE KEY uq_demand_agent_dedupe (merchant_user_id,dedupe_key),
  KEY idx_demand_agent_open (merchant_user_id,status,signal_level,triggered_at),
  CONSTRAINT fk_demand_agent_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_demand_agent_location FOREIGN KEY (location_id) REFERENCES merchant_locations(id) ON DELETE SET NULL,
  CONSTRAINT fk_demand_agent_product FOREIGN KEY (product_id) REFERENCES catalog_products(id) ON DELETE SET NULL,
  CONSTRAINT fk_demand_agent_snapshot FOREIGN KEY (source_snapshot_id) REFERENCES demand_scope_snapshots(id) ON DELETE SET NULL,
  CONSTRAINT chk_demand_agent_confidence CHECK (confidence_score >= 0 AND confidence_score <= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('demand.psr.create','Create purchase signals','Create future visit and committed demand records.',NOW()),
('demand.psr.manage_own','Manage own purchase signals','View and cancel owned purchase signal records.',NOW()),
('demand.dashboard.view','View demand dashboard','View merchant, location, product, and asset demand intelligence.',NOW()),
('demand.snapshots.manage','Manage demand snapshots','Build and refresh predictive demand snapshots.',NOW()),
('demand.signals.manage','Manage demand signals','Acknowledge and resolve agent-ready demand signals.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN ('demand.psr.create','demand.psr.manage_own')
WHERE r.slug IN ('customer','member','merchant','creator','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN ('demand.dashboard.view','demand.snapshots.manage','demand.signals.manage')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_15_psr_demand_intelligence','Purchase Signal Records, future visits, committed demand, scope snapshots, velocity, and agent-ready demand signals.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
