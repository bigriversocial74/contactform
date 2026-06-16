-- Stage 5C Storefront Management, Product Placement, and Public Preview

CREATE TABLE IF NOT EXISTS merchant_storefront_revisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  storefront_id BIGINT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  revision_status ENUM('draft','published','retired') NOT NULL DEFAULT 'draft',
  display_name VARCHAR(160) NOT NULL,
  headline VARCHAR(240) NULL,
  description TEXT NULL,
  logo_asset_id BIGINT UNSIGNED NULL,
  cover_asset_id BIGINT UNSIGNED NULL,
  contact_json JSON NULL,
  theme_json JSON NULL,
  checksum CHAR(64) NOT NULL,
  published_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_storefront_revisions_public_id (public_id),
  UNIQUE KEY uq_storefront_revisions_version (storefront_id,version_number),
  KEY idx_storefront_revisions_status (storefront_id,revision_status,version_number),
  CONSTRAINT fk_storefront_revisions_storefront FOREIGN KEY (storefront_id) REFERENCES merchant_storefronts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_storefront_revisions_logo FOREIGN KEY (logo_asset_id) REFERENCES catalog_assets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_storefront_revisions_cover FOREIGN KEY (cover_asset_id) REFERENCES catalog_assets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_storefront_revisions_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_storefront_revision_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  storefront_revision_id BIGINT UNSIGNED NOT NULL,
  catalog_product_id BIGINT UNSIGNED NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  visibility ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_storefront_revision_product (storefront_revision_id,catalog_product_id),
  KEY idx_storefront_revision_products_order (storefront_revision_id,visibility,is_featured,sort_order),
  CONSTRAINT fk_storefront_revision_products_revision FOREIGN KEY (storefront_revision_id) REFERENCES merchant_storefront_revisions(id) ON DELETE CASCADE,
  CONSTRAINT fk_storefront_revision_products_product FOREIGN KEY (catalog_product_id) REFERENCES catalog_products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_storefront_states (
  storefront_id BIGINT UNSIGNED NOT NULL,
  draft_revision_id BIGINT UNSIGNED NULL,
  published_revision_id BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (storefront_id),
  UNIQUE KEY uq_storefront_state_draft (draft_revision_id),
  UNIQUE KEY uq_storefront_state_published (published_revision_id),
  CONSTRAINT fk_storefront_state_storefront FOREIGN KEY (storefront_id) REFERENCES merchant_storefronts(id) ON DELETE CASCADE,
  CONSTRAINT fk_storefront_state_draft FOREIGN KEY (draft_revision_id) REFERENCES merchant_storefront_revisions(id) ON DELETE SET NULL,
  CONSTRAINT fk_storefront_state_published FOREIGN KEY (published_revision_id) REFERENCES merchant_storefront_revisions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('storefront.preview','Preview storefront drafts','Preview merchant storefront draft revisions.',NOW()),
('storefront.publish','Publish storefront revisions','Publish immutable merchant storefront revisions.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('storefront.preview','storefront.publish')
WHERE r.slug IN ('merchant','admin','super_admin');
