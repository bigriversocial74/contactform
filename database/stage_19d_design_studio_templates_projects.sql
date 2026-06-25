-- Stage 19d: Design Studio templates, reviews, and saved projects
-- Purpose: reusable print/social templates with versioning plus merchant saved projects.
-- Depends on: stage_19b QR library, stage_19c brand kits, users, roles, permissions, role_permissions, merchant_workspaces.

CREATE TABLE IF NOT EXISTS merchant_design_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  workspace_id BIGINT UNSIGNED NULL COMMENT 'NULL for system/admin templates; set for merchant-scoped templates.',
  merchant_user_id BIGINT UNSIGNED NULL,
  brand_kit_id BIGINT UNSIGNED NULL,
  parent_template_id BIGINT UNSIGNED NULL COMMENT 'Immediate parent template for derived copies.',
  supersedes_template_id BIGINT UNSIGNED NULL COMMENT 'Previous published version replaced by this version.',
  template_family_key VARCHAR(120) NULL COMMENT 'Stable family/group key across versions.',
  template_scope ENUM('system','admin','merchant') NOT NULL DEFAULT 'merchant',
  template_type ENUM('print','social','digital') NOT NULL DEFAULT 'print',
  category_key VARCHAR(80) NULL,
  format_key VARCHAR(80) NOT NULL,
  version_number INT UNSIGNED NOT NULL DEFAULT 1,
  version_label VARCHAR(80) NULL COMMENT 'Human-readable version such as v1, holiday-v2, or merchant-copy-3.',
  version_notes VARCHAR(500) NULL,
  name VARCHAR(180) NOT NULL,
  description VARCHAR(500) NULL,
  status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
  review_status ENUM('not_submitted','pending','approved','rejected','changes_requested') NOT NULL DEFAULT 'not_submitted',
  width_px INT UNSIGNED NULL,
  height_px INT UNSIGNED NULL,
  print_width_in DECIMAL(8,3) NULL,
  print_height_in DECIMAL(8,3) NULL,
  bleed_in DECIMAL(8,3) NULL,
  layout_json JSON NOT NULL,
  default_copy_json JSON NULL,
  render_config_json JSON NULL,
  qr_required TINYINT(1) NOT NULL DEFAULT 1,
  is_presigned TINYINT(1) NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_locked TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Locked templates should only be edited by admin/design owners.',
  signature_hash CHAR(64) NULL,
  signed_at DATETIME NULL,
  submitted_at DATETIME NULL,
  approved_at DATETIME NULL,
  published_at DATETIME NULL,
  approved_by_user_id BIGINT UNSIGNED NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  archived_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_design_templates_public_id (public_id),
  UNIQUE KEY uq_merchant_design_templates_family_version (template_scope,template_family_key,version_number,workspace_id),
  KEY idx_merchant_design_templates_workspace (workspace_id,status,template_type,format_key),
  KEY idx_merchant_design_templates_scope (template_scope,status,review_status,template_type,format_key),
  KEY idx_merchant_design_templates_category (category_key,is_featured,status),
  KEY idx_merchant_design_templates_family (template_family_key,version_number,status),
  CONSTRAINT fk_merchant_design_templates_workspace FOREIGN KEY (workspace_id) REFERENCES merchant_workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_design_templates_merchant_user FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_design_templates_brand_kit FOREIGN KEY (brand_kit_id) REFERENCES merchant_brand_kits(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_design_templates_parent FOREIGN KEY (parent_template_id) REFERENCES merchant_design_templates(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_design_templates_supersedes FOREIGN KEY (supersedes_template_id) REFERENCES merchant_design_templates(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_design_templates_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_design_templates_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_design_templates_approved_by FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reusable Design Studio templates with version/family tracking.';

CREATE TABLE IF NOT EXISTS merchant_design_template_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  template_id BIGINT UNSIGNED NOT NULL,
  review_status ENUM('pending','approved','rejected','changes_requested') NOT NULL DEFAULT 'pending',
  reviewer_user_id BIGINT UNSIGNED NULL,
  notes TEXT NULL,
  checklist_json JSON NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_design_template_reviews_public_id (public_id),
  KEY idx_merchant_design_template_reviews_template (template_id,review_status,created_at),
  CONSTRAINT fk_merchant_design_template_reviews_template FOREIGN KEY (template_id) REFERENCES merchant_design_templates(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_design_template_reviews_reviewer FOREIGN KEY (reviewer_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin/design review history for merchant and system templates.';

CREATE TABLE IF NOT EXISTS merchant_design_projects (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  brand_kit_id BIGINT UNSIGNED NULL,
  template_id BIGINT UNSIGNED NULL,
  qr_code_id BIGINT UNSIGNED NULL,
  project_type ENUM('print','social','digital') NOT NULL DEFAULT 'print',
  format_key VARCHAR(80) NOT NULL,
  name VARCHAR(180) NOT NULL,
  status ENUM('draft','ready_for_review','approved','exported','archived') NOT NULL DEFAULT 'draft',
  revision_number INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Increment when project canvas changes.',
  source_template_version INT UNSIGNED NULL COMMENT 'Template version used when project was created/saved.',
  canvas_json JSON NOT NULL,
  copy_json JSON NULL,
  media_json JSON NULL,
  print_options_json JSON NULL,
  export_manifest_json JSON NULL,
  last_exported_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  archived_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_design_projects_public_id (public_id),
  KEY idx_merchant_design_projects_workspace (workspace_id,status,project_type,format_key,updated_at),
  KEY idx_merchant_design_projects_merchant (merchant_user_id,status,updated_at),
  KEY idx_merchant_design_projects_template (template_id,source_template_version,status),
  CONSTRAINT fk_merchant_design_projects_workspace FOREIGN KEY (workspace_id) REFERENCES merchant_workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_design_projects_merchant_user FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_design_projects_brand_kit FOREIGN KEY (brand_kit_id) REFERENCES merchant_brand_kits(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_design_projects_template FOREIGN KEY (template_id) REFERENCES merchant_design_templates(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_design_projects_qr FOREIGN KEY (qr_code_id) REFERENCES merchant_qr_codes(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_design_projects_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_design_projects_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchant saved Design Studio canvases/projects.';

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.design_studio.view','View Design Studio','Open the merchant design studio workspace.',NOW()),
('merchant.design_studio.manage','Manage Design Studio','Create, edit, and export merchant design studio assets.',NOW()),
('merchant.design_templates.view','View design templates','View saved print and social templates.',NOW()),
('merchant.design_templates.manage','Manage design templates','Create and update saved print and social templates.',NOW()),
('merchant.design_templates.admin','Administer design templates','Approve, publish, and feature system design templates.',NOW()),
('merchant.design_projects.view','View design projects','View saved Design Studio projects.',NOW()),
('merchant.design_projects.manage','Manage design projects','Save and update Design Studio projects.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('merchant.design_studio.view','merchant.design_studio.manage','merchant.design_templates.view','merchant.design_templates.manage','merchant.design_projects.view','merchant.design_projects.manage')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('merchant.design_templates.admin')
WHERE r.slug IN ('admin','super_admin');

INSERT INTO microgifter_schema_migrations (migration_key,migration_group,description,checksum,applied_at,created_at,updated_at)
VALUES ('stage_19d_design_studio_templates_projects','design_studio','Create Design Studio templates, reviews, and merchant projects with versioning fields.',SHA2('stage_19d_design_studio_templates_projects',256),NOW(),NOW(),NOW())
ON DUPLICATE KEY UPDATE updated_at=NOW();
