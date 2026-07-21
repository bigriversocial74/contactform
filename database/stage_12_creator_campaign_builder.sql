-- Microgifter Stage 12 Creator Campaign Builder v1
-- Adds merchant-owned creator/UGC campaign configuration without using the legacy marketing_affiliate model.
-- MySQL 8 / Aurora MySQL compatible. Safe to rerun.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS creator_campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  internal_name VARCHAR(160) NULL,
  title VARCHAR(180) NOT NULL,
  objective VARCHAR(64) NOT NULL DEFAULT 'sales',
  description TEXT NULL,
  status ENUM('draft','scheduled','active','paused','completed','cancelled','archived') NOT NULL DEFAULT 'draft',
  visibility ENUM('approved_creators','invite_only') NOT NULL DEFAULT 'approved_creators',
  participation_mode ENUM('application','invite_only','both') NOT NULL DEFAULT 'application',
  creator_approval_required TINYINT(1) NOT NULL DEFAULT 1,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  creator_requirements_json JSON NULL,
  attribution_json JSON NULL,
  budget_json JSON NULL,
  content_rights_json JSON NULL,
  terms_json JSON NULL,
  crm_rules_json JSON NULL,
  current_agreement_version INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaigns_public_id (public_id),
  KEY idx_creator_campaigns_merchant_status (merchant_user_id, status, updated_at),
  KEY idx_creator_campaigns_dates (starts_at, ends_at),
  CONSTRAINT fk_creator_campaigns_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  creator_campaign_id BIGINT UNSIGNED NOT NULL,
  source_type VARCHAR(40) NOT NULL DEFAULT 'catalog_product',
  source_public_id VARCHAR(64) NOT NULL,
  label VARCHAR(190) NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_products_source (creator_campaign_id, source_type, source_public_id),
  KEY idx_creator_campaign_products_campaign (creator_campaign_id, sort_order),
  CONSTRAINT fk_creator_campaign_products_campaign FOREIGN KEY (creator_campaign_id) REFERENCES creator_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_deliverables (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  creator_campaign_id BIGINT UNSIGNED NOT NULL,
  deliverable_type VARCHAR(48) NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  due_offset_days INT UNSIGNED NULL,
  approval_required TINYINT(1) NOT NULL DEFAULT 1,
  publication_required TINYINT(1) NOT NULL DEFAULT 0,
  specifications_json JSON NULL,
  fixed_payment_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_deliverables_public_id (public_id),
  KEY idx_creator_campaign_deliverables_campaign (creator_campaign_id, sort_order),
  CONSTRAINT fk_creator_campaign_deliverables_campaign FOREIGN KEY (creator_campaign_id) REFERENCES creator_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_compensation_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  creator_campaign_id BIGINT UNSIGNED NOT NULL,
  metric VARCHAR(64) NOT NULL,
  calculation_type ENUM('fixed','percentage') NOT NULL DEFAULT 'fixed',
  amount_value DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  trigger_event VARCHAR(100) NOT NULL,
  approval_required TINYINT(1) NOT NULL DEFAULT 1,
  hold_days INT UNSIGNED NOT NULL DEFAULT 0,
  stackable TINYINT(1) NOT NULL DEFAULT 1,
  per_creator_cap_cents BIGINT UNSIGNED NULL,
  campaign_cap_cents BIGINT UNSIGNED NULL,
  conditions_json JSON NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_compensation_public_id (public_id),
  KEY idx_creator_campaign_compensation_campaign (creator_campaign_id, sort_order),
  KEY idx_creator_campaign_compensation_metric (metric, trigger_event),
  CONSTRAINT fk_creator_campaign_compensation_campaign FOREIGN KEY (creator_campaign_id) REFERENCES creator_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_agreement_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  creator_campaign_id BIGINT UNSIGNED NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  config_hash CHAR(64) NOT NULL,
  snapshot_json JSON NOT NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_agreement_version (creator_campaign_id, version_no),
  UNIQUE KEY uq_creator_campaign_agreement_hash (creator_campaign_id, config_hash),
  CONSTRAINT fk_creator_campaign_agreement_campaign FOREIGN KEY (creator_campaign_id) REFERENCES creator_campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_creator_campaign_agreement_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_participants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  creator_campaign_id BIGINT UNSIGNED NOT NULL,
  creator_user_id BIGINT UNSIGNED NOT NULL,
  creator_profile_id BIGINT UNSIGNED NULL,
  agreement_version_id BIGINT UNSIGNED NULL,
  status ENUM('applied','under_review','approved','agreement_pending','active','completed','declined','removed','suspended') NOT NULL DEFAULT 'applied',
  applied_at DATETIME NULL,
  approved_at DATETIME NULL,
  accepted_at DATETIME NULL,
  completed_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_participants_public_id (public_id),
  UNIQUE KEY uq_creator_campaign_participants_creator (creator_campaign_id, creator_user_id),
  KEY idx_creator_campaign_participants_status (creator_campaign_id, status),
  CONSTRAINT fk_creator_campaign_participants_campaign FOREIGN KEY (creator_campaign_id) REFERENCES creator_campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_creator_campaign_participants_user FOREIGN KEY (creator_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_creator_campaign_participants_profile FOREIGN KEY (creator_profile_id) REFERENCES creator_profiles(id) ON DELETE SET NULL,
  CONSTRAINT fk_creator_campaign_participants_agreement FOREIGN KEY (agreement_version_id) REFERENCES creator_campaign_agreement_versions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
