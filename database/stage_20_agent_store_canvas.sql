-- ------------------------------------------------------------
-- Stage 20 Agent Store Canvas / IN-OUT Box Presence Foundation
-- ------------------------------------------------------------
-- Purpose:
--   Adds merchant/customer/store/campaign agent records, active store
--   sessions, session event logs, customer store history, and direct
--   merchant-to-customer canvas messages.
--
-- Notes:
--   This migration intentionally avoids hard foreign keys so it can be
--   imported safely into environments where older stage tables may have
--   been applied in a different order. Referential integrity is enforced
--   by the application layer and supporting indexes.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS mg_agents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  account_type ENUM('merchant','customer','campaign','store') NOT NULL,
  agent_type ENUM('merchant_agent','customer_agent','campaign_agent','store_agent') NOT NULL,
  display_name VARCHAR(160) NOT NULL,
  avatar_url VARCHAR(600) NULL,
  status ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'active',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_agents_public_id (public_id),
  KEY idx_mg_agents_owner (owner_user_id, account_type, status),
  KEY idx_mg_agents_merchant (merchant_user_id, agent_type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_store_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  customer_user_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  customer_agent_id BIGINT UNSIGNED NULL,
  merchant_agent_id BIGINT UNSIGNED NULL,
  store_agent_id BIGINT UNSIGNED NULL,
  source_feed_post_id BIGINT UNSIGNED NULL,
  source_campaign_id BIGINT UNSIGNED NULL,
  status ENUM('entered','active','idle','exited','expired','abandoned','blocked') NOT NULL DEFAULT 'entered',
  active_key BIGINT UNSIGNED NULL COMMENT 'Set to customer_user_id only while this session is active; unique index enforces one active store per customer.',
  entered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_active_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  exited_at DATETIME NULL,
  exit_reason ENUM('manual','switch_store','timeout','merchant_removed','blocked','system') NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_store_sessions_public_id (public_id),
  UNIQUE KEY uq_mg_store_sessions_one_active_customer (active_key),
  KEY idx_mg_store_sessions_customer_status (customer_user_id, status, last_active_at),
  KEY idx_mg_store_sessions_merchant_active (merchant_user_id, active_key, status, last_active_at),
  KEY idx_mg_store_sessions_source_post (source_feed_post_id),
  KEY idx_mg_store_sessions_campaign (source_campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_store_session_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  store_session_id BIGINT UNSIGNED NOT NULL,
  customer_user_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  event_label VARCHAR(180) NULL,
  event_data_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_store_session_events_public_id (public_id),
  KEY idx_mg_store_session_events_session (store_session_id, created_at),
  KEY idx_mg_store_session_events_customer (customer_user_id, created_at),
  KEY idx_mg_store_session_events_merchant (merchant_user_id, event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_customer_store_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  customer_user_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  store_session_id BIGINT UNSIGNED NOT NULL,
  source_feed_post_id BIGINT UNSIGNED NULL,
  summary VARCHAR(500) NULL,
  started_at DATETIME NOT NULL,
  ended_at DATETIME NULL,
  duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  messages_received_count INT UNSIGNED NOT NULL DEFAULT 0,
  rewards_received_count INT UNSIGNED NOT NULL DEFAULT 0,
  rewards_claimed_count INT UNSIGNED NOT NULL DEFAULT 0,
  products_viewed_count INT UNSIGNED NOT NULL DEFAULT 0,
  gifts_sent_count INT UNSIGNED NOT NULL DEFAULT 0,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_customer_store_history_public_id (public_id),
  UNIQUE KEY uq_mg_customer_store_history_session (store_session_id),
  KEY idx_mg_customer_store_history_customer (customer_user_id, started_at),
  KEY idx_mg_customer_store_history_merchant (merchant_user_id, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_agent_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  store_session_id BIGINT UNSIGNED NULL,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  recipient_user_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  sender_role ENUM('merchant','customer','agent','system') NOT NULL DEFAULT 'merchant',
  message_type ENUM('direct','reward','campaign_invite','system') NOT NULL DEFAULT 'direct',
  subject VARCHAR(180) NULL,
  body TEXT NOT NULL,
  status ENUM('sent','delivered','read','archived','blocked') NOT NULL DEFAULT 'sent',
  read_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mg_agent_messages_public_id (public_id),
  KEY idx_mg_agent_messages_recipient (recipient_user_id, status, created_at),
  KEY idx_mg_agent_messages_sender (sender_user_id, created_at),
  KEY idx_mg_agent_messages_merchant (merchant_user_id, created_at),
  KEY idx_mg_agent_messages_session (store_session_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_20_agent_store_canvas','Agent Store Canvas presence, session history, and merchant direct message foundation.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
