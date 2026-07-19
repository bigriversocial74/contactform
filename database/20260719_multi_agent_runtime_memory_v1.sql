-- Microgifter Multi-Agent Runtime & Memory v1.
-- Import after stage_3_agent_persistence.sql and 20260714_personal_gifting_agent_phase2.sql.
-- Safe to rerun after a successful import.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS multi_agent_threads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  agent_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL DEFAULT 'New agent conversation',
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  last_message_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_multi_agent_threads_public_id (public_id),
  KEY idx_multi_agent_threads_agent_recent (agent_id,status,last_message_at,updated_at),
  KEY idx_multi_agent_threads_owner (owner_user_id,updated_at),
  CONSTRAINT fk_multi_agent_threads_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
  CONSTRAINT fk_multi_agent_threads_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS multi_agent_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  thread_id BIGINT UNSIGNED NOT NULL,
  agent_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('user','assistant','system','tool') NOT NULL,
  body TEXT NOT NULL,
  cards_json JSON NULL,
  context_json JSON NULL,
  model_key VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_multi_agent_messages_public_id (public_id),
  KEY idx_multi_agent_messages_thread (thread_id,id),
  KEY idx_multi_agent_messages_agent (agent_id,created_at),
  KEY idx_multi_agent_messages_owner (owner_user_id,created_at),
  CONSTRAINT fk_multi_agent_messages_thread FOREIGN KEY (thread_id) REFERENCES multi_agent_threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_multi_agent_messages_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
  CONSTRAINT fk_multi_agent_messages_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS multi_agent_memory (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  agent_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  memory_key VARCHAR(160) NOT NULL,
  category VARCHAR(64) NOT NULL DEFAULT 'preference',
  title VARCHAR(190) NOT NULL,
  value_json JSON NOT NULL,
  source ENUM('user','agent','onboarding','tool') NOT NULL DEFAULT 'user',
  confidence DECIMAL(4,3) NOT NULL DEFAULT 1.000,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_multi_agent_memory_public_id (public_id),
  UNIQUE KEY uq_multi_agent_memory_agent_key (agent_id,memory_key),
  KEY idx_multi_agent_memory_owner_agent (owner_user_id,agent_id,status,updated_at),
  CONSTRAINT fk_multi_agent_memory_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
  CONSTRAINT fk_multi_agent_memory_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS multi_agent_onboarding (
  agent_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started',
  current_step VARCHAR(64) NULL,
  answers_json JSON NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (agent_id),
  KEY idx_multi_agent_onboarding_owner (owner_user_id,status,updated_at),
  CONSTRAINT fk_multi_agent_onboarding_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
  CONSTRAINT fk_multi_agent_onboarding_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS multi_agent_drafts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  agent_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  thread_id BIGINT UNSIGNED NULL,
  draft_type VARCHAR(64) NOT NULL DEFAULT 'plan',
  title VARCHAR(190) NOT NULL,
  payload_json JSON NOT NULL,
  status ENUM('draft','approved','dismissed','completed') NOT NULL DEFAULT 'draft',
  approval_required TINYINT(1) NOT NULL DEFAULT 1,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_multi_agent_drafts_public_id (public_id),
  KEY idx_multi_agent_drafts_agent_status (agent_id,status,updated_at),
  KEY idx_multi_agent_drafts_owner (owner_user_id,status,updated_at),
  CONSTRAINT fk_multi_agent_drafts_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
  CONSTRAINT fk_multi_agent_drafts_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_multi_agent_drafts_thread FOREIGN KEY (thread_id) REFERENCES multi_agent_threads(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('agent.specialized.use','Use specialized agents','Use installed specialized agents with isolated conversations, memory, onboarding, and approval-first drafts.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='agent.specialized.use'
WHERE r.slug IN ('customer','member','merchant','creator','admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('20260719_multi_agent_runtime_memory_v1','Agent-owned threads, messages, memory, onboarding, and approval-first drafts.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
