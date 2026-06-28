-- ------------------------------------------------------------
-- Stage 20D Store Canvas Automation Rules
-- ------------------------------------------------------------
-- Purpose:
--   Adds per-trigger automation controls, cooldown policy, Stamp fallback
--   behavior, and CRM/follow-up metadata for Store Canvas trigger zones.
-- ------------------------------------------------------------

ALTER TABLE mg_store_trigger_zones
  ADD COLUMN automation_action ENUM('message_and_reward','message_only','reward_only','notify_only','follow_up','crm_segment','analytics_only') NOT NULL DEFAULT 'message_and_reward' AFTER campaign_public_id,
  ADD COLUMN cooldown_policy ENUM('five_minutes','fifteen_minutes','one_hour','once_per_visit','once_per_customer_day') NOT NULL DEFAULT 'fifteen_minutes' AFTER automation_action,
  ADD COLUMN cooldown_seconds INT UNSIGNED NOT NULL DEFAULT 900 AFTER cooldown_policy,
  ADD COLUMN auto_message_text VARCHAR(1000) NULL AFTER cooldown_seconds,
  ADD COLUMN fallback_action ENUM('notify_only','analytics_only','skip') NOT NULL DEFAULT 'notify_only' AFTER auto_message_text,
  ADD COLUMN crm_segment_name VARCHAR(160) NULL AFTER fallback_action,
  ADD COLUMN notify_merchant TINYINT(1) NOT NULL DEFAULT 1 AFTER crm_segment_name;

CREATE INDEX idx_mg_store_trigger_zones_action_status
  ON mg_store_trigger_zones (merchant_user_id,status,automation_action,priority);

CREATE INDEX idx_mg_store_trigger_zones_cooldown
  ON mg_store_trigger_zones (merchant_user_id,status,cooldown_policy,last_triggered_at);

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_20d_store_canvas_automation_rules','Per-zone Store Canvas automation actions, cooldowns, Stamp fallback behavior, and CRM metadata.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
