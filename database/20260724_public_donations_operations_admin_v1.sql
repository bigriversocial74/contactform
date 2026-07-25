-- Microgifter Public Donations Operations Admin v1
-- Additive operational control plane. Safe to import before or after the
-- Public Donations single-install foundation.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS public_donations_operations_settings (
  id TINYINT UNSIGNED NOT NULL,
  override_active TINYINT(1) NOT NULL DEFAULT 0,
  feature_state ENUM('disabled','admin_only','selected_merchants','enabled') NOT NULL DEFAULT 'disabled',
  selected_merchant_ids_json JSON NULL,
  configuration_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
  change_reason VARCHAR(240) NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT chk_public_donations_operations_settings_singleton CHECK (id = 1),
  CONSTRAINT chk_public_donations_operations_settings_override CHECK (override_active IN (0,1)),
  KEY idx_public_donations_operations_settings_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO public_donations_operations_settings
  (id,override_active,feature_state,selected_merchant_ids_json,configuration_version,created_at,updated_at)
VALUES
  (1,0,'disabled',JSON_ARRAY(),1,NOW(),NOW())
ON DUPLICATE KEY UPDATE id=VALUES(id);

CREATE TABLE IF NOT EXISTS public_donations_reconciliation_receipts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  receipt_id CHAR(36) NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  campaign_reference VARCHAR(190) NULL,
  operation_reference VARCHAR(190) NULL,
  execution_mode ENUM('dry_run','repair') NOT NULL,
  repair_modes_json JSON NULL,
  issues_before INT UNSIGNED NOT NULL DEFAULT 0,
  repairable_before INT UNSIGNED NOT NULL DEFAULT 0,
  report_only_before INT UNSIGNED NOT NULL DEFAULT 0,
  repairs_applied INT UNSIGNED NOT NULL DEFAULT 0,
  issues_after INT UNSIGNED NOT NULL DEFAULT 0,
  unexplained_drift_after INT UNSIGNED NOT NULL DEFAULT 0,
  checksum CHAR(64) NOT NULL,
  reason VARCHAR(240) NOT NULL,
  receipt_json JSON NOT NULL,
  report_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_public_donations_reconciliation_receipt_id (receipt_id),
  KEY idx_public_donations_reconciliation_merchant_date (merchant_user_id,created_at),
  KEY idx_public_donations_reconciliation_mode_date (execution_mode,created_at),
  KEY idx_public_donations_reconciliation_drift_date (unexplained_drift_after,created_at),
  KEY idx_public_donations_reconciliation_actor_date (actor_user_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug,name,created_at) VALUES
  ('admin.public_donations_operations.view','View Public Donations operations',NOW()),
  ('admin.public_donations_operations.manage','Manage Public Donations rollout and reconciliation',NOW()),
  ('admin.public_donations_operations.repair','Execute deterministic Public Donations repairs',NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT role.id,permission.id,NOW()
FROM roles role
INNER JOIN permissions permission ON permission.slug IN (
  'admin.public_donations_operations.view',
  'admin.public_donations_operations.manage',
  'admin.public_donations_operations.repair'
)
WHERE role.slug IN ('admin','super_admin');

COMMIT;
