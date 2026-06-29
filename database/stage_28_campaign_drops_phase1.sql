-- Stage 28 Campaign Drops / Target Zones Phase 1
-- Safe to re-run.
-- Adds one table for multiple scheduled map-based promotional drops per merchant.

CREATE TABLE IF NOT EXISTS merchant_target_drops (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(64) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  merchant_location_id BIGINT UNSIGNED NULL,
  campaign_id BIGINT UNSIGNED NULL,
  campaign_public_id VARCHAR(64) NULL,
  campaign_title VARCHAR(180) NULL,
  drop_name VARCHAR(180) NOT NULL DEFAULT 'Untitled Target Drop',
  payload_type ENUM('gift','reward','audio_pack','contest','offer','announcement') NOT NULL DEFAULT 'reward',
  status ENUM('draft','scheduled','launching','active','paused','completed','expired','cancelled') NOT NULL DEFAULT 'draft',
  visibility ENUM('public','private','invite_only','audience') NOT NULL DEFAULT 'public',
  launch_latitude DECIMAL(10,7) NULL,
  launch_longitude DECIMAL(10,7) NULL,
  target_latitude DECIMAL(10,7) NOT NULL,
  target_longitude DECIMAL(10,7) NOT NULL,
  radius_meters INT UNSIGNED NOT NULL DEFAULT 2500,
  launch_at DATETIME NULL,
  expires_at DATETIME NULL,
  timezone VARCHAR(64) NULL,
  quantity_limit INT UNSIGNED NULL,
  claim_limit_per_user INT UNSIGNED NOT NULL DEFAULT 1,
  teaser_enabled TINYINT(1) NOT NULL DEFAULT 1,
  signup_required TINYINT(1) NOT NULL DEFAULT 1,
  animation_type VARCHAR(40) NOT NULL DEFAULT 'gift_arc',
  metadata_json JSON NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_target_drops_public_id (public_id),
  KEY idx_merchant_target_drops_merchant_status (merchant_user_id, status, launch_at),
  KEY idx_merchant_target_drops_visibility_status (visibility, status, launch_at, expires_at),
  KEY idx_merchant_target_drops_target_geo (target_latitude, target_longitude),
  KEY idx_merchant_target_drops_location (merchant_location_id),
  CONSTRAINT fk_merchant_target_drops_merchant_user FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_target_drops_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_target_drops_location FOREIGN KEY (merchant_location_id) REFERENCES merchant_locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key, description, checksum, applied_at)
VALUES ('stage_28_campaign_drops_phase1', 'Campaign Drops / Target Zones Phase 1', NULL, NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description);
