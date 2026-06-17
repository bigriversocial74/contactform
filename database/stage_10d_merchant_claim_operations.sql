-- Stage 10D — Merchant Claim APIs, Operational History, Rate Limits, and Escalation

CREATE TABLE IF NOT EXISTS microgift_claim_rate_limits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket_key CHAR(64) NOT NULL,
  scope ENUM('actor','merchant','location','network','gift') NOT NULL,
  subject_reference VARCHAR(190) NOT NULL,
  window_started_at DATETIME NOT NULL,
  window_seconds INT UNSIGNED NOT NULL,
  attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
  limit_count INT UNSIGNED NOT NULL,
  blocked_until DATETIME NULL,
  last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgift_claim_rate_bucket (bucket_key),
  KEY idx_microgift_claim_rate_blocked (blocked_until),
  KEY idx_microgift_claim_rate_scope_subject (scope,subject_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS microgift_claim_escalations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  location_id BIGINT UNSIGNED NULL,
  instance_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  review_item_id BIGINT UNSIGNED NULL,
  trigger_type ENUM('rate_limit','repeated_invalid_code','merchant_mismatch','location_mismatch','internal_error','manual') NOT NULL,
  severity ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  status ENUM('open','in_review','resolved','dismissed') NOT NULL DEFAULT 'open',
  attempt_count INT UNSIGNED NOT NULL DEFAULT 1,
  summary VARCHAR(255) NOT NULL,
  details_json JSON NULL,
  first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgift_claim_escalation_public_id (public_id),
  KEY idx_microgift_claim_escalation_status (status,severity,last_seen_at),
  KEY idx_microgift_claim_escalation_merchant (merchant_user_id,status,last_seen_at),
  CONSTRAINT fk_microgift_claim_escalation_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_claim_escalation_location FOREIGN KEY (location_id) REFERENCES merchant_locations(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_claim_escalation_instance FOREIGN KEY (instance_id) REFERENCES microgift_instances(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_claim_escalation_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_claim_escalation_review FOREIGN KEY (review_item_id) REFERENCES microgift_review_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS microgift_operational_outbox (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  topic VARCHAR(120) NOT NULL,
  aggregate_type VARCHAR(80) NOT NULL,
  aggregate_public_id VARCHAR(190) NOT NULL,
  payload_json JSON NOT NULL,
  status ENUM('pending','processing','delivered','failed','dead') NOT NULL DEFAULT 'pending',
  attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
  available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  locked_at DATETIME NULL,
  delivered_at DATETIME NULL,
  last_error VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgift_operational_outbox_public_id (public_id),
  KEY idx_microgift_operational_outbox_dispatch (status,available_at,id),
  KEY idx_microgift_operational_outbox_aggregate (aggregate_type,aggregate_public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.location_claim.execute','Execute merchant location claims','Submit authorized merchant-location Microgift claim and redemption operations.',NOW()),
('merchant.location_claim.history','View merchant claim history','View merchant-location claim attempts, outcomes, and redemption history.',NOW()),
('microgift.claim_escalations.manage','Manage claim escalations','Review and resolve merchant-location claim security escalations.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('merchant.location_claim.execute','merchant.location_claim.history')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug='microgift.claim_escalations.manage'
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_10d_merchant_claim_operations','Merchant claim APIs, operational history, rate-limit buckets, escalation workflow, and retryable outbox.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
