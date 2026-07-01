-- ------------------------------------------------------------
-- Stage 23 World Canvas Reward Drops
-- ------------------------------------------------------------
-- Purpose:
--   Lets merchants create controlled reward drops for World Canvas clusters,
--   heat zones, and conversation spaces. Claims are tracked separately so the
--   drop layer can later connect into wallet/campaign inventory.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS world_reward_drops (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  conversation_id BIGINT UNSIGNED NULL,
  cluster_key_hash CHAR(64) NOT NULL,
  cluster_key VARCHAR(220) NOT NULL,
  location_key VARCHAR(160) NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  reward_label VARCHAR(180) NOT NULL,
  reward_description TEXT NULL,
  quantity_total INT UNSIGNED NOT NULL DEFAULT 1,
  quantity_remaining INT UNSIGNED NOT NULL DEFAULT 1,
  claim_limit_per_user INT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('draft','active','paused','exhausted','expired','closed','flagged') NOT NULL DEFAULT 'active',
  starts_at DATETIME NULL,
  expires_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_world_reward_drops_public_id (public_id),
  KEY idx_world_reward_drops_cluster (cluster_key_hash, status),
  KEY idx_world_reward_drops_conversation (conversation_id, status),
  KEY idx_world_reward_drops_merchant (merchant_user_id, status),
  KEY idx_world_reward_drops_location (location_key, status),
  CONSTRAINT fk_world_reward_drops_conversation FOREIGN KEY (conversation_id) REFERENCES world_conversations(id) ON DELETE SET NULL,
  CONSTRAINT fk_world_reward_drops_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_world_reward_drops_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS world_reward_drop_claims (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  reward_drop_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  store_session_id BIGINT UNSIGNED NULL,
  claim_code VARCHAR(40) NOT NULL,
  status ENUM('claimed','redeemed','cancelled','expired') NOT NULL DEFAULT 'claimed',
  claimed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  redeemed_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_world_reward_drop_claims_public_id (public_id),
  UNIQUE KEY uq_world_reward_drop_claims_code (claim_code),
  UNIQUE KEY uq_world_reward_drop_claims_user_drop (reward_drop_id, user_id),
  KEY idx_world_reward_drop_claims_user (user_id, status),
  KEY idx_world_reward_drop_claims_drop (reward_drop_id, status),
  CONSTRAINT fk_world_reward_drop_claims_drop FOREIGN KEY (reward_drop_id) REFERENCES world_reward_drops(id) ON DELETE CASCADE,
  CONSTRAINT fk_world_reward_drop_claims_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_23_world_reward_drops','World Canvas reward drop and claim tables.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
