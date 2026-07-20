-- Microgifter Task Agent Phase 4 consolidated migration v1.
-- Import after database/20260720_task_agent_phase3_shortlist_v1.sql.
-- This file will remain the single Phase 4 migration and is safe to rerun.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS multi_agent_recurring_program_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  agent_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  program_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_multi_agent_recurring_link_public_id (public_id),
  UNIQUE KEY uq_multi_agent_recurring_link_owner_program (owner_user_id,program_id),
  UNIQUE KEY uq_multi_agent_recurring_link_owner_agent_program (owner_user_id,agent_id,program_id),
  KEY idx_multi_agent_recurring_link_agent (agent_id,updated_at),
  CONSTRAINT fk_multi_agent_recurring_link_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
  CONSTRAINT fk_multi_agent_recurring_link_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_multi_agent_recurring_link_program FOREIGN KEY (program_id) REFERENCES user_recurring_gift_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS multi_agent_group_gift_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  agent_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  group_gift_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_multi_agent_group_link_public_id (public_id),
  UNIQUE KEY uq_multi_agent_group_link_owner_group (owner_user_id,group_gift_id),
  KEY idx_multi_agent_group_link_agent (agent_id,updated_at),
  CONSTRAINT fk_multi_agent_group_link_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
  CONSTRAINT fk_multi_agent_group_link_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_multi_agent_group_link_group FOREIGN KEY (group_gift_id) REFERENCES user_group_gifts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('20260720_task_agent_phase4_v1','Consolidated Task Agent Phase 4 program, group-gifting, and approval integration schema.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
