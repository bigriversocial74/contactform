CREATE TABLE IF NOT EXISTS account_package_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  account_user_id BIGINT UNSIGNED NOT NULL,
  package_id VARCHAR(80) NOT NULL,
  status ENUM('active','paused','cancelled','archived') NOT NULL DEFAULT 'active',
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  renews_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_account_package_assignment_public_id (public_id),
  KEY idx_account_package_assignment_user_status (account_user_id,status),
  KEY idx_account_package_assignment_status_package (status,package_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_17c_stamp_package_assignments','Account package assignments for automatic monthly Stamp allowance renewals.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
