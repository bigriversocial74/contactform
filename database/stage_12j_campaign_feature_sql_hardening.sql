-- Stage 12J Campaign Feature SQL Hardening
-- Applies after Stage 12 Campaigns + Reward Templates.
-- Purpose: align the Stage 12E-12I campaign, wallet, agent discovery,
-- feedback, and merchant next-step event code with the database schema.

START TRANSACTION;

-- Stage 12E/12G/12I agent-discovery event paths can create campaign_events
-- for reward-template-level activity before a campaign exists. Allow campaign_id
-- to be NULL while preserving the campaign foreign key for campaign-backed events.
ALTER TABLE campaign_events
  MODIFY campaign_id BIGINT UNSIGNED NULL;

-- Add read-path indexes used by Stage 12D-12H campaign operations and insights.
-- Keep these explicit and ordered for manual phpMyAdmin import.
ALTER TABLE campaign_events
  ADD KEY idx_campaign_events_merchant_type_created (merchant_user_id,event_type,created_at),
  ADD KEY idx_campaign_events_wallet_created (wallet_item_id,created_at),
  ADD KEY idx_campaign_events_contact_created (contact_id,created_at);

ALTER TABLE wallet_items
  ADD KEY idx_wallet_items_merchant_status_updated (merchant_user_id,status,updated_at),
  ADD KEY idx_wallet_items_user_source_status (user_id,source_type,status),
  ADD KEY idx_wallet_items_source_id (source_type,source_id);

ALTER TABLE reward_templates
  ADD KEY idx_reward_templates_agent_wallet (agent_discoverable,agent_add_to_wallet_allowed,status,updated_at),
  ADD KEY idx_reward_templates_merchant_agent (merchant_user_id,agent_discoverable,status,updated_at);

ALTER TABLE campaigns
  ADD KEY idx_campaigns_public_active (public_id,status,starts_at,ends_at),
  ADD KEY idx_campaigns_slug_active (public_slug,status,starts_at,ends_at);

COMMIT;
