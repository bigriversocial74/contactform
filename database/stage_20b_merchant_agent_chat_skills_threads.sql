-- Merchant Agent Chat Skills, Profiles, Threads, and Snapshots

CREATE TABLE IF NOT EXISTS merchant_agent_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  agent_name VARCHAR(80) NOT NULL DEFAULT 'Merchant Agent',
  agent_role VARCHAR(160) NOT NULL DEFAULT 'Merchant growth intelligence advisor',
  agent_tone VARCHAR(40) NOT NULL DEFAULT 'direct',
  soul_version VARCHAR(80) NOT NULL DEFAULT 'merchant-agent-soul-v1',
  profile_context_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_agent_profiles_public_id (public_id),
  UNIQUE KEY uq_merchant_agent_profiles_merchant (merchant_user_id),
  KEY idx_merchant_agent_profiles_merchant_updated (merchant_user_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_agent_threads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  agent_profile_id BIGINT UNSIGNED NULL,
  title VARCHAR(140) NOT NULL DEFAULT 'Current chat',
  status ENUM('active','saved','archived') NOT NULL DEFAULT 'active',
  summary TEXT NULL,
  metadata_json LONGTEXT NULL,
  saved_at DATETIME NULL,
  archived_at DATETIME NULL,
  cleared_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_agent_threads_public_id (public_id),
  KEY idx_merchant_agent_threads_merchant_status (merchant_user_id, status, updated_at),
  KEY idx_merchant_agent_threads_profile (agent_profile_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_agent_skill_registry (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  skill_key VARCHAR(80) NOT NULL,
  label VARCHAR(120) NOT NULL,
  description TEXT NOT NULL,
  default_enabled TINYINT(1) NOT NULL DEFAULT 1,
  block_types_json LONGTEXT NULL,
  status ENUM('active','disabled','experimental') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_agent_skill_registry_key (skill_key),
  KEY idx_merchant_agent_skill_registry_status (status, default_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_agent_insight_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  thread_public_id CHAR(36) NULL,
  skill_key VARCHAR(80) NOT NULL,
  snapshot_type VARCHAR(80) NOT NULL,
  title VARCHAR(180) NOT NULL,
  summary TEXT NULL,
  source_window_days INT UNSIGNED NOT NULL DEFAULT 90,
  insight_payload_json LONGTEXT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_agent_insight_snapshots_public_id (public_id),
  KEY idx_merchant_agent_insight_snapshots_merchant (merchant_user_id, created_at),
  KEY idx_merchant_agent_insight_snapshots_thread (thread_public_id),
  KEY idx_merchant_agent_insight_snapshots_skill (skill_key, snapshot_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO merchant_agent_skill_registry (skill_key,label,description,default_enabled,block_types_json,status,created_at,updated_at) VALUES
('merchant_analysis_charts','Analysis + charts','Analyze merchant products, campaigns, claims, redemptions, customer segments, and opportunities.',1,'["chart","metric_grid","forecast","product_opportunity","project"]','active',NOW(),NOW()),
('social_campaign_advisor','Social campaigns','Create social media campaign advice, channel-specific post drafts, CTA ideas, reward angles, and approval-ready campaign projects based on merchant data.',1,'["social_campaign","social_posts","project"]','active',NOW(),NOW());
