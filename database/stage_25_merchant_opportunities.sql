-- ------------------------------------------------------------
-- Stage 25 Merchant Opportunity Cards
-- ------------------------------------------------------------
-- Purpose:
--   Stores merchant-facing World Canvas opportunities generated from live
--   insights, conversations, reward drops, claim signals, and heat zones.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS world_merchant_opportunities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  opportunity_key_hash CHAR(64) NOT NULL,
  opportunity_key VARCHAR(240) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  conversation_id BIGINT UNSIGNED NULL,
  cluster_key VARCHAR(220) NULL,
  location_key VARCHAR(160) NULL,
  opportunity_type VARCHAR(80) NOT NULL DEFAULT 'world_opportunity',
  priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  title VARCHAR(180) NOT NULL,
  summary TEXT NOT NULL,
  recommended_action VARCHAR(180) NULL,
  action_type VARCHAR(80) NULL,
  action_label VARCHAR(120) NULL,
  action_href VARCHAR(500) NULL,
  score INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('open','viewed','converted','dismissed','expired') NOT NULL DEFAULT 'open',
  source_counts_json JSON NULL,
  metadata_json JSON NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_world_merchant_opportunities_public_id (public_id),
  UNIQUE KEY uq_world_merchant_opportunities_key (opportunity_key_hash),
  KEY idx_world_merchant_opportunities_merchant (merchant_user_id, status, priority, generated_at),
  KEY idx_world_merchant_opportunities_cluster (cluster_key, status),
  KEY idx_world_merchant_opportunities_conversation (conversation_id, status),
  CONSTRAINT fk_world_merchant_opportunities_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_world_merchant_opportunities_conversation FOREIGN KEY (conversation_id) REFERENCES world_conversations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_25_merchant_opportunities','World Canvas merchant opportunity cards table.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
