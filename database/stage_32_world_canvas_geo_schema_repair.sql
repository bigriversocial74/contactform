-- Stage 32 World Canvas Geo Schema Repair
-- Safe to re-run.
-- Repairs deployments where Stage 27 was imported but merchant World geo fields were still reported missing.
-- This migration intentionally avoids AFTER clauses so it works against older merchant_locations variants.

SET @mg_has_merchant_locations := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations'
);

SET @mg_has_location_latitude := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations' AND COLUMN_NAME = 'latitude'
);
SET @mg_sql := IF(@mg_has_merchant_locations = 1 AND @mg_has_location_latitude = 0,
  'ALTER TABLE merchant_locations ADD COLUMN latitude DECIMAL(10,7) NULL',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_location_longitude := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations' AND COLUMN_NAME = 'longitude'
);
SET @mg_sql := IF(@mg_has_merchant_locations = 1 AND @mg_has_location_longitude = 0,
  'ALTER TABLE merchant_locations ADD COLUMN longitude DECIMAL(10,7) NULL',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_location_accuracy := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations' AND COLUMN_NAME = 'geo_accuracy_meters'
);
SET @mg_sql := IF(@mg_has_merchant_locations = 1 AND @mg_has_location_accuracy = 0,
  'ALTER TABLE merchant_locations ADD COLUMN geo_accuracy_meters INT UNSIGNED NULL',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_location_geo_source := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations' AND COLUMN_NAME = 'geo_source'
);
SET @mg_sql := IF(@mg_has_merchant_locations = 1 AND @mg_has_location_geo_source = 0,
  'ALTER TABLE merchant_locations ADD COLUMN geo_source VARCHAR(80) NULL',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_location_zone := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations' AND COLUMN_NAME = 'world_zone_radius_meters'
);
SET @mg_sql := IF(@mg_has_merchant_locations = 1 AND @mg_has_location_zone = 0,
  'ALTER TABLE merchant_locations ADD COLUMN world_zone_radius_meters INT UNSIGNED NOT NULL DEFAULT 250',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

CREATE TABLE IF NOT EXISTS user_world_positions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  accuracy_meters INT UNSIGNED NULL,
  geo_source VARCHAR(80) NOT NULL DEFAULT 'user_saved',
  position_context ENUM('manual','browser','ip','store_session','admin') NOT NULL DEFAULT 'manual',
  is_current TINYINT(1) NOT NULL DEFAULT 1,
  expires_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_world_positions_public_id (public_id),
  KEY idx_user_world_positions_geo (latitude, longitude),
  KEY idx_user_world_positions_user_current (user_id, is_current, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @mg_has_location_geo_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations' AND INDEX_NAME = 'idx_merchant_locations_world_geo'
);
SET @mg_sql := IF(@mg_has_merchant_locations = 1 AND @mg_has_location_geo_index = 0,
  'CREATE INDEX idx_merchant_locations_world_geo ON merchant_locations(latitude, longitude)',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

INSERT INTO schema_migrations (migration_key, description, checksum, applied_at)
VALUES ('stage_32_world_canvas_geo_schema_repair', 'World Canvas merchant geo schema repair without AFTER-clause assumptions', NULL, NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description);
