-- Stage 19 Design Studio campaign links import fix
-- Use this only if stage_19_design_studio_qr_library.sql stopped at:
--   #1215 - Cannot add foreign key constraint
-- on merchant_design_campaign_links.
--
-- This creates the campaign link table without physical foreign keys while preserving
-- indexed relationship columns. The application still controls those relationships.

CREATE TABLE IF NOT EXISTS merchant_design_campaign_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NULL,
  template_id BIGINT UNSIGNED NULL,
  asset_id BIGINT UNSIGNED NULL,
  qr_code_id BIGINT UNSIGNED NULL,
  campaign_type ENUM('promotional_crm','newsletter','contest','landing_page','distribution','product','custom') NOT NULL DEFAULT 'custom',
  campaign_ref VARCHAR(180) NOT NULL,
  campaign_unique_hash CHAR(64) GENERATED ALWAYS AS (SHA2(CONCAT_WS('|',workspace_id,campaign_type,campaign_ref,IFNULL(project_id,0),IFNULL(template_id,0),IFNULL(asset_id,0),IFNULL(qr_code_id,0)),256)) STORED,
  label VARCHAR(180) NULL,
  metadata_json JSON NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_design_campaign_links_public_id (public_id),
  UNIQUE KEY uq_merchant_design_campaign_links_hash (campaign_unique_hash),
  KEY idx_merchant_design_campaign_links_workspace (workspace_id,campaign_type,campaign_ref),
  KEY idx_merchant_design_campaign_links_project (project_id,campaign_type,campaign_ref),
  KEY idx_merchant_design_campaign_links_template (template_id,campaign_type,campaign_ref),
  KEY idx_merchant_design_campaign_links_asset (asset_id,campaign_type,campaign_ref),
  KEY idx_merchant_design_campaign_links_qr (qr_code_id,campaign_type,campaign_ref),
  KEY idx_merchant_design_campaign_links_created_by (created_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Links Design Studio outputs to promotional CRM, campaign, product, landing, and distribution records.';

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.design_studio.view','View Design Studio','Open the merchant design studio workspace.',NOW()),
('merchant.design_studio.manage','Manage Design Studio','Create, edit, and export merchant design studio assets.',NOW()),
('merchant.brand_kits.view','View brand kits','View merchant brand kits and scanner results.',NOW()),
('merchant.brand_kits.manage','Manage brand kits','Scan websites and manage merchant brand kits.',NOW()),
('merchant.design_templates.view','View design templates','View saved print and social templates.',NOW()),
('merchant.design_templates.manage','Manage design templates','Create and update saved print and social templates.',NOW()),
('merchant.design_templates.admin','Administer design templates','Approve, publish, and feature system design templates.',NOW()),
('merchant.design_projects.view','View design projects','View saved Design Studio projects.',NOW()),
('merchant.design_projects.manage','Manage design projects','Save and update Design Studio projects.',NOW()),
('merchant.design_assets.view','View design assets','View exported and generated Design Studio assets.',NOW()),
('merchant.design_assets.manage','Manage design assets','Create design asset records and export jobs.',NOW()),
('merchant.design_ai.generate','Generate design assets with AI','Request AI-generated Design Studio images, copy, layouts, and variations.',NOW()),
('merchant.design_ai.admin','Administer design AI','Manage AI prompt presets and approve generated asset workflows.',NOW()),
('merchant.qr_library.view','View merchant QR library','View QR codes available to merchant design and campaign tools.',NOW()),
('merchant.qr_library.manage','Manage merchant QR library','Create, update, pause, and archive merchant QR codes.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('merchant.design_studio.view','merchant.design_studio.manage','merchant.brand_kits.view','merchant.brand_kits.manage','merchant.design_templates.view','merchant.design_templates.manage','merchant.design_projects.view','merchant.design_projects.manage','merchant.design_assets.view','merchant.design_assets.manage','merchant.design_ai.generate','merchant.qr_library.view','merchant.qr_library.manage')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('merchant.design_templates.admin','merchant.design_ai.admin')
WHERE r.slug IN ('admin','super_admin');

INSERT IGNORE INTO merchant_design_ai_presets (public_id,template_scope,preset_key,name,category_key,generation_type,status,prompt_template_json,safety_json,created_at,updated_at) VALUES
('90000000-0000-4000-8000-000000000191','system','restaurant-food-promo','Restaurant Food Promo','restaurant','image','active',JSON_OBJECT('prompt','Create a clean, appetizing promotional image for a local restaurant offer. Keep room for headline, CTA, and QR code.','negative','No alcohol focus, no medical claims, no fake coupons.'),JSON_OBJECT('requires_review',true),NOW(),NOW()),
('90000000-0000-4000-8000-000000000192','system','live-event-promo','Live Event Promo','event','image','active',JSON_OBJECT('prompt','Create an energetic local event promotional image with stage-light energy and clear negative space for QR and text.','negative','No copyrighted artist likenesses, no unsafe crowd scenes.'),JSON_OBJECT('requires_review',true),NOW(),NOW()),
('90000000-0000-4000-8000-000000000193','system','fitness-challenge','Fitness Challenge','fitness','image','active',JSON_OBJECT('prompt','Create a bright fitness challenge promotional image for a local gym or studio with motivational energy and space for CTA.','negative','No body-shaming, no medical transformation claims.'),JSON_OBJECT('requires_review',true),NOW(),NOW()),
('90000000-0000-4000-8000-000000000194','system','holiday-gift-card','Holiday Gift Card Promo','holiday','image','active',JSON_OBJECT('prompt','Create a warm seasonal gift-card promotional image for a local merchant with premium retail feel.','negative','Avoid religious specificity unless merchant provides it.'),JSON_OBJECT('requires_review',true),NOW(),NOW()),
('90000000-0000-4000-8000-000000000195','system','local-rewards-campaign','Local Rewards Campaign','rewards','image','active',JSON_OBJECT('prompt','Create a modern local rewards campaign image with community-commerce feel, branded space, CTA, and QR placement.','negative','No fake endorsements or misleading reward values.'),JSON_OBJECT('requires_review',true),NOW(),NOW());

INSERT INTO microgifter_schema_migrations (migration_key,migration_group,description,checksum,applied_at,created_at,updated_at)
VALUES ('stage_19_design_studio_qr_library','design_studio','Single-file Design Studio foundation migration: QR, brand kits, templates, projects, AI queue, assets, exports, campaign links, and permissions.',SHA2('stage_19_design_studio_qr_library',256),NOW(),NOW(),NOW())
ON DUPLICATE KEY UPDATE updated_at=NOW();
