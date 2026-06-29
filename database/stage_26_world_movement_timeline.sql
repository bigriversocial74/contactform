-- ------------------------------------------------------------
-- Stage 26 World Canvas Movement Timeline
-- ------------------------------------------------------------
-- Purpose:
--   Stores optional replay snapshots for World Canvas movement. The live timeline
--   API can also synthesize movement directly from Store Canvas events,
--   conversations, reward drops, and claims.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS world_movement_timeline (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  movement_type VARCHAR(80) NOT NULL DEFAULT 'world_signal',
  scope_type VARCHAR(60) NOT NULL DEFAULT 'global',
  scope_key VARCHAR(220) NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  conversation_id BIGINT UNSIGNED NULL,
  source_public_id VARCHAR(120) NULL,
  source_table VARCHAR(120) NULL,
  title VARCHAR(180) NOT NULL,
  summary TEXT NULL,
  from_label VARCHAR(120) NULL,
  to_label VARCHAR(120) NULL,
  from_x DECIMAL(8,3) NULL,
  from_y DECIMAL(8,3) NULL,
  to_x DECIMAL(8,3) NULL,
  to_y DECIMAL(8,3) NULL,
  intensity INT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('active','hidden','archived') NOT NULL DEFAULT 'active',
  metadata_json JSON NULL,
  occurred_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_world_movement_timeline_public_id (public_id),
  KEY idx_world_movement_timeline_occurred (occurred_at, status),
  KEY idx_world_movement_timeline_scope (scope_type, scope_key, status, occurred_at),
  KEY idx_world_movement_timeline_merchant (merchant_user_id, status, occurred_at),
  KEY idx_world_movement_timeline_conversation (conversation_id, status, occurred_at),
  CONSTRAINT fk_world_movement_timeline_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_world_movement_timeline_conversation FOREIGN KEY (conversation_id) REFERENCES world_conversations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_26_world_movement_timeline','World Canvas movement timeline replay table.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
