-- Stage 12J Campaign Feature SQL Hardening
-- Applies after Stage 12 Campaigns + Reward Templates.
-- Purpose: align the Stage 12E-12I campaign, wallet, agent discovery,
-- feedback, and merchant next-step event code with the database schema.
-- Safe to import manually in phpMyAdmin after taking a database backup.

-- Stage 12E/12G/12I agent-discovery event paths can create campaign_events
-- for reward-template-level activity before a campaign exists. Allow campaign_id
-- to be NULL while preserving the campaign foreign key for campaign-backed events.
ALTER TABLE campaign_events
  MODIFY campaign_id BIGINT UNSIGNED NULL;

DROP PROCEDURE IF EXISTS mg_add_index_if_missing;
DELIMITER $$
CREATE PROCEDURE mg_add_index_if_missing(
  IN p_table_name VARCHAR(128),
  IN p_index_name VARCHAR(128),
  IN p_index_columns TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = p_table_name
      AND index_name = p_index_name
    LIMIT 1
  ) THEN
    SET @mg_sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD KEY `', p_index_name, '` ', p_index_columns);
    PREPARE mg_stmt FROM @mg_sql;
    EXECUTE mg_stmt;
    DEALLOCATE PREPARE mg_stmt;
  END IF;
END$$
DELIMITER ;

CALL mg_add_index_if_missing('campaign_events','idx_campaign_events_merchant_type_created','(merchant_user_id,event_type,created_at)');
CALL mg_add_index_if_missing('campaign_events','idx_campaign_events_wallet_created','(wallet_item_id,created_at)');
CALL mg_add_index_if_missing('campaign_events','idx_campaign_events_contact_created','(contact_id,created_at)');

CALL mg_add_index_if_missing('wallet_items','idx_wallet_items_merchant_status_updated','(merchant_user_id,status,updated_at)');
CALL mg_add_index_if_missing('wallet_items','idx_wallet_items_user_source_status','(user_id,source_type,status)');
CALL mg_add_index_if_missing('wallet_items','idx_wallet_items_source_id','(source_type,source_id)');

CALL mg_add_index_if_missing('reward_templates','idx_reward_templates_agent_wallet','(agent_discoverable,agent_add_to_wallet_allowed,status,updated_at)');
CALL mg_add_index_if_missing('reward_templates','idx_reward_templates_merchant_agent','(merchant_user_id,agent_discoverable,status,updated_at)');

CALL mg_add_index_if_missing('campaigns','idx_campaigns_public_active','(public_id,status,starts_at,ends_at)');
CALL mg_add_index_if_missing('campaigns','idx_campaigns_slug_active','(public_slug,status,starts_at,ends_at)');

DROP PROCEDURE IF EXISTS mg_add_index_if_missing;
