-- Stage 4B Builder Persistence and Product Publishing

CREATE TABLE IF NOT EXISTS catalog_builder_drafts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  builder_type ENUM('simple_product','greeting_card','multimedia_greeting_card','simple_collab') NOT NULL DEFAULT 'simple_product',
  payload_json JSON NOT NULL,
  asset_map_json JSON NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catalog_builder_drafts_public_id (public_id),
  UNIQUE KEY uq_catalog_builder_drafts_product (product_id),
  KEY idx_catalog_builder_drafts_updated (updated_by_user_id, updated_at),
  CONSTRAINT fk_catalog_builder_drafts_product FOREIGN KEY (product_id) REFERENCES catalog_products(id) ON DELETE CASCADE,
  CONSTRAINT fk_catalog_builder_drafts_user FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE catalog_product_version_assets
  MODIFY role ENUM('cover','inside_cover','gallery','audio','video','carousel','back','download','thumbnail','other') NOT NULL;
