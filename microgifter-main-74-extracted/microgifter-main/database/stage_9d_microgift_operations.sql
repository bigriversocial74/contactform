CREATE TABLE IF NOT EXISTS microgift_review_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  review_type VARCHAR(80) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'open',
  priority VARCHAR(20) NOT NULL DEFAULT 'normal',
  instance_id BIGINT UNSIGNED NULL,
  legacy_gift_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  source_type VARCHAR(80) NOT NULL,
  source_reference VARCHAR(190) NOT NULL,
  summary VARCHAR(240) NOT NULL,
  details_json JSON NULL,
  assigned_user_id BIGINT UNSIGNED NULL,
  resolved_by_user_id BIGINT UNSIGNED NULL,
  resolution_note VARCHAR(500) NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgift_review_public_id (public_id),
  UNIQUE KEY uq_microgift_review_source (review_type,source_type,source_reference),
  KEY idx_microgift_review_queue (status,priority,created_at),
  CONSTRAINT fk_microgift_review_instance FOREIGN KEY (instance_id) REFERENCES microgift_instances(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_review_legacy FOREIGN KEY (legacy_gift_id) REFERENCES gifts(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_review_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_review_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_review_assigned FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_review_resolved FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS microgift_daily_metrics (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  metric_date DATE NOT NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  template_id BIGINT UNSIGNED NULL,
  issued_count INT UNSIGNED NOT NULL DEFAULT 0,
  claimed_count INT UNSIGNED NOT NULL DEFAULT 0,
  redeemed_count INT UNSIGNED NOT NULL DEFAULT 0,
  expired_count INT UNSIGNED NOT NULL DEFAULT 0,
  cancelled_count INT UNSIGNED NOT NULL DEFAULT 0,
  revoked_count INT UNSIGNED NOT NULL DEFAULT 0,
  face_value_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  redeemed_value_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  unique_recipients INT UNSIGNED NOT NULL DEFAULT 0,
  unique_locations INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgift_daily_metric (metric_date,merchant_user_id,template_id),
  KEY idx_microgift_daily_merchant_date (merchant_user_id,metric_date),
  CONSTRAINT fk_microgift_daily_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_microgift_daily_template FOREIGN KEY (template_id) REFERENCES microgift_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('microgift.operations.view','View Microgift operations','View merchant Microgift lifecycle operations.',NOW()),
('microgift.reviews.manage','Manage Microgift reviews','Inspect and resolve Microgift review items.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='microgift.operations.view'
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='microgift.reviews.manage'
WHERE r.slug IN ('admin','super_admin');
