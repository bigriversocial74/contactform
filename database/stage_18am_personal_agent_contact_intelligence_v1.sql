-- Microgifter Personal Agent Contact, List, and Occasion Intelligence v1.
-- Import after database/stage_18al_personal_agent_followup_recovery_v1.sql.
-- Safe to rerun after a successful import.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS user_agent_action_drafts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  thread_id BIGINT UNSIGNED NULL,
  action_type VARCHAR(64) NOT NULL,
  payload_json JSON NOT NULL,
  preview_json JSON NOT NULL,
  idempotency_key CHAR(64) NOT NULL,
  status ENUM('pending','confirmed','executed','cancelled','expired','failed') NOT NULL DEFAULT 'pending',
  expires_at DATETIME NOT NULL,
  confirmed_at DATETIME NULL,
  executed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  result_json JSON NULL,
  error_message VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_agent_action_drafts_public_id (public_id),
  UNIQUE KEY uq_user_agent_action_drafts_owner_idempotency (owner_user_id,idempotency_key),
  KEY idx_user_agent_action_drafts_owner_status (owner_user_id,status,expires_at,updated_at),
  KEY idx_user_agent_action_drafts_thread (thread_id,status,created_at),
  CONSTRAINT fk_user_agent_action_drafts_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_agent_action_drafts_thread FOREIGN KEY (thread_id) REFERENCES user_agent_threads(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_agent_action_receipts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  action_draft_id BIGINT UNSIGNED NULL,
  action_type VARCHAR(64) NOT NULL,
  entity_type VARCHAR(64) NOT NULL,
  entity_public_id VARCHAR(80) NULL,
  summary VARCHAR(500) NOT NULL,
  result_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_agent_action_receipts_public_id (public_id),
  KEY idx_user_agent_action_receipts_owner_recent (owner_user_id,created_at,id),
  KEY idx_user_agent_action_receipts_entity (entity_type,entity_public_id,created_at),
  CONSTRAINT fk_user_agent_action_receipts_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_agent_action_receipts_draft FOREIGN KEY (action_draft_id) REFERENCES user_agent_action_drafts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_agent_relationship_signals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  user_contact_id BIGINT UNSIGNED NULL,
  signal_key VARCHAR(190) NOT NULL,
  signal_type VARCHAR(64) NOT NULL,
  title VARCHAR(190) NOT NULL,
  summary VARCHAR(700) NOT NULL,
  score DECIMAL(6,3) NOT NULL DEFAULT 0.000,
  confidence DECIMAL(4,3) NOT NULL DEFAULT 0.500,
  event_date DATE NULL,
  status ENUM('active','dismissed','resolved') NOT NULL DEFAULT 'active',
  evidence_json JSON NULL,
  first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_agent_relationship_signals_public_id (public_id),
  UNIQUE KEY uq_user_agent_relationship_signals_owner_key (owner_user_id,signal_key),
  KEY idx_user_agent_relationship_signals_owner_status (owner_user_id,status,score,last_seen_at),
  KEY idx_user_agent_relationship_signals_contact (user_contact_id,status,event_date),
  CONSTRAINT fk_user_agent_relationship_signals_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_agent_relationship_signals_contact FOREIGN KEY (user_contact_id) REFERENCES user_contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('agent.personal.contact_actions','Use Personal Agent contact actions','Prepare and confirm owner-scoped contact, list, occasion, and reminder actions through the Personal Agent.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug='agent.personal.contact_actions'
WHERE r.slug IN ('customer','member','merchant','creator','admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_18am_personal_agent_contact_intelligence_v1',
  'Personal Agent reviewable contact actions, execution receipts, and relationship opportunity signals.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
