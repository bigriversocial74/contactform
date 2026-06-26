CREATE TABLE IF NOT EXISTS subscription_package_change_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  current_package_id VARCHAR(80) NULL,
  requested_package_id VARCHAR(80) NOT NULL,
  request_type ENUM('upgrade','downgrade','enterprise','lateral') NOT NULL DEFAULT 'upgrade',
  status ENUM('pending_payment','pending_admin_review','approved','rejected','canceled','completed') NOT NULL DEFAULT 'pending_admin_review',
  checkout_url VARCHAR(600) NULL,
  amount_cents BIGINT UNSIGNED NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  billing_cycle VARCHAR(40) NOT NULL DEFAULT 'month',
  user_note TEXT NULL,
  admin_note TEXT NULL,
  metadata_json JSON NULL,
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subscription_package_change_public_id (public_id),
  KEY idx_subscription_package_change_user_status (user_id,status,updated_at,id),
  KEY idx_subscription_package_change_status_created (status,created_at,id),
  KEY idx_subscription_package_change_requested (requested_package_id,status,created_at),
  CONSTRAINT fk_subscription_package_change_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_subscription_package_change_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_13b_subscription_package_changes','Subscription package upgrade, downgrade, Enterprise, payment pending, and admin review requests.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
