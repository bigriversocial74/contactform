-- Stage 28 World Canvas Campaign Drops
-- Safe to re-run.
-- Adds multiple scheduled/active campaign drops for map-based promotional distribution.

CREATE TABLE IF NOT EXISTS merchant_target_drops (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  merchant_location_id BIGINT UNSIGNED NULL,
  campaign_id BIGINT UNSIGNED NULL,
  drop_name VARCHAR(180) NOT NULL DEFAULT 'Campaign Drop',
  drop_type ENUM('reward','gift','audio_pack','contest','offer','campaign') NOT NULL DEFAULT 'campaign',
  target_latitude DECIMAL(10,7) NOT NULL,
  target_longitude DECIMAL(10,7) NOT NULL,
  radius_meters INT UNSIGNED NOT NULL DEFAULT 1000,
  status ENUM('draft','scheduled','launching','active','paused','completed','expired','cancelled') NOT NULL DEFAULT 'draft',
  visibility ENUM('private','teaser','public','invite_only') NOT NULL DEFAULT 'private',
  launch_at DATETIME NULL,
  expires_at DATETIME NULL,
  teaser_enabled TINYINT(1) NOT NULL DEFAULT 1,
  quantity_limit INT UNSIGNED NULL,
  claim_limit_per_user INT UNSIGNED NOT NULL DEFAULT 1,
  signup_required TINYINT(1) NOT NULL DEFAULT 1,
  animation_type VARCHAR(60) NOT NULL DEFAULT 'gift_arc',
  metadata_json JSON NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_target_drops_public_id (public_id),
  KEY idx_merchant_target_drops_merchant_status (merchant_user_id, status, launch_at),
  KEY idx_merchant_target_drops_public_feed (visibility, status, launch_at, expires_at),
  KEY idx_merchant_target_drops_geo (target_latitude, target_longitude),
  KEY idx_merchant_target_drops_location (merchant_location_id),
  KEY idx_merchant_target_drops_campaign (campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key, description, checksum, applied_at)
VALUES ('stage_28_world_canvas_campaign_drops', 'World Canvas campaign drops and target zones', NULL, NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description);
