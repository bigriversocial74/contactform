-- Stage 18F PPPM Product Publishing and Distribution

ALTER TABLE merchant_locations
  ADD COLUMN is_primary TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

ALTER TABLE catalog_pppm_templates
  ADD COLUMN microgift_template_version_id BIGINT UNSIGNED NULL AFTER product_version_id,
  ADD KEY idx_catalog_pppm_microgift_version (microgift_template_version_id),
  ADD CONSTRAINT fk_catalog_pppm_microgift_version
    FOREIGN KEY (microgift_template_version_id) REFERENCES microgift_template_versions(id) ON DELETE RESTRICT;

CREATE TABLE IF NOT EXISTS catalog_product_version_locations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_version_id BIGINT UNSIGNED NOT NULL,
  merchant_location_id BIGINT UNSIGNED NOT NULL,
  availability_status ENUM('available','hidden') NOT NULL DEFAULT 'available',
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catalog_product_version_location (product_version_id,merchant_location_id),
  KEY idx_catalog_product_location_search (merchant_location_id,availability_status,product_version_id),
  KEY idx_catalog_version_locations (product_version_id,availability_status,is_primary),
  CONSTRAINT fk_catalog_product_location_version
    FOREIGN KEY (product_version_id) REFERENCES catalog_product_versions(id) ON DELETE CASCADE,
  CONSTRAINT fk_catalog_product_location_location
    FOREIGN KEY (merchant_location_id) REFERENCES merchant_locations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_18f_pppm_publish_distribution',
  'Connects published PPPM voucher definitions to canonical Microgift versions and merchant location discovery.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);
