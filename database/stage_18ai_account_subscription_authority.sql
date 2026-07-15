-- Account, Subscription, and Entitlement Authority Repair v1
-- Adds audited complimentary package grants and a dedicated admin permission.

CREATE TABLE IF NOT EXISTS platform_complimentary_subscription_grants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  package_id VARCHAR(80) NOT NULL,
  status ENUM('active','revoked','replaced','expired') NOT NULL DEFAULT 'active',
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  reason VARCHAR(240) NOT NULL,
  granted_by_user_id BIGINT UNSIGNED NOT NULL,
  revoked_by_user_id BIGINT UNSIGNED NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_platform_complimentary_subscription_grants_public (public_id),
  KEY idx_platform_complimentary_subscription_grants_user_status (user_id,status),
  KEY idx_platform_complimentary_subscription_grants_package (package_id),
  KEY idx_platform_complimentary_subscription_grants_end (ends_at),
  CONSTRAINT fk_platform_complimentary_subscription_grants_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_platform_complimentary_subscription_grants_granted_by FOREIGN KEY (granted_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_platform_complimentary_subscription_grants_revoked_by FOREIGN KEY (revoked_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,created_at)
VALUES ('admin.subscriptions.manage','Manage complimentary subscriptions',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
INNER JOIN permissions p ON p.slug='admin.subscriptions.manage'
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_18ai_account_subscription_authority','Canonical account subscription authority and audited complimentary package grants.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
