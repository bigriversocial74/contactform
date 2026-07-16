-- Personal Agent Opportunity Actions and Commerce Attribution v1
-- Safe to re-run.

CREATE TABLE IF NOT EXISTS personal_agent_opportunities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  attribution_token CHAR(64) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  thread_public_id CHAR(36) NULL,
  assistant_message_public_id CHAR(36) NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  entity_type VARCHAR(40) NOT NULL,
  entity_public_id VARCHAR(190) NOT NULL,
  title VARCHAR(255) NOT NULL,
  destination_url VARCHAR(600) NOT NULL,
  state ENUM('active','saved','hidden','completed') NOT NULL DEFAULT 'active',
  source_context_json JSON NULL,
  last_action_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_personal_agent_opportunity_public (public_id),
  UNIQUE KEY uq_personal_agent_opportunity_token (attribution_token),
  UNIQUE KEY uq_personal_agent_opportunity_message_entity (user_id,assistant_message_public_id,entity_type,entity_public_id),
  KEY idx_personal_agent_opportunity_user_state (user_id,state,updated_at),
  KEY idx_personal_agent_opportunity_merchant (merchant_user_id,created_at),
  KEY idx_personal_agent_opportunity_entity (entity_type,entity_public_id),
  CONSTRAINT fk_personal_agent_opportunity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_personal_agent_opportunity_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS personal_agent_opportunity_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  opportunity_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  action_type VARCHAR(50) NULL,
  entity_type VARCHAR(40) NOT NULL,
  entity_public_id VARCHAR(190) NOT NULL,
  order_public_id VARCHAR(190) NULL,
  campaign_public_id VARCHAR(190) NULL,
  product_version_public_id VARCHAR(190) NULL,
  idempotency_key VARCHAR(190) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_personal_agent_event_public (public_id),
  UNIQUE KEY uq_personal_agent_event_idempotency (idempotency_key),
  KEY idx_personal_agent_event_opportunity (opportunity_id,created_at),
  KEY idx_personal_agent_event_user (user_id,event_type,created_at),
  KEY idx_personal_agent_event_merchant (merchant_user_id,event_type,created_at),
  KEY idx_personal_agent_event_order (order_public_id),
  CONSTRAINT fk_personal_agent_event_opportunity FOREIGN KEY (opportunity_id) REFERENCES personal_agent_opportunities(id) ON DELETE CASCADE,
  CONSTRAINT fk_personal_agent_event_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_personal_agent_event_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_18ak_personal_agent_opportunity_attribution_v1','Personal Agent saved opportunities, customer actions, commerce attribution tokens, conversion events, and merchant reporting.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);