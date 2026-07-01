-- ------------------------------------------------------------
-- Microgifter Campaign Ads Manager Phase 1
-- Controlled campaign boosts + sponsored local drops.
--
-- Safe deployment notes:
-- - No foreign keys are used in this migration to avoid shared-host import
--   failures when legacy referenced tables are absent or use mixed engines.
-- - IDs are indexed so application-level ownership and review checks remain fast.
-- - No billing/payment tables are created in Phase 1.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ad_campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NULL,
  target_zone_id BIGINT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  objective VARCHAR(64) NOT NULL,
  status ENUM('draft','pending_review','approved','active','paused','rejected','completed','archived') NOT NULL DEFAULT 'draft',
  budget_type ENUM('none','flat_boost','claim_cap','redemption_cap','sponsored_reward_budget') NOT NULL DEFAULT 'none',
  budget_amount DECIMAL(10,2) NULL,
  claim_cap INT UNSIGNED NULL,
  redemption_cap INT UNSIGNED NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  approved_by_user_id BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ad_campaigns_public_id (public_id),
  KEY idx_ad_campaigns_merchant_status (merchant_id,status,updated_at),
  KEY idx_ad_campaigns_target_zone (target_zone_id),
  KEY idx_ad_campaigns_window (status,starts_at,ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_creatives (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  ad_campaign_id BIGINT UNSIGNED NOT NULL,
  headline VARCHAR(190) NOT NULL,
  description TEXT NULL,
  image_url TEXT NULL,
  cta_label VARCHAR(80) NOT NULL DEFAULT 'Claim Reward',
  destination_type VARCHAR(64) NULL,
  destination_id BIGINT UNSIGNED NULL,
  sponsored_label VARCHAR(60) NOT NULL DEFAULT 'Sponsored',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ad_creatives_public_id (public_id),
  KEY idx_ad_creatives_campaign (ad_campaign_id),
  KEY idx_ad_creatives_destination (destination_type,destination_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_placements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  placement_key VARCHAR(90) NOT NULL,
  placement_name VARCHAR(140) NOT NULL,
  surface VARCHAR(90) NOT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  max_ads INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ad_placements_key (placement_key),
  KEY idx_ad_placements_surface (surface,is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ad_placements (placement_key,placement_name,surface,description,is_active,max_ads,created_at,updated_at) VALUES
('feed_sponsored_card','Feed Sponsored Card','feed','Full sponsored campaign card inside the Microgifter social feed.',1,2,NOW(),NOW()),
('sidebar_sponsored_card','Sidebar Sponsored Card','sidebar','Compact sponsored campaign card in right/sidebar panels.',1,1,NOW(),NOW()),
('world_canvas_sponsored_pin','World Canvas Sponsored Pin','world_canvas','Sponsored local opportunity marker for map/canvas surfaces.',1,5,NOW(),NOW()),
('target_zone_sponsored_drop','Target Zone Sponsored Drop','target_zone','Sponsored local drop connected to a region, radius, or trigger zone.',1,5,NOW(),NOW()),
('wallet_recommendation','Wallet Recommendation','wallet','Future recommendation placement for wallet surfaces.',0,1,NOW(),NOW()),
('inbox_recommendation','Inbox Recommendation','inbox','Future recommendation placement for inbox surfaces.',0,1,NOW(),NOW()),
('claim_success_recommendation','Claim Success Recommendation','claim','Future post-claim promotional placement.',0,1,NOW(),NOW()),
('campaign_drops_map','Campaign Drops Map','campaign_drops','Future campaign drops map placement.',0,5,NOW(),NOW())
ON DUPLICATE KEY UPDATE
  placement_name=VALUES(placement_name),
  surface=VALUES(surface),
  description=VALUES(description),
  max_ads=VALUES(max_ads),
  updated_at=NOW();

CREATE TABLE IF NOT EXISTS ad_campaign_placements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ad_campaign_id BIGINT UNSIGNED NOT NULL,
  placement_key VARCHAR(90) NOT NULL,
  priority INT NOT NULL DEFAULT 100,
  status ENUM('active','paused','archived') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ad_campaign_placement (ad_campaign_id,placement_key),
  KEY idx_ad_campaign_placements_key_status (placement_key,status,priority),
  KEY idx_ad_campaign_placements_campaign (ad_campaign_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_targeting_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ad_campaign_id BIGINT UNSIGNED NOT NULL,
  rule_type VARCHAR(80) NOT NULL,
  rule_value_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ad_targeting_campaign (ad_campaign_id),
  KEY idx_ad_targeting_type (rule_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  ad_campaign_id BIGINT UNSIGNED NOT NULL,
  merchant_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(64) NOT NULL,
  surface VARCHAR(90) NULL,
  placement_key VARCHAR(90) NULL,
  campaign_id BIGINT UNSIGNED NULL,
  target_zone_id BIGINT UNSIGNED NULL,
  wallet_item_id BIGINT UNSIGNED NULL,
  claim_id BIGINT UNSIGNED NULL,
  redemption_id BIGINT UNSIGNED NULL,
  session_key VARCHAR(190) NULL,
  ip_hash VARCHAR(190) NULL,
  user_agent_hash VARCHAR(190) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ad_events_public_id (public_id),
  KEY idx_ad_events_campaign_type_time (ad_campaign_id,event_type,created_at),
  KEY idx_ad_events_merchant_time (merchant_id,created_at),
  KEY idx_ad_events_user_time (user_id,created_at),
  KEY idx_ad_events_placement_time (placement_key,created_at),
  KEY idx_ad_events_target_zone_time (target_zone_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ad_campaign_id BIGINT UNSIGNED NOT NULL,
  review_status ENUM('pending','approved','rejected','paused') NOT NULL DEFAULT 'pending',
  review_notes TEXT NULL,
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ad_reviews_campaign (ad_campaign_id,created_at),
  KEY idx_ad_reviews_status (review_status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
