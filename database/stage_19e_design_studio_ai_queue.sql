-- Stage 19e: Design Studio AI presets and generation queue
-- Purpose: reusable AI prompt presets plus reliable queue fields for generated image/copy/layout jobs.
-- Depends on: stage_19c brand kits, stage_19d templates/projects, users, roles, permissions, role_permissions, merchant_workspaces.

CREATE TABLE IF NOT EXISTS merchant_design_ai_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NULL,
  brand_kit_id BIGINT UNSIGNED NULL,
  provider_key VARCHAR(80) NULL COMMENT 'AI provider integration key.',
  model_key VARCHAR(120) NULL COMMENT 'Provider model identifier.',
  provider_request_id VARCHAR(180) NULL COMMENT 'External provider request/job id when available.',
  generation_type ENUM('image','copy','layout','variation') NOT NULL DEFAULT 'image',
  prompt_json JSON NOT NULL,
  status ENUM('draft','queued','running','needs_approval','completed','failed','canceled') NOT NULL DEFAULT 'draft',
  approval_status ENUM('not_required','pending','approved','rejected') NOT NULL DEFAULT 'pending',
  priority TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Lower value runs first; default is normal priority.',
  attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
  locked_at DATETIME NULL COMMENT 'Worker lock timestamp.',
  locked_by VARCHAR(120) NULL COMMENT 'Worker id/process name that owns the lock.',
  next_attempt_at DATETIME NULL,
  last_attempt_at DATETIME NULL,
  failed_at DATETIME NULL,
  failure_code VARCHAR(80) NULL,
  worker_version VARCHAR(80) NULL,
  result_json JSON NULL,
  error_message VARCHAR(500) NULL,
  requested_by_user_id BIGINT UNSIGNED NOT NULL,
  approved_by_user_id BIGINT UNSIGNED NULL,
  output_asset_id BIGINT UNSIGNED NULL COMMENT 'Design asset created by this AI job; FK is intentionally deferred to avoid circular migration dependency.',
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_design_ai_jobs_public_id (public_id),
  KEY idx_merchant_design_ai_jobs_workspace (workspace_id,status,generation_type,created_at),
  KEY idx_merchant_design_ai_jobs_queue (status,priority,next_attempt_at,locked_at,created_at),
  KEY idx_merchant_design_ai_jobs_project (project_id,status,created_at),
  KEY idx_merchant_design_ai_jobs_provider (provider_key,provider_request_id),
  CONSTRAINT fk_merchant_design_ai_jobs_workspace FOREIGN KEY (workspace_id) REFERENCES merchant_workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_design_ai_jobs_merchant_user FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_design_ai_jobs_project FOREIGN KEY (project_id) REFERENCES merchant_design_projects(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_design_ai_jobs_brand_kit FOREIGN KEY (brand_kit_id) REFERENCES merchant_brand_kits(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_design_ai_jobs_requested_by FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_design_ai_jobs_approved_by FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reliable queue for AI generated Design Studio assets and variations.';

CREATE TABLE IF NOT EXISTS merchant_design_ai_presets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  workspace_id BIGINT UNSIGNED NULL,
  template_scope ENUM('system','admin','merchant') NOT NULL DEFAULT 'system',
  preset_key VARCHAR(120) NOT NULL,
  name VARCHAR(180) NOT NULL,
  category_key VARCHAR(80) NULL,
  generation_type ENUM('image','copy','layout','variation') NOT NULL DEFAULT 'image',
  status ENUM('draft','active','archived') NOT NULL DEFAULT 'active',
  prompt_template_json JSON NOT NULL,
  safety_json JSON NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_design_ai_presets_public_id (public_id),
  UNIQUE KEY uq_merchant_design_ai_presets_public_scope_key (template_scope,preset_key,workspace_id),
  KEY idx_merchant_design_ai_presets_scope (template_scope,status,category_key,generation_type),
  CONSTRAINT fk_merchant_design_ai_presets_workspace FOREIGN KEY (workspace_id) REFERENCES merchant_workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_design_ai_presets_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_design_ai_presets_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin/system/merchant AI prompt presets for Design Studio.';

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.design_ai.generate','Generate design assets with AI','Request AI-generated Design Studio images, copy, layouts, and variations.',NOW()),
('merchant.design_ai.admin','Administer design AI','Manage AI prompt presets and approve generated asset workflows.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('merchant.design_ai.generate')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('merchant.design_ai.admin')
WHERE r.slug IN ('admin','super_admin');

INSERT IGNORE INTO merchant_design_ai_presets (public_id,template_scope,preset_key,name,category_key,generation_type,status,prompt_template_json,safety_json,created_at,updated_at) VALUES
('90000000-0000-4000-8000-000000000191','system','restaurant-food-promo','Restaurant Food Promo','restaurant','image','active',JSON_OBJECT('prompt','Create a clean, appetizing promotional image for a local restaurant offer. Keep room for headline, CTA, and QR code.','negative','No alcohol focus, no medical claims, no fake coupons.'),JSON_OBJECT('requires_review',true),NOW(),NOW()),
('90000000-0000-4000-8000-000000000192','system','live-event-promo','Live Event Promo','event','image','active',JSON_OBJECT('prompt','Create an energetic local event promotional image with stage-light energy and clear negative space for QR and text.','negative','No copyrighted artist likenesses, no unsafe crowd scenes.'),JSON_OBJECT('requires_review',true),NOW(),NOW()),
('90000000-0000-4000-8000-000000000193','system','fitness-challenge','Fitness Challenge','fitness','image','active',JSON_OBJECT('prompt','Create a bright fitness challenge promotional image for a local gym or studio with motivational energy and space for CTA.','negative','No body-shaming, no medical transformation claims.'),JSON_OBJECT('requires_review',true),NOW(),NOW()),
('90000000-0000-4000-8000-000000000194','system','holiday-gift-card','Holiday Gift Card Promo','holiday','image','active',JSON_OBJECT('prompt','Create a warm seasonal gift-card promotional image for a local merchant with premium retail feel.','negative','Avoid religious specificity unless merchant provides it.'),JSON_OBJECT('requires_review',true),NOW(),NOW()),
('90000000-0000-4000-8000-000000000195','system','local-rewards-campaign','Local Rewards Campaign','rewards','image','active',JSON_OBJECT('prompt','Create a modern local rewards campaign image with community-commerce feel, branded space, CTA, and QR placement.','negative','No fake endorsements or misleading reward values.'),JSON_OBJECT('requires_review',true),NOW(),NOW());

INSERT INTO microgifter_schema_migrations (migration_key,migration_group,description,checksum,applied_at,created_at,updated_at)
VALUES ('stage_19e_design_studio_ai_queue','design_studio','Create AI presets and reliable AI generation queue fields.',SHA2('stage_19e_design_studio_ai_queue',256),NOW(),NOW(),NOW())
ON DUPLICATE KEY UPDATE updated_at=NOW();
