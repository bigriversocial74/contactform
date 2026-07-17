-- Microgifter Design Studio advertising workflow v2
-- Additive and safe to re-run after 20260716_design_studio_content_calendar.sql.

SET @mg_has_schedule := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'design_content_schedule'
);

SET @mg_has_theme := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'design_content_schedule' AND COLUMN_NAME = 'campaign_theme'
);
SET @mg_sql := IF(@mg_has_schedule = 1 AND @mg_has_theme = 0,
  "ALTER TABLE design_content_schedule ADD COLUMN campaign_theme VARCHAR(40) NOT NULL DEFAULT 'product_spotlight'",
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_caption_short := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'design_content_schedule' AND COLUMN_NAME = 'caption_short'
);
SET @mg_sql := IF(@mg_has_schedule = 1 AND @mg_has_caption_short = 0,
  'ALTER TABLE design_content_schedule ADD COLUMN caption_short VARCHAR(280) NULL',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_caption_standard := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'design_content_schedule' AND COLUMN_NAME = 'caption_standard'
);
SET @mg_sql := IF(@mg_has_schedule = 1 AND @mg_has_caption_standard = 0,
  'ALTER TABLE design_content_schedule ADD COLUMN caption_standard TEXT NULL',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_caption_extended := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'design_content_schedule' AND COLUMN_NAME = 'caption_extended'
);
SET @mg_sql := IF(@mg_has_schedule = 1 AND @mg_has_caption_extended = 0,
  'ALTER TABLE design_content_schedule ADD COLUMN caption_extended TEXT NULL',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_hashtags := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'design_content_schedule' AND COLUMN_NAME = 'hashtags'
);
SET @mg_sql := IF(@mg_has_schedule = 1 AND @mg_has_hashtags = 0,
  'ALTER TABLE design_content_schedule ADD COLUMN hashtags VARCHAR(500) NULL',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_product_link := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'design_content_schedule' AND COLUMN_NAME = 'product_link'
);
SET @mg_sql := IF(@mg_has_schedule = 1 AND @mg_has_product_link = 0,
  'ALTER TABLE design_content_schedule ADD COLUMN product_link VARCHAR(500) NULL',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_cta := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'design_content_schedule' AND COLUMN_NAME = 'call_to_action'
);
SET @mg_sql := IF(@mg_has_schedule = 1 AND @mg_has_cta = 0,
  'ALTER TABLE design_content_schedule ADD COLUMN call_to_action VARCHAR(160) NULL',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_platform_copy := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'design_content_schedule' AND COLUMN_NAME = 'platform_copy_json'
);
SET @mg_sql := IF(@mg_has_schedule = 1 AND @mg_has_platform_copy = 0,
  'ALTER TABLE design_content_schedule ADD COLUMN platform_copy_json JSON NULL',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_generation_context := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'design_content_schedule' AND COLUMN_NAME = 'generation_context_json'
);
SET @mg_sql := IF(@mg_has_schedule = 1 AND @mg_has_generation_context = 0,
  'ALTER TABLE design_content_schedule ADD COLUMN generation_context_json JSON NULL',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

CREATE TABLE IF NOT EXISTS merchant_advertising_assets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  catalog_product_id BIGINT UNSIGNED NULL,
  schedule_item_id BIGINT UNSIGNED NULL,
  catalog_asset_id BIGINT UNSIGNED NOT NULL,
  idempotency_key VARCHAR(128) NOT NULL,
  title VARCHAR(180) NOT NULL,
  asset_kind VARCHAR(24) NOT NULL,
  format_key VARCHAR(40) NOT NULL,
  layout_key VARCHAR(40) NOT NULL,
  caption_json JSON NULL,
  render_metadata_json JSON NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'active',
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  archived_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_advertising_assets_public_id (public_id),
  UNIQUE KEY uq_merchant_advertising_assets_idempotency (merchant_user_id, idempotency_key),
  KEY idx_merchant_advertising_assets_filters (merchant_user_id, status, format_key, created_at),
  KEY idx_merchant_advertising_assets_product (catalog_product_id, created_at),
  KEY idx_merchant_advertising_assets_schedule (schedule_item_id),
  CONSTRAINT fk_merchant_advertising_assets_workspace
    FOREIGN KEY (workspace_id) REFERENCES merchant_workspaces (id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_advertising_assets_merchant
    FOREIGN KEY (merchant_user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_advertising_assets_product
    FOREIGN KEY (catalog_product_id) REFERENCES catalog_products (id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_advertising_assets_schedule
    FOREIGN KEY (schedule_item_id) REFERENCES design_content_schedule (id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_advertising_assets_catalog_asset
    FOREIGN KEY (catalog_asset_id) REFERENCES catalog_assets (id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_advertising_assets_creator
    FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key, description, checksum, applied_at)
VALUES (
  '20260716_design_studio_advertising_workflow_v2',
  'Design Studio saved advertising assets, deterministic posting copy, and smarter calendar metadata',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description = VALUES(description);
