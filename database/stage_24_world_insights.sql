-- ------------------------------------------------------------
-- Stage 24 World Canvas Insights
-- ------------------------------------------------------------
-- Purpose:
--   Stores generated World Canvas insight snapshots for auditability, future
--   automation, and merchant recommendation history. The live API can generate
--   insights directly from sessions, conversations, reward drops, and claims.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS world_insight_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  insight_key_hash CHAR(64) NOT NULL,
  insight_key VARCHAR(220) NOT NULL,
  insight_type VARCHAR(80) NOT NULL DEFAULT 'world_signal',
  scope_type VARCHAR(60) NOT NULL DEFAULT 'global',
  scope_key VARCHAR(220) NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  conversation_id BIGINT UNSIGNED NULL,
  severity ENUM('info','opportunity','warning','critical') NOT NULL DEFAULT 'info',
  title VARCHAR(180) NOT NULL,
  summary TEXT NOT NULL,
  recommendation TEXT NULL,
  action_label VARCHAR(120) NULL,
  action_href VARCHAR(500) NULL,
  score INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('active','dismissed','converted','expired','archived') NOT NULL DEFAULT 'active',
  source_counts_json JSON NULL,
  metadata_json JSON NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_world_insight_snapshots_public_id (public_id),
  KEY idx_world_insight_snapshots_key (insight_key_hash, status),
  KEY idx_world_insight_snapshots_scope (scope_type, scope_key, status),
  KEY idx_world_insight_snapshots_merchant (merchant_user_id, status, generated_at),
  KEY idx_world_insight_snapshots_conversation (conversation_id, status),
  CONSTRAINT fk_world_insight_snapshots_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_world_insight_snapshots_conversation FOREIGN KEY (conversation_id) REFERENCES world_conversations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_24_world_insights','World Canvas insight snapshot table.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
