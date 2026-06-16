-- 02A Microgifter user models and identity profiles schema
-- Purpose: add enableable user models on top of one login identity.
-- HostGator/MySQL compatible. Review before import.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS user_models (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  is_assignable TINYINT(1) NOT NULL DEFAULT 1,
  requires_approval TINYINT(1) NOT NULL DEFAULT 0,
  default_status ENUM('pending','active','disabled','suspended','revoked','rejected') NOT NULL DEFAULT 'pending',
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_models_public_id (public_id),
  UNIQUE KEY uq_user_models_code (code),
  KEY idx_user_models_assignable (is_assignable, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_model_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  user_model_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','active','disabled','suspended','revoked','rejected') NOT NULL DEFAULT 'pending',
  requested_at DATETIME NULL,
  enabled_at DATETIME NULL,
  disabled_at DATETIME NULL,
  approved_at DATETIME NULL,
  rejected_at DATETIME NULL,
  suspended_at DATETIME NULL,
  revoked_at DATETIME NULL,
  approved_by_user_id BIGINT UNSIGNED NULL,
  disabled_by_user_id BIGINT UNSIGNED NULL,
  reason VARCHAR(255) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_model_assignments_public_id (public_id),
  UNIQUE KEY uq_user_model_assignments_user_model (user_id, user_model_id),
  KEY idx_user_model_assignments_status (user_model_id, status),
  KEY idx_user_model_assignments_user_status (user_id, status),
  CONSTRAINT fk_user_model_assignments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_model_assignments_model FOREIGN KEY (user_model_id) REFERENCES user_models(id) ON DELETE RESTRICT,
  CONSTRAINT fk_user_model_assignments_approved_by FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_model_assignments_disabled_by FOREIGN KEY (disabled_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_model_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  user_model_id BIGINT UNSIGNED NOT NULL,
  assignment_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(100) NOT NULL,
  from_status VARCHAR(40) NULL,
  to_status VARCHAR(40) NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  reason VARCHAR(255) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_model_events_public_id (public_id),
  KEY idx_user_model_events_user_created (user_id, created_at),
  KEY idx_user_model_events_model_created (user_model_id, created_at),
  KEY idx_user_model_events_type_created (event_type, created_at),
  CONSTRAINT fk_user_model_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_model_events_model FOREIGN KEY (user_model_id) REFERENCES user_models(id) ON DELETE RESTRICT,
  CONSTRAINT fk_user_model_events_assignment FOREIGN KEY (assignment_id) REFERENCES user_model_assignments(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_model_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS model_default_roles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_model_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_model_default_roles_model_role (user_model_id, role_id),
  CONSTRAINT fk_model_default_roles_model FOREIGN KEY (user_model_id) REFERENCES user_models(id) ON DELETE CASCADE,
  CONSTRAINT fk_model_default_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  display_name VARCHAR(160) NULL,
  slug VARCHAR(160) NULL,
  bio TEXT NULL,
  status ENUM('draft','pending','active','disabled','suspended') NOT NULL DEFAULT 'draft',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_profiles_public_id (public_id),
  UNIQUE KEY uq_creator_profiles_user (user_id),
  UNIQUE KEY uq_creator_profiles_slug (slug),
  KEY idx_creator_profiles_status (status),
  CONSTRAINT fk_creator_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  business_name VARCHAR(180) NULL,
  display_name VARCHAR(180) NULL,
  onboarding_status ENUM('draft','pending_review','approved','disabled','suspended','rejected') NOT NULL DEFAULT 'draft',
  verification_status ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'unverified',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_profiles_public_id (public_id),
  UNIQUE KEY uq_merchant_profiles_user (user_id),
  KEY idx_merchant_profiles_onboarding (onboarding_status),
  KEY idx_merchant_profiles_verification (verification_status),
  CONSTRAINT fk_merchant_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS moderator_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','active','disabled','suspended') NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_moderator_profiles_public_id (public_id),
  UNIQUE KEY uq_moderator_profiles_user (user_id),
  KEY idx_moderator_profiles_status (status),
  CONSTRAINT fk_moderator_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vendor_manager_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','active','disabled','suspended') NOT NULL DEFAULT 'pending',
  territory VARCHAR(180) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vendor_manager_profiles_public_id (public_id),
  UNIQUE KEY uq_vendor_manager_profiles_user (user_id),
  KEY idx_vendor_manager_profiles_status (status),
  CONSTRAINT fk_vendor_manager_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_affiliate_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  affiliate_code VARCHAR(80) NULL,
  status ENUM('pending','active','disabled','suspended','rejected') NOT NULL DEFAULT 'pending',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_marketing_affiliate_profiles_public_id (public_id),
  UNIQUE KEY uq_marketing_affiliate_profiles_user (user_id),
  UNIQUE KEY uq_marketing_affiliate_profiles_code (affiliate_code),
  KEY idx_marketing_affiliate_profiles_status (status),
  CONSTRAINT fk_marketing_affiliate_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trader_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','active','disabled','suspended','rejected') NOT NULL DEFAULT 'pending',
  risk_status ENUM('unreviewed','pending_review','approved','restricted','rejected') NOT NULL DEFAULT 'unreviewed',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_trader_profiles_public_id (public_id),
  UNIQUE KEY uq_trader_profiles_user (user_id),
  KEY idx_trader_profiles_status (status),
  KEY idx_trader_profiles_risk (risk_status),
  CONSTRAINT fk_trader_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO user_models (public_id, code, name, description, is_system, is_assignable, requires_approval, default_status, sort_order)
VALUES
  ('um_customer', 'customer', 'Customer', 'Default personal gifting model.', 1, 1, 0, 'active', 10),
  ('um_creator', 'creator', 'Creator', 'Creator identity for public campaigns, collections, templates, and profile pages.', 0, 1, 1, 'pending', 20),
  ('um_merchant', 'merchant', 'Merchant', 'Business/vendor identity for stores, offers, products, claims, and redemptions.', 0, 1, 1, 'pending', 30),
  ('um_moderator', 'moderator', 'Moderator', 'Platform trust and review operating model.', 1, 0, 1, 'pending', 40),
  ('um_vendor_manager', 'vendor_manager', 'Vendor Manager', 'Vendor onboarding, merchant review, and business relationship operations.', 1, 0, 1, 'pending', 50),
  ('um_marketing_affiliate', 'marketing_affiliate', 'Marketing Affiliate', 'Referral, campaign, and promotion operating model.', 0, 1, 1, 'pending', 60),
  ('um_trader', 'trader', 'Trader', 'Future trading/marketplace operating model requiring review.', 1, 0, 1, 'pending', 70),
  ('um_admin', 'admin', 'Admin', 'Platform operator model.', 1, 0, 1, 'pending', 80),
  ('um_super_admin', 'super_admin', 'Super Admin', 'Platform owner model controlled through roles and permissions.', 1, 0, 1, 'pending', 90)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  is_system = VALUES(is_system),
  is_assignable = VALUES(is_assignable),
  requires_approval = VALUES(requires_approval),
  default_status = VALUES(default_status),
  sort_order = VALUES(sort_order),
  updated_at = NOW();

INSERT INTO user_model_assignments (public_id, user_id, user_model_id, status, requested_at, enabled_at, reason, metadata_json, created_at)
SELECT CONCAT('uma_customer_', u.id), u.id, um.id, 'active', NOW(), NOW(), '02A default customer model backfill', JSON_OBJECT('source', '02A_user_models_identity_profiles.sql'), NOW()
FROM users u
INNER JOIN user_models um ON um.code = 'customer'
WHERE NOT EXISTS (
  SELECT 1
  FROM user_model_assignments existing
  WHERE existing.user_id = u.id
    AND existing.user_model_id = um.id
);

INSERT INTO user_model_events (public_id, user_id, user_model_id, assignment_id, event_type, from_status, to_status, actor_user_id, reason, metadata_json, created_at)
SELECT CONCAT('ume_customer_backfill_', uma.id), uma.user_id, uma.user_model_id, uma.id, 'user_model.enabled', NULL, 'active', NULL, '02A default customer model backfill', JSON_OBJECT('source', '02A_user_models_identity_profiles.sql'), NOW()
FROM user_model_assignments uma
INNER JOIN user_models um ON um.id = uma.user_model_id AND um.code = 'customer'
WHERE uma.reason = '02A default customer model backfill'
  AND NOT EXISTS (
    SELECT 1
    FROM user_model_events e
    WHERE e.public_id = CONCAT('ume_customer_backfill_', uma.id)
  );

COMMIT;
