-- Stage 19D — Separate customer and merchant Claude chat defaults.
-- Customer Personal Agent chat prefers Claude Haiku 4.5.
-- Merchant Agent chat remains on Claude Sonnet 4.6.
-- Import after stage_19c_claude_sonnet_merchant_agent_planner.sql and
-- 20260714_personal_gifting_agent_phase2.sql.
-- Safe to rerun.

START TRANSACTION;

-- Keep the platform/merchant Anthropic default on Claude Sonnet 4.6.
UPDATE ai_models m
INNER JOIN ai_providers p ON p.id = m.provider_id
SET m.is_default = 0,
    m.updated_at = NOW()
WHERE p.provider_key = 'anthropic';

UPDATE ai_models m
INNER JOIN ai_providers p ON p.id = m.provider_id
SET m.display_name = 'Claude Sonnet 4.6',
    m.enabled = 1,
    m.is_default = 1,
    m.sort_order = 10,
    m.updated_at = NOW()
WHERE p.provider_key = 'anthropic'
  AND m.model_key = 'claude-sonnet-4-6';

-- Add the current active Haiku model for customer Personal Agent chat.
INSERT INTO ai_models
(public_id, provider_id, model_key, display_name, enabled, is_default, sort_order, max_input_tokens, max_output_tokens, metadata_json, created_at, updated_at)
SELECT UUID(), p.id, 'claude-haiku-4-5-20251001', 'Claude Haiku 4.5', 1, 0, 20, 200000, 64000,
       JSON_OBJECT(
         'recommended_for', 'customer_personal_agent_chat',
         'customer_chat_default', TRUE,
         'merchant_chat_default', FALSE,
         'stage', '19d'
       ),
       NOW(), NOW()
FROM ai_providers p
WHERE p.provider_key = 'anthropic'
ON DUPLICATE KEY UPDATE
  display_name = VALUES(display_name),
  enabled = 1,
  is_default = 0,
  sort_order = 20,
  max_input_tokens = VALUES(max_input_tokens),
  max_output_tokens = VALUES(max_output_tokens),
  metadata_json = VALUES(metadata_json),
  updated_at = NOW();

-- Retire the obsolete Haiku 3.5 catalog entries so they cannot be selected.
UPDATE ai_models m
INNER JOIN ai_providers p ON p.id = m.provider_id
SET m.enabled = 0,
    m.is_default = 0,
    m.updated_at = NOW()
WHERE p.provider_key = 'anthropic'
  AND m.model_key IN ('claude-3-5-haiku-latest', 'claude-3-5-haiku-20241022');

-- Move any existing Personal Agent preference from retired Haiku 3.5 to Haiku 4.5.
UPDATE user_agent_settings s
INNER JOIN ai_models old_model ON old_model.id = s.preferred_model_id
INNER JOIN ai_providers old_provider ON old_provider.id = old_model.provider_id AND old_provider.provider_key = 'anthropic'
INNER JOIN ai_providers new_provider ON new_provider.provider_key = 'anthropic'
INNER JOIN ai_models new_model ON new_model.provider_id = new_provider.id AND new_model.model_key = 'claude-haiku-4-5-20251001'
SET s.preferred_model_id = new_model.id,
    s.updated_at = NOW()
WHERE old_model.model_key IN ('claude-3-5-haiku-latest', 'claude-3-5-haiku-20241022');

INSERT INTO schema_migrations (migration_key, description, checksum, applied_at)
VALUES (
  'stage_19d_customer_haiku_merchant_sonnet_defaults',
  'Claude Haiku 4.5 customer Personal Agent default with Claude Sonnet 4.6 retained for merchant agent chat.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description = VALUES(description);

COMMIT;
