-- Campaign Embed v4.7 — Persistent Test Tracking
-- Import this migration before using the database-backed placement test actions.

CREATE TABLE IF NOT EXISTS campaign_embed_placement_tests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(64) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NULL,
  campaign_public_id VARCHAR(64) NULL,
  campaign_slug VARCHAR(190) NULL,
  campaign_title VARCHAR(255) NULL,
  origin_host VARCHAR(190) NULL,
  page_url TEXT NULL,
  page_path VARCHAR(255) NULL,
  source VARCHAR(80) NULL,
  embed_mode VARCHAR(80) NULL,
  placement_label VARCHAR(255) NULL,
  next_test VARCHAR(255) NULL,
  status ENUM('planned','running','completed','paused') NOT NULL DEFAULT 'running',
  started_at DATETIME NULL,
  ended_at DATETIME NULL,
  paused_at DATETIME NULL,
  compared_at DATETIME NULL,
  notes TEXT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campaign_embed_placement_tests_public_id (public_id),
  KEY idx_campaign_embed_placement_tests_merchant_status (merchant_user_id, status, started_at),
  KEY idx_campaign_embed_placement_tests_campaign (merchant_user_id, campaign_id, status),
  KEY idx_campaign_embed_placement_tests_campaign_public (merchant_user_id, campaign_public_id, status),
  KEY idx_campaign_embed_placement_tests_origin (merchant_user_id, origin_host, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
