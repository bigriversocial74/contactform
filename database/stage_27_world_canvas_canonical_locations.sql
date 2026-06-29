-- Stage 27 World Canvas Canonical Locations
-- Safe to re-run.
-- Uses the existing Stage 5A merchant_locations table; does not create a second merchant-location system.
-- Adds geospatial columns for World Canvas merchant zones and keeps dynamic user/avatar positions separate.

SET @mg_has_merchant_locations := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations'
);

SET @mg_has_location_latitude := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations' AND COLUMN_NAME = 'latitude'
);
SET @mg_sql := IF(@mg_has_merchant_locations = 1 AND @mg_has_location_latitude = 0,
  'ALTER TABLE merchant_locations ADD COLUMN latitude DECIMAL(10,7) NULL AFTER phone',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_location_longitude := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations' AND COLUMN_NAME = 'longitude'
);
SET @mg_sql := IF(@mg_has_merchant_locations = 1 AND @mg_has_location_longitude = 0,
  'ALTER TABLE merchant_locations ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_location_accuracy := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations' AND COLUMN_NAME = 'geo_accuracy_meters'
);
SET @mg_sql := IF(@mg_has_merchant_locations = 1 AND @mg_has_location_accuracy = 0,
  'ALTER TABLE merchant_locations ADD COLUMN geo_accuracy_meters INT UNSIGNED NULL AFTER longitude',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_location_geo_source := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations' AND COLUMN_NAME = 'geo_source'
);
SET @mg_sql := IF(@mg_has_merchant_locations = 1 AND @mg_has_location_geo_source = 0,
  'ALTER TABLE merchant_locations ADD COLUMN geo_source VARCHAR(80) NULL AFTER geo_accuracy_meters',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_location_zone := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_locations' AND COLUMN_NAME = 'world_zone_radius_meters'
);
SET @mg_sql := IF(@mg_has_merchant_locations = 1 AND @mg_has_location_zone = 0,
  'ALTER TABLE merchant_locations ADD COLUMN world_zone_radius_meters INT UNSIGNED NOT NULL DEFAULT 250 AFTER geo_source',
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
  KEY idx_user_world_positions_user_current (user_id, is_current, updated_at),
  CONSTRAINT fk_user_world_positions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
VALUES ('stage_27_world_canvas_canonical_locations', 'World Canvas geospatial columns for merchant_locations and user world positions', NULL, NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description);
