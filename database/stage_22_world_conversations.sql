-- ------------------------------------------------------------
-- Stage 22 World Canvas Conversations
-- ------------------------------------------------------------
-- Purpose:
--   Turns World Canvas avatar clusters into temporary conversation spaces.
--   Conversations are keyed by cluster/location/campaign context and keep public
--   world chat separate from private Merchant Store Canvas CRM.
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
