-- Stage 10E — Outbox Workers, Merchant Dashboards, Configurable Rate Policies, and Retention Jobs

CREATE TABLE IF NOT EXISTS microgift_claim_rate_policies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  location_id BIGINT UNSIGNED NULL,
  scope ENUM('actor','merchant','location','network','gift') NOT NULL,
  limit_count INT UNSIGNED NOT NULL,
  window_seconds INT UNSIGNED NOT NULL,
  block_seconds INT UNSIGNED NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  priority INT NOT NULL DEFAULT 100,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgift_claim_rate_policy_public_id (public_id),
  KEY idx_microgift_claim_rate_policy_resolution (scope,status,merchant_user_id,location_id,priority),
  CONSTRAINT fk_microgift_claim_rate_policy_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_microgift_claim_rate_policy_location FOREIGN KEY (location_id) REFERENCES merchant_locations(id) ON DELETE CASCADE,
  CONSTRAINT fk_microgift_claim_rate_policy_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS microgift_retention_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  job_name VARCHAR(120) NOT NULL,
  status ENUM('running','completed','failed') NOT NULL DEFAULT 'running',
  cutoff_at DATETIME NOT NULL,
  affected_rows INT UNSIGNED NOT NULL DEFAULT 0,
  details_json JSON NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgift_retention_run_public_id (public_id),
  KEY idx_microgift_retention_run_job (job_name,started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.claim_dashboard.view','View merchant claim dashboard','View merchant claim activity, success rates, locations, and escalation summaries.',NOW()),
('microgift.rate_policies.manage','Manage claim rate policies','Create and update configurable claim rate-limit policies.',NOW()),
('microgift.outbox.manage','Manage operational outbox','Inspect and retry operational outbox messages.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='merchant.claim_dashboard.view'
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN ('microgift.rate_policies.manage','microgift.outbox.manage')
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_10e_outbox_dashboard_policies_retention','Outbox workers, merchant dashboard contracts, configurable claim rate policies, and retention jobs.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
