-- Stage 4A Product Catalog and Asset Foundation

CREATE TABLE IF NOT EXISTS catalog_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  product_type ENUM('gift','prize','reward','voucher','entitlement','reservation','credit','digital_product','other') NOT NULL DEFAULT 'gift',
  slug VARCHAR(160) NOT NULL,
  status ENUM('draft','review','published','archived') NOT NULL DEFAULT 'draft',
  current_version_id BIGINT UNSIGNED NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  published_at DATETIME NULL,
  archived_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catalog_products_public_id (public_id),
  UNIQUE KEY uq_catalog_products_merchant_slug (merchant_user_id, slug),
  KEY idx_catalog_products_merchant_status (merchant_user_id, status, updated_at),
  CONSTRAINT fk_catalog_products_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_catalog_products_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_product_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  version_status ENUM('draft','published','retired') NOT NULL DEFAULT 'draft',
  title VARCHAR(160) NOT NULL,
  description TEXT NULL,
  unit_value_cents INT UNSIGNED NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  expiration_policy_json JSON NULL,
  terms_json JSON NULL,
  fulfillment_json JSON NULL,
  metadata_json JSON NULL,
  checksum CHAR(64) NOT NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catalog_product_versions_public_id (public_id),
  UNIQUE KEY uq_catalog_product_versions_number (product_id, version_number),
  KEY idx_catalog_product_versions_status (product_id, version_status, created_at),
  CONSTRAINT fk_catalog_product_versions_product FOREIGN KEY (product_id) REFERENCES catalog_products(id) ON DELETE RESTRICT,
  CONSTRAINT fk_catalog_product_versions_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_assets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  asset_type ENUM('image','audio','video','document','download','qr_template','other') NOT NULL,
  storage_provider VARCHAR(80) NOT NULL,
  storage_key VARCHAR(500) NOT NULL,
  original_filename VARCHAR(255) NULL,
  mime_type VARCHAR(120) NULL,
  byte_size BIGINT UNSIGNED NULL,
  checksum_sha256 CHAR(64) NULL,
  width_px INT UNSIGNED NULL,
  height_px INT UNSIGNED NULL,
  duration_ms INT UNSIGNED NULL,
  status ENUM('pending','ready','quarantined','failed','archived') NOT NULL DEFAULT 'pending',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catalog_assets_public_id (public_id),
  UNIQUE KEY uq_catalog_assets_provider_key (storage_provider, storage_key),
  KEY idx_catalog_assets_owner_status (owner_user_id, status, created_at),
  CONSTRAINT fk_catalog_assets_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_product_version_assets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_version_id BIGINT UNSIGNED NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  role ENUM('cover','inside_cover','gallery','audio','carousel','back','download','thumbnail','other') NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  presentation_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catalog_version_asset_role_order (product_version_id, asset_id, role, sort_order),
  KEY idx_catalog_version_assets_order (product_version_id, role, sort_order),
  CONSTRAINT fk_catalog_version_assets_version FOREIGN KEY (product_version_id) REFERENCES catalog_product_versions(id) ON DELETE CASCADE,
  CONSTRAINT fk_catalog_version_assets_asset FOREIGN KEY (asset_id) REFERENCES catalog_assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_pppm_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  product_version_id BIGINT UNSIGNED NOT NULL,
  item_type ENUM('gift','prize','reward','voucher','entitlement','reservation','credit','other') NOT NULL,
  default_funding_type ENUM('customer_purchase','merchant_funded','sponsor_funded','platform_funded','promotional','earned_reward','free','other') NOT NULL DEFAULT 'other',
  issuance_defaults_json JSON NULL,
  status ENUM('active','inactive','retired') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catalog_pppm_templates_public_id (public_id),
  UNIQUE KEY uq_catalog_pppm_templates_version (product_version_id),
  CONSTRAINT fk_catalog_pppm_templates_version FOREIGN KEY (product_version_id) REFERENCES catalog_product_versions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE catalog_products
  ADD CONSTRAINT fk_catalog_products_current_version
  FOREIGN KEY (current_version_id) REFERENCES catalog_product_versions(id) ON DELETE RESTRICT;

INSERT IGNORE INTO permissions (slug, name, description, created_at) VALUES
('catalog.products.view', 'View catalog products', 'View owned and published catalog products.', NOW()),
('catalog.products.manage', 'Manage catalog products', 'Create and edit merchant catalog products.', NOW()),
('catalog.products.publish', 'Publish catalog products', 'Publish immutable catalog product versions.', NOW()),
('catalog.assets.manage', 'Manage catalog assets', 'Register and manage product asset metadata.', NOW());

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('catalog.products.view','catalog.products.manage','catalog.products.publish','catalog.assets.manage')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
JOIN permissions p ON p.slug = 'catalog.products.view'
WHERE r.slug = 'customer';