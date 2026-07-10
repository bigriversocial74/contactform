-- Saved Loyalty Cards v1
-- Stores customer-saved Stamp Card / Loyalty campaign cards.

CREATE TABLE IF NOT EXISTS customer_saved_campaign_cards (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('saved','archived') NOT NULL DEFAULT 'saved',
  metadata_json JSON NULL,
  saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customer_saved_campaign_cards_public_id (public_id),
  UNIQUE KEY uq_customer_saved_campaign_cards_user_campaign (user_id,campaign_id),
  KEY idx_customer_saved_campaign_cards_user_status_updated (user_id,status,updated_at),
  KEY idx_customer_saved_campaign_cards_campaign_status (campaign_id,status),
  KEY idx_customer_saved_campaign_cards_merchant_status (merchant_user_id,status,updated_at),
  CONSTRAINT fk_customer_saved_cards_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_customer_saved_cards_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_customer_saved_cards_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
