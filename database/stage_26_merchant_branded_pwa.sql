-- Stage 26 Merchant-Branded PWA Mode
-- Gives each merchant workspace a Microgifter-powered branded install URL, manifest,
-- icon set, splash/start screen copy, and public-safe asset storage mapping.

CREATE TABLE IF NOT EXISTS merchant_pwa_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  merchant_slug VARCHAR(100) NOT NULL,
  app_name VARCHAR(100) NOT NULL,
  short_name VARCHAR(60) NOT NULL,
  description VARCHAR(280) NULL,
  install_headline VARCHAR(140) NULL,
  install_subtitle VARCHAR(320) NULL,
  splash_title VARCHAR(120) NULL,
  splash_subtitle VARCHAR(320) NULL,
  theme_color CHAR(7) NOT NULL DEFAULT '#2563eb',
  background_color CHAR(7) NOT NULL DEFAULT '#f8fafc',
  status ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'draft',
  enable_install_prompt TINYINT(1) NOT NULL DEFAULT 1,
  enable_push_prompt TINYINT(1) NOT NULL DEFAULT 1,
  settings_json JSON NULL,
  activated_at DATETIME NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_pwa_profiles_public_id (public_id),
  UNIQUE KEY uq_merchant_pwa_profiles_workspace (workspace_id),
  UNIQUE KEY uq_merchant_pwa_profiles_slug (merchant_slug),
  KEY idx_merchant_pwa_profiles_status (status,updated_at),
  KEY idx_merchant_pwa_profiles_updated_by (updated_by_user_id),
  CONSTRAINT fk_merchant_pwa_profiles_workspace FOREIGN KEY (workspace_id) REFERENCES merchant_workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_pwa_profiles_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_pwa_assets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  profile_id BIGINT UNSIGNED NOT NULL,
  asset_role ENUM('app_icon_192','app_icon_512','maskable_icon_512','apple_touch_icon','notification_icon','notification_badge','splash_logo','splash_background') NOT NULL,
  storage_provider VARCHAR(40) NOT NULL DEFAULT 'public_local',
  storage_key VARCHAR(255) NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  byte_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  checksum_sha256 CHAR(64) NULL,
  width_px INT UNSIGNED NULL,
  height_px INT UNSIGNED NULL,
  status ENUM('active','archived','deleted') NOT NULL DEFAULT 'active',
  uploaded_by_user_id BIGINT UNSIGNED NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_pwa_assets_public_id (public_id),
  KEY idx_merchant_pwa_assets_profile_role_status (profile_id,asset_role,status,updated_at),
  KEY idx_merchant_pwa_assets_workspace (workspace_id,status,updated_at),
  KEY idx_merchant_pwa_assets_uploaded_by (uploaded_by_user_id),
  CONSTRAINT fk_merchant_pwa_assets_workspace FOREIGN KEY (workspace_id) REFERENCES merchant_workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_pwa_assets_profile FOREIGN KEY (profile_id) REFERENCES merchant_pwa_profiles(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_pwa_assets_uploaded_by FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.pwa.view','View merchant PWA branding','View the merchant-branded PWA install profile, manifest, app routes, and asset status.',NOW()),
('merchant.pwa.manage','Manage merchant PWA branding','Update merchant-branded PWA copy, colors, app install settings, and public-safe app assets.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('merchant.pwa.view','merchant.pwa.manage')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_26_merchant_branded_pwa',
  'Adds merchant-branded PWA install profiles, app manifests, public-safe branded assets, and merchant install mode scaffolding.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);
