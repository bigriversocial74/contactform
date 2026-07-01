-- ------------------------------------------------------------
-- Microgifter Feed Stories
-- 24-hour image/video stories for feed.php.
--
-- Safe deployment notes:
-- - No foreign keys are used so this migration imports safely on shared hosts
--   when optional campaign/catalog tables are absent or use mixed engines.
-- - Product and campaign ownership is enforced in application code.
-- - Rewards are intentionally excluded; rewards are distributed through campaigns.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS microgifter_stories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  linked_type ENUM('none','product','campaign') NOT NULL DEFAULT 'none',
  linked_product_id BIGINT UNSIGNED NULL,
  linked_campaign_id BIGINT UNSIGNED NULL,
  story_type ENUM('user','merchant','product','campaign','sponsored','system') NOT NULL DEFAULT 'user',
  media_asset_id BIGINT UNSIGNED NULL,
  media_type ENUM('image','video') NOT NULL,
  media_url VARCHAR(700) NOT NULL,
  thumbnail_url VARCHAR(700) NULL,
  caption VARCHAR(280) NULL,
  cta_label VARCHAR(80) NULL,
  cta_url VARCHAR(700) NULL,
  status ENUM('active','expired','deleted','blocked') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgifter_stories_public_id (public_id),
  KEY idx_microgifter_stories_active_expires (status,expires_at,created_at),
  KEY idx_microgifter_stories_owner (owner_user_id,status,created_at),
  KEY idx_microgifter_stories_merchant (merchant_user_id,status,created_at),
  KEY idx_microgifter_stories_linked_product (linked_product_id),
  KEY idx_microgifter_stories_linked_campaign (linked_campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS microgifter_story_views (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  story_id BIGINT UNSIGNED NOT NULL,
  viewer_user_id BIGINT UNSIGNED NULL,
  viewer_session_id VARCHAR(128) NULL,
  viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  view_duration_seconds INT UNSIGNED NULL,
  completed TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgifter_story_views_user (story_id,viewer_user_id),
  UNIQUE KEY uq_microgifter_story_views_session (story_id,viewer_session_id),
  KEY idx_microgifter_story_views_story_time (story_id,viewed_at),
  KEY idx_microgifter_story_views_user_time (viewer_user_id,viewed_at),
  KEY idx_microgifter_story_views_session_time (viewer_session_id,viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
