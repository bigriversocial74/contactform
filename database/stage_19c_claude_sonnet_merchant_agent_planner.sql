-- Stage 19C — Claude Sonnet Merchant Agent Planner
-- Adds the current Claude Sonnet model seed and durable, approval-first merchant AI plan storage.
-- Claude plans recommendations; Stage 16 remains the execution authority.

UPDATE ai_models m
INNER JOIN ai_providers p ON p.id = m.provider_id
SET m.is_default = 0, m.updated_at = NOW()
WHERE p.provider_key = 'anthropic';

INSERT INTO ai_models
(public_id, provider_id, model_key, display_name, enabled, is_default, sort_order, max_input_tokens, max_output_tokens, metadata_json, created_at, updated_at)
SELECT UUID(), p.id, 'claude-sonnet-4-6', 'Claude Sonnet 4.6', 1, 1, 10, 1000000, 128000,
       JSON_OBJECT('recommended_for','merchant_agent_planning','stage','19c'),
       NOW(), NOW()
FROM ai_providers p
WHERE p.provider_key = 'anthropic'
ON DUPLICATE KEY UPDATE
  display_name = VALUES(display_name),
  enabled = 1,
  is_default = 1,
  sort_order = 10,
  max_input_tokens = VALUES(max_input_tokens),
  max_output_tokens = VALUES(max_output_tokens),
  metadata_json = VALUES(metadata_json),
  updated_at = NOW();

CREATE TABLE IF NOT EXISTS ai_merchant_plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  agent_id BIGINT UNSIGNED NULL,
  provider_id BIGINT UNSIGNED NOT NULL,
  model_id BIGINT UNSIGNED NOT NULL,
  scope VARCHAR(80) NOT NULL DEFAULT 'all',
  merchant_goal VARCHAR(1000) NULL,
  status ENUM('review_ready','approved','partially_approved','rejected','archived','failed') NOT NULL DEFAULT 'review_ready',
  priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  summary TEXT NULL,
  prompt_fingerprint CHAR(64) NOT NULL,
  input_context_json JSON NULL,
  raw_response_json JSON NULL,
  input_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  error_message VARCHAR(1000) NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ai_merchant_plans_public_id (public_id),
  KEY idx_ai_merchant_plans_merchant_status (merchant_user_id,status,updated_at),
  KEY idx_ai_merchant_plans_agent_status (agent_id,status,updated_at),
  KEY idx_ai_merchant_plans_provider_model (provider_id,model_id,created_at),
  CONSTRAINT fk_ai_merchant_plans_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ai_merchant_plans_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_merchant_plans_provider FOREIGN KEY (provider_id) REFERENCES ai_providers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ai_merchant_plans_model FOREIGN KEY (model_id) REFERENCES ai_models(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ai_merchant_plans_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_merchant_plan_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  sequence_no INT UNSIGNED NOT NULL DEFAULT 1,
  action_key VARCHAR(120) NOT NULL,
  target_type VARCHAR(120) NOT NULL,
  target_reference VARCHAR(190) NULL,
  risk_level ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  requires_approval TINYINT(1) NOT NULL DEFAULT 1,
  confidence DECIMAL(5,4) NULL,
  title VARCHAR(220) NOT NULL,
  reason TEXT NULL,
  suggested_payload_json JSON NULL,
  status ENUM('recommended','approved','edited','deferred','rejected','queued','executed','failed') NOT NULL DEFAULT 'recommended',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ai_merchant_plan_items_public_id (public_id),
  KEY idx_ai_merchant_plan_items_plan (plan_id,sequence_no),
  KEY idx_ai_merchant_plan_items_status (status,updated_at),
  KEY idx_ai_merchant_plan_items_action (action_key,target_type),
  CONSTRAINT fk_ai_merchant_plan_items_plan FOREIGN KEY (plan_id) REFERENCES ai_merchant_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.ai.plan','Plan merchant AI recommendations','Use approved AI providers to create merchant-safe campaign, reward, CRM, claims, and analytics recommendations.',NOW()),
('merchant.ai.review','Review merchant AI recommendations','Review, approve, defer, or reject merchant AI plan items before execution.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('merchant.ai.plan','merchant.ai.review')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_19c_claude_sonnet_merchant_agent_planner',
  'Claude Sonnet 4.6 model seed and approval-first merchant AI plan storage.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);
