-- Stage 19c: Design Studio brand kits
-- Purpose: merchant brand kits and website-scanned brand asset candidates.
-- Depends on: stage_19a, users, roles, permissions, role_permissions, merchant_workspaces.

CREATE TABLE IF NOT EXISTS merchant_brand_kits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL COMMENT 'Stable public UUID used by APIs.',
  workspace_id BIGINT UNSIGNED NOT NULL COMMENT 'Owning merchant workspace.',
  merchant_user_id BIGINT UNSIGNED NOT NULL COMMENT 'Merchant owner at time of creation.',
  name VARCHAR(180) NOT NULL,
  status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
  source_url VARCHAR(1000) NULL COMMENT 'Website URL scanned or manually entered source.',
  logo_url VARCHAR(1000) NULL COMMENT 'Preferred candidate logo URL.',
  primary_color CHAR(7) NULL,
  secondary_color CHAR(7) NULL,
  accent_color CHAR(7) NULL,
  palette_json JSON NULL COMMENT 'Array of discovered/approved hex colors.',
  font_json JSON NULL COMMENT 'Future font selections and brand typography rules.',
  social_json JSON NULL COMMENT 'Detected or manually entered social profile links.',
  image_candidates_json JSON NULL COMMENT 'Website image candidates awaiting approval/import.',
  scan_result_json JSON NULL COMMENT 'Raw scanner result summary; no raw HTML stored.',
  scan_status ENUM('not_started','queued','scanned','failed','approved') NOT NULL DEFAULT 'not_started',
  scanned_at DATETIME NULL,
  approved_at DATETIME NULL,
  approved_by_user_id BIGINT UNSIGNED NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  archived_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_brand_kits_public_id (public_id),
  KEY idx_merchant_brand_kits_workspace (workspace_id,status,updated_at),
  KEY idx_merchant_brand_kits_scan_status (workspace_id,scan_status,scanned_at),
  CONSTRAINT fk_merchant_brand_kits_workspace FOREIGN KEY (workspace_id) REFERENCES merchant_workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_brand_kits_merchant_user FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_brand_kits_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_brand_kits_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_brand_kits_approved_by FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchant brand kit sourced from profile data, manual edits, or website scanner.';

CREATE TABLE IF NOT EXISTS merchant_brand_kit_assets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  brand_kit_id BIGINT UNSIGNED NOT NULL,
  asset_role ENUM('logo','favicon','hero_image','product_image','social_image','background','other') NOT NULL DEFAULT 'other',
  source_url VARCHAR(1000) NOT NULL,
  status ENUM('candidate','approved','rejected','imported','archived') NOT NULL DEFAULT 'candidate',
  confidence_score DECIMAL(5,4) NULL COMMENT 'Optional scanner/import confidence from 0.0000 to 1.0000.',
  imported_design_asset_id BIGINT UNSIGNED NULL COMMENT 'Filled after candidate is imported into merchant_design_assets.',
  approved_at DATETIME NULL,
  approved_by_user_id BIGINT UNSIGNED NULL,
  rejected_at DATETIME NULL,
  rejected_by_user_id BIGINT UNSIGNED NULL,
  imported_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_brand_kit_assets_public_id (public_id),
  UNIQUE KEY uq_merchant_brand_kit_assets_source (brand_kit_id,asset_role,source_url(255)),
  KEY idx_merchant_brand_kit_assets_kit (brand_kit_id,status,asset_role),
  KEY idx_merchant_brand_kit_assets_imported_asset (imported_design_asset_id),
  CONSTRAINT fk_merchant_brand_kit_assets_kit FOREIGN KEY (brand_kit_id) REFERENCES merchant_brand_kits(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_brand_kit_assets_approved_by FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_brand_kit_assets_rejected_by FOREIGN KEY (rejected_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logo/image candidates discovered for a merchant brand kit.';

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.brand_kits.view','View brand kits','View merchant brand kits and scanner results.',NOW()),
('merchant.brand_kits.manage','Manage brand kits','Scan websites and manage merchant brand kits.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('merchant.brand_kits.view','merchant.brand_kits.manage')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT INTO microgifter_schema_migrations (migration_key,migration_group,description,checksum,applied_at,created_at,updated_at)
VALUES ('stage_19c_design_studio_brand_kits','design_studio','Create merchant brand kit and scanned candidate asset tables.',SHA2('stage_19c_design_studio_brand_kits',256),NOW(),NOW(),NOW())
ON DUPLICATE KEY UPDATE updated_at=NOW();
