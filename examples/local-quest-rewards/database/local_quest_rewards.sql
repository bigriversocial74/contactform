-- Local Quest Rewards demo app schema
-- Run this in the database used by the third-party Quest app, not the Microgifter platform database.

CREATE TABLE IF NOT EXISTS lqr_admin_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(64) NOT NULL,
  username VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(180) DEFAULT NULL,
  role_key VARCHAR(60) NOT NULL DEFAULT 'admin',
  status VARCHAR(40) NOT NULL DEFAULT 'active',
  last_login_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lqr_admin_public_id (public_id),
  UNIQUE KEY uq_lqr_admin_username (username),
  KEY idx_lqr_admin_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lqr_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(64) NOT NULL,
  display_name VARCHAR(180) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  external_user_id VARCHAR(96) NOT NULL,
  linked_account_id VARCHAR(96) DEFAULT NULL,
  link_status VARCHAR(40) NOT NULL DEFAULT 'not_linked',
  linked_at DATETIME DEFAULT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'active',
  admin_notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lqr_users_public_id (public_id),
  UNIQUE KEY uq_lqr_users_email (email),
  UNIQUE KEY uq_lqr_users_external_user (external_user_id),
  KEY idx_lqr_users_linked_account (linked_account_id),
  KEY idx_lqr_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lqr_link_states (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  state_token VARCHAR(96) NOT NULL,
  user_public_id VARCHAR(64) NOT NULL,
  external_user_id VARCHAR(96) NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lqr_link_states_token (state_token),
  KEY idx_lqr_link_states_user (user_public_id),
  KEY idx_lqr_link_states_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lqr_quests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  quest_key VARCHAR(96) NOT NULL,
  title VARCHAR(180) NOT NULL,
  merchant VARCHAR(180) DEFAULT NULL,
  sponsor VARCHAR(180) DEFAULT NULL,
  location VARCHAR(180) DEFAULT NULL,
  description TEXT,
  event_type VARCHAR(80) NOT NULL DEFAULT 'quest.completed',
  program_id VARCHAR(96) DEFAULT NULL,
  template_id VARCHAR(96) DEFAULT NULL,
  reward_label VARCHAR(180) NOT NULL,
  difficulty VARCHAR(60) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  visibility VARCHAR(40) NOT NULL DEFAULT 'public',
  starts_at DATETIME DEFAULT NULL,
  ends_at DATETIME DEFAULT NULL,
  max_total_completions INT UNSIGNED NOT NULL DEFAULT 0,
  max_total_rewards INT UNSIGNED NOT NULL DEFAULT 0,
  requires_signed_code TINYINT(1) NOT NULL DEFAULT 0,
  signed_code_type VARCHAR(80) NOT NULL DEFAULT 'quest_checkin',
  permission_json JSON DEFAULT NULL,
  controls_json JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lqr_quests_key (quest_key),
  KEY idx_lqr_quests_active (is_active),
  KEY idx_lqr_quests_schedule (starts_at, ends_at),
  KEY idx_lqr_quests_sponsor (sponsor),
  KEY idx_lqr_quests_visibility (visibility),
  KEY idx_lqr_quests_signed (requires_signed_code, signed_code_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lqr_quest_completions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_public_id VARCHAR(64) NOT NULL,
  quest_key VARCHAR(96) NOT NULL,
  completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  metadata_json JSON DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lqr_completion_user_quest (user_public_id, quest_key),
  KEY idx_lqr_completion_quest (quest_key),
  KEY idx_lqr_completion_completed (completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lqr_rewards (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_public_id VARCHAR(64) NOT NULL,
  quest_key VARCHAR(96) NOT NULL,
  reward_id VARCHAR(96) NOT NULL,
  external_event_id VARCHAR(180) NOT NULL,
  status VARCHAR(80) NOT NULL DEFAULT 'unknown',
  item_id VARCHAR(96) DEFAULT NULL,
  item_status VARCHAR(80) DEFAULT NULL,
  claim_status VARCHAR(80) NOT NULL DEFAULT 'available_in_app',
  claim_report_status VARCHAR(80) NOT NULL DEFAULT 'not_reported',
  microgifter_event_id VARCHAR(96) DEFAULT NULL,
  response_json JSON DEFAULT NULL,
  status_response_json JSON DEFAULT NULL,
  claim_report_response_json JSON DEFAULT NULL,
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_checked_at DATETIME DEFAULT NULL,
  claimed_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lqr_rewards_user_quest (user_public_id, quest_key),
  UNIQUE KEY uq_lqr_rewards_reward_id (reward_id),
  KEY idx_lqr_rewards_user (user_public_id),
  KEY idx_lqr_rewards_status (status),
  KEY idx_lqr_rewards_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lqr_reward_claims (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(96) NOT NULL,
  user_public_id VARCHAR(64) NOT NULL,
  quest_key VARCHAR(96) NOT NULL,
  reward_id VARCHAR(96) NOT NULL,
  item_id VARCHAR(96) DEFAULT NULL,
  external_claim_id VARCHAR(180) NOT NULL,
  claim_action VARCHAR(80) NOT NULL,
  claim_status VARCHAR(80) NOT NULL,
  report_status VARCHAR(80) NOT NULL,
  microgifter_event_id VARCHAR(96) DEFAULT NULL,
  request_json JSON DEFAULT NULL,
  response_json JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lqr_claims_public_id (public_id),
  UNIQUE KEY uq_lqr_claims_external (external_claim_id),
  KEY idx_lqr_claims_reward (reward_id),
  KEY idx_lqr_claims_user (user_public_id),
  KEY idx_lqr_claims_report_status (report_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lqr_signed_code_replays (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  replay_key VARCHAR(128) NOT NULL,
  quest_key VARCHAR(96) DEFAULT NULL,
  code_type VARCHAR(80) DEFAULT NULL,
  nonce VARCHAR(128) DEFAULT NULL,
  payload_json JSON DEFAULT NULL,
  first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lqr_signed_replay_key (replay_key),
  KEY idx_lqr_signed_replay_quest (quest_key),
  KEY idx_lqr_signed_replay_seen (first_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lqr_webhook_deliveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  delivery_id VARCHAR(128) NOT NULL,
  event_type VARCHAR(120) NOT NULL,
  verified TINYINT(1) NOT NULL DEFAULT 0,
  reconciled TINYINT(1) NOT NULL DEFAULT 0,
  reward_id VARCHAR(96) DEFAULT NULL,
  item_id VARCHAR(96) DEFAULT NULL,
  payload_json JSON DEFAULT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lqr_webhook_delivery (delivery_id),
  KEY idx_lqr_webhook_event (event_type),
  KEY idx_lqr_webhook_reward (reward_id),
  KEY idx_lqr_webhook_received (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lqr_admin_audit_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_username VARCHAR(120) DEFAULT NULL,
  action_key VARCHAR(120) NOT NULL,
  target_type VARCHAR(80) DEFAULT NULL,
  target_id VARCHAR(120) DEFAULT NULL,
  context_json JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_lqr_admin_audit_action (action_key),
  KEY idx_lqr_admin_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lqr_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_type VARCHAR(100) NOT NULL,
  message TEXT NOT NULL,
  context_json JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_lqr_events_type (event_type),
  KEY idx_lqr_events_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lqr_app_state (
  state_key VARCHAR(80) NOT NULL,
  state_json JSON DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (state_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
