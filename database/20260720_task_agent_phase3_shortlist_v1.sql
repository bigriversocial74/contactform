-- Microgifter Task Agent Phase 3.1: local gift discovery shortlist v1.
-- Import after 20260719_multi_agent_runtime_memory_v1.sql.
-- Safe to rerun after a successful import.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS multi_agent_shortlist_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  agent_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  product_version_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NULL,
  recipient_context_json JSON NULL,
  discovery_reason VARCHAR(255) NULL,
  status ENUM('active','removed','selected') NOT NULL DEFAULT 'active',
  selected_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_multi_agent_shortlist_public_id (public_id),
  UNIQUE KEY uq_multi_agent_shortlist_owner_agent_version (owner_user_id,agent_id,product_version_id),
  KEY idx_multi_agent_shortlist_agent_status (agent_id,status,updated_at),
  KEY idx_multi_agent_shortlist_owner_status (owner_user_id,status,updated_at),
  KEY idx_multi_agent_shortlist_plan (plan_id,status,updated_at),
  CONSTRAINT fk_multi_agent_shortlist_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
  CONSTRAINT fk_multi_agent_shortlist_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_multi_agent_shortlist_product FOREIGN KEY (product_id) REFERENCES catalog_products(id) ON DELETE CASCADE,
  CONSTRAINT fk_multi_agent_shortlist_version FOREIGN KEY (product_version_id) REFERENCES catalog_product_versions(id) ON DELETE CASCADE,
  CONSTRAINT fk_multi_agent_shortlist_plan FOREIGN KEY (plan_id) REFERENCES user_gifting_plans(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('20260720_task_agent_phase3_shortlist_v1','Agent-owned published-product shortlist for deterministic local gift discovery.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
