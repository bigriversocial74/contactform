-- Stage 27 World Canvas Canonical Locations
-- Safe to re-run before import.
-- Adds canonical location storage for static merchant MAIN locations and dynamic user/avatar positions.

CREATE TABLE IF NOT EXISTS merchant_locations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  location_name VARCHAR(180) NOT NULL DEFAULT 'Main Location',
  location_type ENUM('main','secondary','event','temporary') NOT NULL DEFAULT 'main',
  address_line1 VARCHAR(240) NULL,
  address_line2 VARCHAR(240) NULL,
  city VARCHAR(120) NULL,
  region VARCHAR(120) NULL,
  postal_code VARCHAR(40) NULL,
  country_code CHAR(2) NULL,
  main_latitude DECIMAL(10,7) NULL,
  main_longitude DECIMAL(10,7) NULL,
  geo_accuracy_meters INT UNSIGNED NULL,
  geo_source VARCHAR(80) NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('active','hidden','archived') NOT NULL DEFAULT 'active',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_locations_public_id (public_id),
  KEY idx_merchant_locations_primary (merchant_user_id, is_primary, location_type, status),
  KEY idx_merchant_locations_geo (main_latitude, main_longitude),
  KEY idx_merchant_locations_merchant_status (merchant_user_id, status, location_type),
  CONSTRAINT fk_merchant_locations_user FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

INSERT IGNORE INTO schema_migrations (migration_key, description, applied_at)
VALUES ('stage_27_world_canvas_canonical_locations', 'Canonical World Canvas merchant and user location storage', NOW());
