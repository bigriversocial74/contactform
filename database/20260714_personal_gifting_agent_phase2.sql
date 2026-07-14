-- Microgifter Personal Gifting Agent Phase 2.
-- Import after database/20260714_user_contact_lists_phase1.sql.
-- Safe to rerun after a successful import.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS user_agent_settings (
  user_id BIGINT UNSIGNED NOT NULL,
  preferred_model_id BIGINT UNSIGNED NULL,
  default_currency CHAR(3) NOT NULL DEFAULT 'USD',
  default_budget_min DECIMAL(12,2) NULL,
  default_budget_max DECIMAL(12,2) NULL,
  approval_mode ENUM('advisory','draft_only') NOT NULL DEFAULT 'draft_only',
  suggestion_horizon_days SMALLINT UNSIGNED NOT NULL DEFAULT 45,
  enable_suggestions TINYINT(1) NOT NULL DEFAULT 1,
  enable_date_brief TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  KEY idx_user_agent_settings_model (preferred_model_id),
  CONSTRAINT fk_user_agent_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_agent_settings_model FOREIGN KEY (preferred_model_id) REFERENCES ai_models(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_gifting_plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  list_id BIGINT UNSIGNED NULL,
  user_contact_id BIGINT UNSIGNED NULL,
  contact_user_id BIGINT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  occasion_type VARCHAR(64) NOT NULL DEFAULT 'general',
  occasion_label VARCHAR(160) NULL,
  target_date DATE NULL,
  budget_min DECIMAL(12,2) NULL,
  budget_max DECIMAL(12,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  status ENUM('draft','planned','ready','completed','cancelled') NOT NULL DEFAULT 'draft',
  notes TEXT NULL,
  recommendation_json JSON NULL,
  source ENUM('manual','agent','important_date','list') NOT NULL DEFAULT 'manual',
  approval_required TINYINT(1) NOT NULL DEFAULT 1,
  completed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_gifting_plans_public_id (public_id),
  KEY idx_user_gifting_plans_owner_status (owner_user_id,status,target_date,updated_at),
  KEY idx_user_gifting_plans_list (list_id,status,target_date),
  KEY idx_user_gifting_plans_contact (user_contact_id,status,target_date),
  KEY idx_user_gifting_plans_linked (contact_user_id,status,target_date),
  CONSTRAINT fk_user_gifting_plans_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_gifting_plans_list FOREIGN KEY (list_id) REFERENCES user_contact_lists(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_gifting_plans_contact FOREIGN KEY (user_contact_id) REFERENCES user_contacts(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_gifting_plans_linked FOREIGN KEY (contact_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_gifting_plan_members (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  user_contact_id BIGINT UNSIGNED NULL,
  contact_user_id BIGINT UNSIGNED NULL,
  role_key VARCHAR(64) NOT NULL DEFAULT 'recipient',
  contribution_target DECIMAL(12,2) NULL,
  status ENUM('draft','invited','confirmed','declined','completed') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_gifting_plan_members_public_id (public_id),
  UNIQUE KEY uq_user_gifting_plan_member_private (plan_id,user_contact_id),
  UNIQUE KEY uq_user_gifting_plan_member_linked (plan_id,contact_user_id),
  KEY idx_user_gifting_plan_members_owner (owner_user_id,plan_id,status),
  CONSTRAINT fk_user_gifting_plan_members_plan FOREIGN KEY (plan_id) REFERENCES user_gifting_plans(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_gifting_plan_members_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_gifting_plan_members_contact FOREIGN KEY (user_contact_id) REFERENCES user_contacts(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_gifting_plan_members_linked FOREIGN KEY (contact_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT chk_user_gifting_plan_member_target CHECK (
    (user_contact_id IS NOT NULL AND contact_user_id IS NULL)
    OR (user_contact_id IS NULL AND contact_user_id IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_gifting_reminders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NULL,
  user_contact_date_id BIGINT UNSIGNED NULL,
  list_id BIGINT UNSIGNED NULL,
  user_contact_id BIGINT UNSIGNED NULL,
  contact_user_id BIGINT UNSIGNED NULL,
  reminder_type VARCHAR(64) NOT NULL DEFAULT 'gift_planning',
  title VARCHAR(190) NOT NULL,
  remind_at DATETIME NOT NULL,
  status ENUM('scheduled','completed','dismissed','cancelled') NOT NULL DEFAULT 'scheduled',
  delivery_channel ENUM('in_app') NOT NULL DEFAULT 'in_app',
  notes VARCHAR(2000) NULL,
  completed_at DATETIME NULL,
  dismissed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_gifting_reminders_public_id (public_id),
  KEY idx_user_gifting_reminders_owner_due (owner_user_id,status,remind_at),
  KEY idx_user_gifting_reminders_plan (plan_id,status,remind_at),
  CONSTRAINT fk_user_gifting_reminders_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_gifting_reminders_plan FOREIGN KEY (plan_id) REFERENCES user_gifting_plans(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_gifting_reminders_date FOREIGN KEY (user_contact_date_id) REFERENCES user_contact_dates(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_gifting_reminders_list FOREIGN KEY (list_id) REFERENCES user_contact_lists(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_gifting_reminders_contact FOREIGN KEY (user_contact_id) REFERENCES user_contacts(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_gifting_reminders_linked FOREIGN KEY (contact_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_agent_memory (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  memory_key VARCHAR(160) NOT NULL,
  category VARCHAR(64) NOT NULL DEFAULT 'preference',
  title VARCHAR(190) NOT NULL,
  value_json JSON NOT NULL,
  source ENUM('user','agent','contact','list','plan') NOT NULL DEFAULT 'user',
  confidence DECIMAL(4,3) NOT NULL DEFAULT 1.000,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_agent_memory_public_id (public_id),
  UNIQUE KEY uq_user_agent_memory_owner_key (owner_user_id,memory_key),
  KEY idx_user_agent_memory_owner_category (owner_user_id,status,category,updated_at),
  CONSTRAINT fk_user_agent_memory_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_agent_threads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL DEFAULT 'Personal gifting conversation',
  selected_context_type ENUM('none','contact','linked_user','list','plan') NOT NULL DEFAULT 'none',
  selected_context_public_id VARCHAR(64) NULL,
  last_message_at DATETIME NULL,
  cleared_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_agent_threads_public_id (public_id),
  KEY idx_user_agent_threads_owner_recent (owner_user_id,last_message_at,updated_at),
  CONSTRAINT fk_user_agent_threads_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_agent_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  thread_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('user','assistant','system') NOT NULL,
  body TEXT NOT NULL,
  cards_json JSON NULL,
  context_json JSON NULL,
  model_key VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_agent_messages_public_id (public_id),
  KEY idx_user_agent_messages_thread (thread_id,id),
  KEY idx_user_agent_messages_owner (owner_user_id,created_at),
  CONSTRAINT fk_user_agent_messages_thread FOREIGN KEY (thread_id) REFERENCES user_agent_threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_agent_messages_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO user_agent_settings (user_id)
SELECT id FROM users;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('agent.personal.use','Use personal gifting agent','Use the customer-side Personal Gifting Agent for private planning, reminders, memory, and approval-first recommendations.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug='agent.personal.use'
WHERE r.slug IN ('customer','member','merchant','creator','admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  '20260714_personal_gifting_agent_phase2',
  'Personal gifting agent settings, approval-first draft plans, reminders, memory, threads, messages, and group-plan members.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
