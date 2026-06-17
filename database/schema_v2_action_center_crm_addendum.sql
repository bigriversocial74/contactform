-- Microgifter Schema v2 Addendum: Action Center + Sales CRM
-- Generated after comparing the uploaded Stage 1-9 consolidated SQL against current codebase usage.
-- This file is intentionally additive. It does not rewrite the Stage 1-9 consolidation.
-- Apply after microgifter_stage1_to_stage9_upgrade(3).sql or merge these definitions into the next full consolidation.

SET FOREIGN_KEY_CHECKS=0;

-- -----------------------------------------------------------------------------
-- Action Center projection table
-- Required by api/account/_action_center.php and api/microgifts/_action_center_projection.php
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS microgift_inbox_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  instance_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  folder ENUM('inbox','sent','claimed') NOT NULL DEFAULT 'inbox',
  state ENUM('claimable','redeemable','redeemed','expired','revoked','received') NOT NULL DEFAULT 'received',
  sender_user_id BIGINT UNSIGNED NULL,
  recipient_user_id BIGINT UNSIGNED NULL,
  redemption_id BIGINT UNSIGNED NULL,
  merchant_user_id BIGINT UNSIGNED NULL,
  location_id BIGINT UNSIGNED NULL,
  can_tip TINYINT(1) NOT NULL DEFAULT 0,
  read_at DATETIME NULL,
  archived_at DATETIME NULL,
  first_received_at DATETIME NULL,
  sent_at DATETIME NULL,
  claimed_at DATETIME NULL,
  redeemed_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgift_inbox_items_public_id (public_id),
  UNIQUE KEY uq_microgift_inbox_items_instance_user (instance_id,user_id),
  KEY idx_microgift_inbox_items_user_folder_updated (user_id,folder,archived_at,updated_at,id),
  KEY idx_microgift_inbox_items_user_unread (user_id,folder,read_at,archived_at),
  KEY idx_microgift_inbox_items_instance (instance_id),
  KEY idx_microgift_inbox_items_sender (sender_user_id,updated_at),
  KEY idx_microgift_inbox_items_recipient (recipient_user_id,updated_at),
  KEY idx_microgift_inbox_items_redemption (redemption_id),
  KEY idx_microgift_inbox_items_location (location_id),
  CONSTRAINT fk_microgift_inbox_items_instance FOREIGN KEY (instance_id) REFERENCES microgift_instances(id) ON DELETE RESTRICT,
  CONSTRAINT fk_microgift_inbox_items_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_microgift_inbox_items_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_inbox_items_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_inbox_items_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_inbox_items_redemption FOREIGN KEY (redemption_id) REFERENCES microgift_redemptions(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_inbox_items_location FOREIGN KEY (location_id) REFERENCES merchant_locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Sales CRM module tables
-- Required by includes/crm.php, api/sales/*, api/admin/sales/*, and sales-crm.php.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS crm_leads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  lead_type ENUM('merchant','workplace','creator','affiliate','partner','general') NOT NULL DEFAULT 'general',
  source_page VARCHAR(180) NOT NULL DEFAULT 'learn-more',
  source_url VARCHAR(600) NULL,
  source_utm_json JSON NULL,
  name VARCHAR(180) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(80) NULL,
  business_name VARCHAR(220) NULL,
  website_url VARCHAR(600) NULL,
  zip_code VARCHAR(20) NULL,
  category VARCHAR(120) NULL,
  message TEXT NULL,
  status ENUM('new','assigned','contacted','qualified','nurture','converted','closed_lost','spam') NOT NULL DEFAULT 'new',
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  assigned_user_id BIGINT UNSIGNED NULL,
  assigned_at DATETIME NULL,
  region_country VARCHAR(80) NULL,
  region_state VARCHAR(120) NULL,
  region_city VARCHAR(120) NULL,
  region_postal VARCHAR(40) NULL,
  ip_hash CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_crm_leads_public_id (public_id),
  KEY idx_crm_leads_status_updated (status,updated_at),
  KEY idx_crm_leads_assigned_status (assigned_user_id,status,updated_at),
  KEY idx_crm_leads_email (email),
  KEY idx_crm_leads_region (region_country,region_state,region_city,region_postal),
  KEY idx_crm_leads_source_created (source_page,created_at),
  CONSTRAINT fk_crm_leads_assigned_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_lead_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  lead_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  from_status VARCHAR(40) NULL,
  to_status VARCHAR(40) NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  note TEXT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_crm_lead_events_public_id (public_id),
  KEY idx_crm_lead_events_lead_created (lead_id,created_at),
  KEY idx_crm_lead_events_actor_created (actor_user_id,created_at),
  KEY idx_crm_lead_events_type_created (event_type,created_at),
  CONSTRAINT fk_crm_lead_events_lead FOREIGN KEY (lead_id) REFERENCES crm_leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_crm_lead_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_lead_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  lead_id BIGINT UNSIGNED NOT NULL,
  assigned_to_user_id BIGINT UNSIGNED NOT NULL,
  assigned_by_user_id BIGINT UNSIGNED NULL,
  assignment_method ENUM('auto_least_open','manual','system') NOT NULL DEFAULT 'manual',
  reason TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_crm_lead_assignments_public_id (public_id),
  KEY idx_crm_lead_assignments_lead_created (lead_id,created_at),
  KEY idx_crm_lead_assignments_to_created (assigned_to_user_id,created_at),
  CONSTRAINT fk_crm_lead_assignments_lead FOREIGN KEY (lead_id) REFERENCES crm_leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_crm_lead_assignments_to FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_crm_lead_assignments_by FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_lead_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  lead_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  note TEXT NOT NULL,
  visibility ENUM('internal','public') NOT NULL DEFAULT 'internal',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_crm_lead_notes_public_id (public_id),
  KEY idx_crm_lead_notes_lead_created (lead_id,created_at),
  KEY idx_crm_lead_notes_user_created (user_id,created_at),
  CONSTRAINT fk_crm_lead_notes_lead FOREIGN KEY (lead_id) REFERENCES crm_leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_crm_lead_notes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_roster (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('active','inactive','paused','suspended') NOT NULL DEFAULT 'active',
  territory VARCHAR(180) NULL,
  region_code VARCHAR(80) NULL,
  lead_weight INT UNSIGNED NOT NULL DEFAULT 100,
  max_open_leads INT UNSIGNED NOT NULL DEFAULT 50,
  open_lead_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_assigned_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sales_roster_public_id (public_id),
  UNIQUE KEY uq_sales_roster_user (user_id),
  KEY idx_sales_roster_status_load (status,open_lead_count,last_assigned_at,id),
  KEY idx_sales_roster_region_status (region_code,status,open_lead_count,last_assigned_at,id),
  CONSTRAINT fk_sales_roster_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_presence (
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('online','away','offline') NOT NULL DEFAULT 'offline',
  last_seen_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  KEY idx_sales_presence_status_seen (status,last_seen_at),
  CONSTRAINT fk_sales_presence_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_chat_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  recipient_user_id BIGINT UNSIGNED NOT NULL,
  message TEXT NOT NULL,
  sent_while_offline TINYINT(1) NOT NULL DEFAULT 0,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_employee_chat_messages_public_id (public_id),
  KEY idx_employee_chat_messages_thread (sender_user_id,recipient_user_id,created_at,id),
  KEY idx_employee_chat_messages_unread (recipient_user_id,sender_user_id,read_at),
  CONSTRAINT fk_employee_chat_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_employee_chat_messages_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS website_analytics_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  source_page VARCHAR(180) NULL,
  path VARCHAR(500) NULL,
  referrer VARCHAR(700) NULL,
  utm_source VARCHAR(120) NULL,
  utm_medium VARCHAR(120) NULL,
  utm_campaign VARCHAR(160) NULL,
  utm_term VARCHAR(160) NULL,
  utm_content VARCHAR(160) NULL,
  region_country VARCHAR(80) NULL,
  region_state VARCHAR(120) NULL,
  region_city VARCHAR(120) NULL,
  region_postal VARCHAR(40) NULL,
  timezone_label VARCHAR(120) NULL,
  ip_hash CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  session_key_hash CHAR(64) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_website_analytics_events_public_id (public_id),
  KEY idx_website_analytics_events_created (created_at),
  KEY idx_website_analytics_events_source_created (source_page,created_at),
  KEY idx_website_analytics_events_type_created (event_type,created_at),
  KEY idx_website_analytics_events_region_created (region_country,region_state,region_city,region_postal,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
