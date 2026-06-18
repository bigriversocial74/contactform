-- Stage 18M: Admin Merchant and Catalog Operations Center
START TRANSACTION;

ALTER TABLE merchant_storefronts
  MODIFY status ENUM('draft','published','suspended','archived') NOT NULL DEFAULT 'draft';

ALTER TABLE catalog_products
  MODIFY status ENUM('draft','review','published','paused','archived') NOT NULL DEFAULT 'draft';

CREATE TABLE IF NOT EXISTS merchant_catalog_operation_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  subject_type ENUM('workspace','storefront','product','asset') NOT NULL,
  subject_reference VARCHAR(190) NOT NULL,
  action_type VARCHAR(80) NOT NULL,
  from_status VARCHAR(40) NULL,
  to_status VARCHAR(40) NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(1000) NOT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_catalog_operation_events_public_id (public_id),
  KEY idx_merchant_catalog_operation_events_subject (subject_type,subject_reference,created_at,id),
  KEY idx_merchant_catalog_operation_events_actor (actor_user_id,created_at,id),
  CONSTRAINT fk_merchant_catalog_operation_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name) VALUES
  ('admin.merchants.view','View merchant operations'),
  ('admin.merchants.manage','Manage merchant operations'),
  ('admin.catalog.view','View catalog operations'),
  ('admin.catalog.manage','Manage catalog operations');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.slug IN (
  'admin.merchants.view','admin.merchants.manage','admin.catalog.view','admin.catalog.manage'
)
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_18m_admin_merchant_catalog_operations','Protected merchant, storefront, catalog product, and asset lifecycle operations.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
