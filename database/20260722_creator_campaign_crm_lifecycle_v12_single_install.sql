-- Creator Campaign Phase 12 — CRM Contact Lifecycle Integration
-- Additive bridge between the separate creator_campaigns domain and the canonical Merchant CRM.
-- Prerequisites: stage_12_merchant_crm.sql and Creator Campaign Phases 1-5.
-- This migration does not alter or reuse the legacy merchant_crm_contact_campaigns.campaign_id foreign key.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS merchant_crm_creator_campaign_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  creator_campaign_id BIGINT UNSIGNED NOT NULL,
  crm_contact_id BIGINT UNSIGNED NULL,
  crm_event_id BIGINT UNSIGNED NULL,
  relationship_type ENUM('creator_partner','customer_lead','customer','claimant','redeemer') NOT NULL,
  source_domain ENUM('participation','tracking','earning','payout','dispute','message','manual') NOT NULL,
  source_event_key VARCHAR(190) NOT NULL,
  source_public_id VARCHAR(80) NULL,
  event_type VARCHAR(120) NOT NULL,
  projection_status ENUM('pending','completed','failed','skipped') NOT NULL DEFAULT 'pending',
  error_code VARCHAR(80) NULL,
  error_message VARCHAR(1000) NULL,
  metadata_json JSON NULL,
  occurred_at DATETIME NOT NULL,
  projected_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_crm_cc_event_public (public_id),
  UNIQUE KEY uq_merchant_crm_cc_event_source (merchant_user_id,source_event_key),
  KEY idx_merchant_crm_cc_event_campaign (merchant_user_id,creator_campaign_id,projection_status,occurred_at,id),
  KEY idx_merchant_crm_cc_event_contact (crm_contact_id,occurred_at,id),
  KEY idx_merchant_crm_cc_event_crm_event (crm_event_id),
  CONSTRAINT fk_merchant_crm_cc_event_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_crm_cc_event_campaign FOREIGN KEY (creator_campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_merchant_crm_cc_event_contact FOREIGN KEY (crm_contact_id) REFERENCES merchant_crm_contacts(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_crm_cc_event_crm_event FOREIGN KEY (crm_event_id) REFERENCES merchant_crm_contact_events(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_crm_contact_creator_campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  crm_contact_id BIGINT UNSIGNED NOT NULL,
  creator_campaign_id BIGINT UNSIGNED NOT NULL,
  relationship_type ENUM('creator_partner','customer_lead','customer','claimant','redeemer') NOT NULL,
  relationship_status ENUM('active','closed') NOT NULL DEFAULT 'active',
  first_event_at DATETIME NOT NULL,
  last_event_at DATETIME NOT NULL,
  event_count INT UNSIGNED NOT NULL DEFAULT 1,
  last_event_type VARCHAR(120) NOT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_crm_contact_cc_public (public_id),
  UNIQUE KEY uq_merchant_crm_contact_cc_role (crm_contact_id,creator_campaign_id,relationship_type),
  KEY idx_merchant_crm_contact_cc_merchant (merchant_user_id,relationship_type,last_event_at,id),
  KEY idx_merchant_crm_contact_cc_campaign (creator_campaign_id,relationship_type,last_event_at,id),
  CONSTRAINT fk_merchant_crm_contact_cc_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_crm_contact_cc_contact FOREIGN KEY (crm_contact_id) REFERENCES merchant_crm_contacts(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_crm_contact_cc_campaign FOREIGN KEY (creator_campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_crm_creator_campaign_projection_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  creator_campaign_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  run_mode ENUM('event','campaign','workspace') NOT NULL,
  status ENUM('running','completed','completed_with_errors','failed') NOT NULL DEFAULT 'running',
  participation_scanned INT UNSIGNED NOT NULL DEFAULT 0,
  tracking_scanned INT UNSIGNED NOT NULL DEFAULT 0,
  projected_count INT UNSIGNED NOT NULL DEFAULT 0,
  replay_count INT UNSIGNED NOT NULL DEFAULT 0,
  skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
  failed_count INT UNSIGNED NOT NULL DEFAULT 0,
  error_summary_json JSON NULL,
  started_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_crm_cc_run_public (public_id),
  KEY idx_merchant_crm_cc_run_merchant (merchant_user_id,started_at,id),
  KEY idx_merchant_crm_cc_run_campaign (creator_campaign_id,started_at,id),
  CONSTRAINT fk_merchant_crm_cc_run_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_crm_cc_run_campaign FOREIGN KEY (creator_campaign_id) REFERENCES creator_campaigns(id) ON DELETE SET NULL,
  CONSTRAINT fk_merchant_crm_cc_run_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.creator_crm.view','View Creator Campaign CRM','View canonical CRM contacts and Creator Campaign relationship history for the active merchant workspace.',NOW()),
('merchant.creator_crm.manage','Manage Creator Campaign CRM','Reconcile Creator Campaign lifecycle records into canonical Merchant CRM contacts.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('merchant.creator_crm.view','merchant.creator_crm.manage')
WHERE r.slug IN ('merchant','admin','super_admin');

COMMIT;
