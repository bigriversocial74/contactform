-- Merchant CRM Media Segments v1
-- Stores dynamic saved segment definitions for Watch/Listen media campaign follow-up.

CREATE TABLE IF NOT EXISTS merchant_crm_segments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NULL,
  segment_scope ENUM('media','general') NOT NULL DEFAULT 'media',
  name VARCHAR(140) NOT NULL,
  description VARCHAR(500) NULL,
  rules_json JSON NOT NULL,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  last_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_refreshed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_crm_segments_public_id (public_id),
  UNIQUE KEY uq_merchant_crm_segments_name (merchant_user_id, name),
  KEY idx_merchant_crm_segments_scope (merchant_user_id, segment_scope, status),
  KEY idx_merchant_crm_segments_campaign (campaign_id),
  CONSTRAINT fk_merchant_crm_segments_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
