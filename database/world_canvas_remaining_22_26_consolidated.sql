-- ------------------------------------------------------------
-- World Canvas Remaining SQL Consolidated
-- ------------------------------------------------------------
-- Import this file if Stage 21 has already been imported.
--
-- Includes:
--   Stage 22: World Canvas Conversations
--   Stage 23: World Canvas Reward Drops
--   Stage 24: World Canvas Insights
--   Stage 25: Merchant Opportunity Cards
--   Stage 26: World Canvas Movement Timeline
--
-- Does NOT include:
--   Stage 21: Avatar latitude/longitude columns on mg_store_sessions
-- ------------------------------------------------------------

-- ------------------------------------------------------------
-- Stage 22 World Canvas Conversations
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS world_conversations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  cluster_key_hash CHAR(64) NOT NULL,
  cluster_key VARCHAR(220) NOT NULL,
  title VARCHAR(180) NOT NULL,
  conversation_type VARCHAR(60) NOT NULL DEFAULT 'cluster',
  location_key VARCHAR(160) NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  campaign_public_id VARCHAR(80) NULL,
  reward_public_id VARCHAR(80) NULL,
  participant_count INT UNSIGNED NOT NULL DEFAULT 0,
  message_count INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('active','quiet','expired','closed','flagged') NOT NULL DEFAULT 'active',
  last_message_at DATETIME NULL,
  expires_at DATETIME NULL,
  metadata_json JSON NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_world_conversations_public_id (public_id),
  UNIQUE KEY uq_world_conversations_cluster_hash (cluster_key_hash),
  KEY idx_world_conversations_status_updated (status, updated_at),
  KEY idx_world_conversations_location (location_key, status),
  KEY idx_world_conversations_merchant (merchant_user_id, status),
  CONSTRAINT fk_world_conversations_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_world_conversations_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS world_conversation_members (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  store_session_id BIGINT UNSIGNED NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  member_public_id VARCHAR(80) NULL,
  identity_mode VARCHAR(60) NOT NULL DEFAULT 'anonymous',
  display_label VARCHAR(120) NOT NULL DEFAULT 'Anonymous avatar',
  role ENUM('avatar','merchant','system','admin') NOT NULL DEFAULT 'avatar',
  status ENUM('active','left','muted','blocked') NOT NULL DEFAULT 'active',
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NULL,
  metadata_json JSON NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_world_conversation_member_session (conversation_id, store_session_id),
  KEY idx_world_conversation_members_user (user_id, status),
  KEY idx_world_conversation_members_conversation (conversation_id, status),
  CONSTRAINT fk_world_conversation_members_conversation FOREIGN KEY (conversation_id) REFERENCES world_conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_world_conversation_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_world_conversation_members_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS world_conversation_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  conversation_id BIGINT UNSIGNED NOT NULL,
  member_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  store_session_id BIGINT UNSIGNED NULL,
  identity_mode VARCHAR(60) NOT NULL DEFAULT 'anonymous',
  sender_label VARCHAR(120) NOT NULL DEFAULT 'Anonymous avatar',
  message_body TEXT NOT NULL,
  message_type VARCHAR(40) NOT NULL DEFAULT 'text',
  status ENUM('visible','hidden','flagged','deleted') NOT NULL DEFAULT 'visible',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_world_conversation_messages_public_id (public_id),
  KEY idx_world_conversation_messages_conversation (conversation_id, status, created_at),
  KEY idx_world_conversation_messages_user (user_id, created_at),
  CONSTRAINT fk_world_conversation_messages_conversation FOREIGN KEY (conversation_id) REFERENCES world_conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_world_conversation_messages_member FOREIGN KEY (member_id) REFERENCES world_conversation_members(id) ON DELETE SET NULL,
  CONSTRAINT fk_world_conversation_messages_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_22_world_conversations','World Canvas avatar conversation engine tables.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- ------------------------------------------------------------
-- Stage 23 World Canvas Reward Drops
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

-- ------------------------------------------------------------
-- Stage 24 World Canvas Insights
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

-- ------------------------------------------------------------
-- Stage 25 Merchant Opportunity Cards
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

-- ------------------------------------------------------------
-- Stage 26 World Canvas Movement Timeline
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
