-- Stage 27 World Canvas Canonical Locations
-- Safe to re-run before import.
-- Uses the existing Stage 5A merchant_locations table; does not create a second merchant-location system.
-- Adds geospatial columns for World Canvas merchant zones and keeps dynamic user/avatar positions separate.

ALTER TABLE merchant_locations
  ADD COLUMN latitude DECIMAL(10,7) NULL AFTER phone,
  ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude,
  ADD COLUMN geo_accuracy_meters INT UNSIGNED NULL AFTER longitude,
  ADD COLUMN geo_source VARCHAR(80) NULL AFTER geo_accuracy_meters,
  ADD COLUMN world_zone_radius_meters INT UNSIGNED NOT NULL DEFAULT 250 AFTER geo_source;

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

CREATE INDEX idx_merchant_locations_world_geo ON merchant_locations(latitude, longitude);

INSERT IGNORE INTO schema_migrations (migration_key, description, applied_at)
VALUES ('stage_27_world_canvas_canonical_locations', 'World Canvas geospatial columns for merchant_locations and user world positions', NOW());
