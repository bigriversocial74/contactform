-- Stage 12 Campaign Features Full Import
-- One-file import for the Microgifter campaign, reward template, local discovery,
-- wallet, claim, redemption, feedback, and merchant action logging feature set.
--
-- Import order:
-- 1. Existing Microgifter core schema must already be installed.
-- 2. Import this file once through phpMyAdmin or mysql CLI.
-- 3. This file is written to be safe to re-import for table creation and index additions.

CREATE TABLE IF NOT EXISTS reward_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  reward_type ENUM('dollar_credit','free_item','discount','perk_upgrade','event_reward','custom') NOT NULL DEFAULT 'custom',
  value_type ENUM('fixed_amount','percent','free_item','custom') NOT NULL DEFAULT 'custom',
  value_amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
  value_percent DECIMAL(5,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  redemption_instructions TEXT NULL,
  expiration_rule ENUM('none','after_issue','after_claim','fixed_date','event_date') NOT NULL DEFAULT 'none',
  expiration_days INT UNSIGNED NULL,
  expires_at DATETIME NULL,
  quantity_limit INT UNSIGNED NULL,
  issued_count INT UNSIGNED NOT NULL DEFAULT 0,
  per_user_limit INT UNSIGNED NOT NULL DEFAULT 1,
  agent_discoverable TINYINT(1) NOT NULL DEFAULT 0,
  agent_summary VARCHAR(500) NULL,
  agent_categories_json JSON NULL,
  agent_locations_json JSON NULL,
  agent_budget_hint_cents INT UNSIGNED NULL,
  agent_use_cases_json JSON NULL,
  agent_add_to_wallet_allowed TINYINT(1) NOT NULL DEFAULT 0,
  agent_gift_send_allowed TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'draft',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reward_templates_public_id (public_id),
  KEY idx_reward_templates_merchant_status (merchant_user_id,status,updated_at),
  KEY idx_reward_templates_agent (agent_discoverable,status,updated_at),
  KEY idx_reward_templates_type (reward_type,status),
  CONSTRAINT fk_reward_templates_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  reward_template_id BIGINT UNSIGNED NULL,
  campaign_type ENUM('newsletter_signup','contest_giveaway','qr_reward_drop','referral_reward','birthday_vip','agent_offer') NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  form_headline VARCHAR(180) NULL,
  form_description TEXT NULL,
  success_message VARCHAR(500) NULL,
  status ENUM('draft','active','paused','ended','archived') NOT NULL DEFAULT 'draft',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  quantity_limit INT UNSIGNED NULL,
  issued_count INT UNSIGNED NOT NULL DEFAULT 0,
  per_user_limit INT UNSIGNED NOT NULL DEFAULT 1,
  requires_location_id BIGINT UNSIGNED NULL,
  agent_discoverable TINYINT(1) NOT NULL DEFAULT 0,
  public_slug VARCHAR(140) NULL,
  qr_code_token VARCHAR(96) NULL,
  rules_json JSON NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campaigns_public_id (public_id),
  UNIQUE KEY uq_campaigns_merchant_slug (merchant_user_id,public_slug),
  UNIQUE KEY uq_campaigns_qr_token (qr_code_token),
  KEY idx_campaigns_merchant_type_status (merchant_user_id,campaign_type,status,updated_at),
  KEY idx_campaigns_reward_template (reward_template_id,status),
  KEY idx_campaigns_agent (agent_discoverable,status,updated_at),
  CONSTRAINT fk_campaigns_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaigns_reward_template FOREIGN KEY (reward_template_id) REFERENCES reward_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_contacts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(60) NULL,
  name VARCHAR(180) NULL,
  source ENUM('newsletter_signup','contest_entry','qr_scan','referral','birthday_vip','agent_discovery','manual','api_issue') NOT NULL DEFAULT 'newsletter_signup',
  opt_in_status ENUM('unknown','opted_in','opted_out','bounced','complained') NOT NULL DEFAULT 'unknown',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campaign_contacts_public_id (public_id),
  UNIQUE KEY uq_campaign_contacts_campaign_email (campaign_id,email),
  KEY idx_campaign_contacts_merchant_email (merchant_user_id,email),
  KEY idx_campaign_contacts_user (user_id),
  KEY idx_campaign_contacts_source_created (source,created_at),
  CONSTRAINT fk_campaign_contacts_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_contacts_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_campaign_contacts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  contact_id BIGINT UNSIGNED NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  reward_template_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NULL,
  pppm_item_id BIGINT UNSIGNED NULL,
  source_type ENUM('purchase','manual_send','newsletter_signup','contest_entry','contest_winner','qr_scan','agent_discovery','api_issue') NOT NULL,
  source_id VARCHAR(190) NULL,
  status ENUM('issued','viewed','claimed','redeemed','expired','cancelled') NOT NULL DEFAULT 'issued',
  value_cents_snapshot INT UNSIGNED NOT NULL DEFAULT 0,
  currency_snapshot CHAR(3) NOT NULL DEFAULT 'USD',
  title_snapshot VARCHAR(180) NOT NULL,
  metadata_json JSON NULL,
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  viewed_at DATETIME NULL,
  claimed_at DATETIME NULL,
  redeemed_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wallet_items_public_id (public_id),
  KEY idx_wallet_items_user_status (user_id,status,updated_at),
  KEY idx_wallet_items_contact_status (contact_id,status,updated_at),
  KEY idx_wallet_items_merchant_source (merchant_user_id,source_type,issued_at),
  KEY idx_wallet_items_campaign_status (campaign_id,status,updated_at),
  KEY idx_wallet_items_reward_template (reward_template_id,status),
  KEY idx_wallet_items_pppm (pppm_item_id),
  CONSTRAINT fk_wallet_items_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_wallet_items_contact FOREIGN KEY (contact_id) REFERENCES campaign_contacts(id) ON DELETE SET NULL,
  CONSTRAINT fk_wallet_items_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wallet_items_reward_template FOREIGN KEY (reward_template_id) REFERENCES reward_templates(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wallet_items_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
  CONSTRAINT fk_wallet_items_pppm FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NULL,
  wallet_item_id BIGINT UNSIGNED NULL,
  contact_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(120) NOT NULL,
  event_context_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campaign_events_public_id (public_id),
  KEY idx_campaign_events_campaign_created (campaign_id,created_at,id),
  KEY idx_campaign_events_type_created (event_type,created_at),
  KEY idx_campaign_events_wallet_item (wallet_item_id),
  KEY idx_campaign_events_contact (contact_id),
  CONSTRAINT fk_campaign_events_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_campaign_events_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_campaign_events_wallet_item FOREIGN KEY (wallet_item_id) REFERENCES wallet_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_campaign_events_contact FOREIGN KEY (contact_id) REFERENCES campaign_contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If the table already existed from the earlier Stage 12 SQL, this aligns it
-- with agent-discovery/feedback events that do not always have campaign_id.
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

-- Permissions used by the merchant reward-template and campaign screens/APIs.
INSERT IGNORE INTO permissions (slug,name,created_at) VALUES
('merchant.reward_templates.view','View reward templates',NOW()),
('merchant.reward_templates.manage','Manage reward templates',NOW()),
('merchant.campaigns.view','View merchant campaigns',NOW()),
('merchant.campaigns.manage','Manage merchant campaigns',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN (
  'merchant.reward_templates.view',
  'merchant.reward_templates.manage',
  'merchant.campaigns.view',
  'merchant.campaigns.manage'
)
WHERE r.slug IN ('merchant','admin','super_admin');
