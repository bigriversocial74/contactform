-- Campaign Embed Settings v2
-- Adds per-campaign embed configuration and lightweight public embed event analytics.

CREATE TABLE IF NOT EXISTS campaign_embed_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  embed_enabled TINYINT(1) NOT NULL DEFAULT 1,
  default_layout VARCHAR(24) NOT NULL DEFAULT 'inline',
  custom_button_text VARCHAR(120) NULL,
  custom_success_message VARCHAR(255) NULL,
  allowed_domains_json TEXT NULL,
  settings_json TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campaign_embed_settings_campaign (campaign_id),
  KEY idx_campaign_embed_settings_merchant (merchant_user_id),
  KEY idx_campaign_embed_settings_enabled (embed_enabled),
  CONSTRAINT fk_campaign_embed_settings_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_embed_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  origin_host VARCHAR(255) NULL,
  page_url VARCHAR(700) NULL,
  embed_mode VARCHAR(24) NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  metadata_json TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campaign_embed_events_public (public_id),
  KEY idx_campaign_embed_events_campaign_created (campaign_id, created_at),
  KEY idx_campaign_embed_events_merchant_created (merchant_user_id, created_at),
  KEY idx_campaign_embed_events_type_created (event_type, created_at),
  KEY idx_campaign_embed_events_origin (origin_host),
  CONSTRAINT fk_campaign_embed_events_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
