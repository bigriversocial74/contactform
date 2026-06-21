-- Microgifter Stage 19 AI Provider Models and Rate Limits
-- Provider API keys remain server-side environment variables. Database stores enabled providers, model catalog, agent selections, and rate-limit policy/usage.

CREATE TABLE IF NOT EXISTS ai_providers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  provider_key VARCHAR(80) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  env_var_name VARCHAR(120) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  rate_limit_per_minute INT UNSIGNED NOT NULL DEFAULT 60,
  rate_limit_per_hour INT UNSIGNED NOT NULL DEFAULT 1000,
  rate_limit_per_day INT UNSIGNED NOT NULL DEFAULT 5000,
  user_rate_limit_per_hour INT UNSIGNED NOT NULL DEFAULT 100,
  user_rate_limit_per_day INT UNSIGNED NOT NULL DEFAULT 500,
  agent_rate_limit_per_hour INT UNSIGNED NOT NULL DEFAULT 30,
  agent_rate_limit_per_day INT UNSIGNED NOT NULL DEFAULT 150,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ai_providers_public_id (public_id),
  UNIQUE KEY uq_ai_providers_key (provider_key),
  KEY idx_ai_providers_enabled (enabled, provider_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_models (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  provider_id BIGINT UNSIGNED NOT NULL,
  model_key VARCHAR(120) NOT NULL,
  display_name VARCHAR(160) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 100,
  max_input_tokens INT UNSIGNED NULL,
  max_output_tokens INT UNSIGNED NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ai_models_public_id (public_id),
  UNIQUE KEY uq_ai_models_provider_model (provider_id, model_key),
  KEY idx_ai_models_provider_enabled (provider_id, enabled, sort_order),
  CONSTRAINT fk_ai_models_provider FOREIGN KEY (provider_id) REFERENCES ai_providers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_ai_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agent_id BIGINT UNSIGNED NOT NULL,
  provider_id BIGINT UNSIGNED NOT NULL,
  model_id BIGINT UNSIGNED NOT NULL,
  rate_limit_per_hour INT UNSIGNED NULL,
  rate_limit_per_day INT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agent_ai_settings_agent (agent_id),
  KEY idx_agent_ai_settings_provider_model (provider_id, model_id),
  CONSTRAINT fk_agent_ai_settings_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE RESTRICT,
  CONSTRAINT fk_agent_ai_settings_provider FOREIGN KEY (provider_id) REFERENCES ai_providers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_agent_ai_settings_model FOREIGN KEY (model_id) REFERENCES ai_models(id) ON DELETE RESTRICT,
  CONSTRAINT fk_agent_ai_settings_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_usage_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider_id BIGINT UNSIGNED NOT NULL,
  model_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  agent_id BIGINT UNSIGNED NULL,
  request_status ENUM('allowed','blocked','completed','failed') NOT NULL DEFAULT 'allowed',
  block_scope ENUM('global','user','agent') NULL,
  request_units INT UNSIGNED NOT NULL DEFAULT 1,
  input_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ai_usage_provider_created (provider_id, created_at),
  KEY idx_ai_usage_user_created (user_id, provider_id, created_at),
  KEY idx_ai_usage_agent_created (agent_id, user_id, provider_id, created_at),
  CONSTRAINT fk_ai_usage_provider FOREIGN KEY (provider_id) REFERENCES ai_providers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ai_usage_model FOREIGN KEY (model_id) REFERENCES ai_models(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_usage_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_usage_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO ai_providers
(public_id, provider_key, display_name, env_var_name, enabled, rate_limit_per_minute, rate_limit_per_hour, rate_limit_per_day, user_rate_limit_per_hour, user_rate_limit_per_day, agent_rate_limit_per_hour, agent_rate_limit_per_day, created_at, updated_at)
VALUES
(UUID(), 'anthropic', 'Claude / Anthropic', 'MG_ANTHROPIC_API_KEY', 0, 60, 1000, 5000, 100, 500, 30, 150, NOW(), NOW()),
(UUID(), 'openai', 'GPT / OpenAI', 'MG_OPENAI_API_KEY', 0, 60, 1000, 5000, 100, 500, 30, 150, NOW(), NOW()),
(UUID(), 'google', 'Gemma / Google', 'MG_GEMMA_API_KEY', 0, 60, 1000, 5000, 100, 500, 30, 150, NOW(), NOW()),
(UUID(), 'kimi', 'Kimi / Moonshot AI', 'MG_KIMI_API_KEY', 0, 60, 1000, 5000, 100, 500, 30, 150, NOW(), NOW()),
(UUID(), 'llama', 'Llama / Meta or self-hosted', 'MG_LLAMA_API_KEY', 0, 60, 1000, 5000, 100, 500, 30, 150, NOW(), NOW());

INSERT IGNORE INTO ai_models (public_id, provider_id, model_key, display_name, enabled, is_default, sort_order, created_at, updated_at)
SELECT UUID(), p.id, x.model_key, x.display_name, 1, x.is_default, x.sort_order, NOW(), NOW()
FROM ai_providers p
JOIN (
  SELECT 'anthropic' provider_key, 'claude-3-5-sonnet-latest' model_key, 'Claude Sonnet' display_name, 1 is_default, 10 sort_order UNION ALL
  SELECT 'anthropic', 'claude-3-5-haiku-latest', 'Claude Haiku', 0, 20 UNION ALL
  SELECT 'openai', 'gpt-4o-mini', 'GPT-4o mini', 1, 10 UNION ALL
  SELECT 'openai', 'gpt-4o', 'GPT-4o', 0, 20 UNION ALL
  SELECT 'google', 'gemma-3', 'Gemma 3', 1, 10 UNION ALL
  SELECT 'kimi', 'kimi-latest', 'Kimi latest', 1, 10 UNION ALL
  SELECT 'llama', 'llama-3.1', 'Llama 3.1', 1, 10
) x ON x.provider_key = p.provider_key;

INSERT IGNORE INTO permissions (slug, name, description, created_at) VALUES
('admin.ai.manage', 'Manage AI settings', 'Configure AI provider model catalog and platform rate limits.', NOW()),
('agent.ai.configure', 'Configure agent AI model', 'Select an approved AI model for owned agents.', NOW());

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.ai.manage','agent.ai.configure')
WHERE r.slug IN ('admin','super_admin','merchant','customer');
