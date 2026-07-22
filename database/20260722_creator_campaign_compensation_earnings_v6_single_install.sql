-- Creator Campaign Compensation and Earnings v6
-- Scope: immutable compensation rule versions, append-only creator earning events, reversals, adjustments, and reporting.
-- Campaign budgets, holds, payout execution, disputes, tax reporting, and MCP execution remain later phases.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS creator_campaign_compensation_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  rule_code VARCHAR(80) NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  compensation_type ENUM('fixed_deliverable','percent_conversion','flat_conversion','milestone','manual_only') NOT NULL,
  trigger_type ENUM('deliverable_verified','purchase_attributed','claim_attributed','redemption_attributed','milestone_approved','manual') NOT NULL,
  status ENUM('draft','active','retired') NOT NULL DEFAULT 'draft',
  active_trigger_key VARCHAR(190) GENERATED ALWAYS AS (
    CASE WHEN status='active' THEN CONCAT(campaign_id,':',trigger_type) ELSE NULL END
  ) STORED,
  current_version_id BIGINT UNSIGNED NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_comp_rule_public (public_id),
  UNIQUE KEY uq_cc_comp_rule_code (campaign_id,rule_code),
  UNIQUE KEY uq_cc_comp_rule_active_trigger (active_trigger_key),
  KEY idx_cc_comp_rule_campaign (campaign_id,status,trigger_type,id),
  CONSTRAINT fk_cc_comp_rule_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_comp_rule_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_comp_rule_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_compensation_rule_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  rule_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  version_status ENUM('draft','active','superseded','retired') NOT NULL DEFAULT 'draft',
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  flat_amount_minor BIGINT UNSIGNED NULL,
  rate_bps INT UNSIGNED NULL,
  minimum_source_amount_minor BIGINT UNSIGNED NULL,
  maximum_earning_minor BIGINT UNSIGNED NULL,
  terms_text MEDIUMTEXT NOT NULL,
  calculation_snapshot_json JSON NOT NULL,
  content_hash CHAR(64) NOT NULL,
  effective_from DATETIME NULL,
  effective_to DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_comp_version_public (public_id),
  UNIQUE KEY uq_cc_comp_version_number (rule_id,version_number),
  UNIQUE KEY uq_cc_comp_version_hash (rule_id,content_hash),
  KEY idx_cc_comp_version_campaign (campaign_id,version_status,created_at,id),
  CONSTRAINT fk_cc_comp_version_rule FOREIGN KEY (rule_id) REFERENCES creator_campaign_compensation_rules(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_comp_version_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_comp_version_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT chk_cc_comp_version_rate CHECK (rate_bps IS NULL OR rate_bps <= 10000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @cc_comp_current_fk_exists := (
  SELECT COUNT(*) FROM information_schema.referential_constraints
  WHERE constraint_schema=DATABASE() AND constraint_name='fk_cc_comp_rule_current_version'
);
SET @cc_comp_current_fk_sql := IF(
  @cc_comp_current_fk_exists=0,
  'ALTER TABLE creator_campaign_compensation_rules ADD CONSTRAINT fk_cc_comp_rule_current_version FOREIGN KEY (current_version_id) REFERENCES creator_campaign_compensation_rule_versions(id) ON DELETE RESTRICT',
  'SELECT 1'
);
PREPARE cc_comp_current_fk_stmt FROM @cc_comp_current_fk_sql;
EXECUTE cc_comp_current_fk_stmt;
DEALLOCATE PREPARE cc_comp_current_fk_stmt;

CREATE TABLE IF NOT EXISTS creator_campaign_earning_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  creator_user_id BIGINT UNSIGNED NOT NULL,
  agreement_version_id BIGINT UNSIGNED NOT NULL,
  rule_id BIGINT UNSIGNED NULL,
  rule_version_id BIGINT UNSIGNED NULL,
  event_type ENUM('earning','adjustment','reversal') NOT NULL,
  source_type ENUM('deliverable','attribution','conversion','milestone','manual') NOT NULL,
  source_public_id VARCHAR(80) NOT NULL,
  source_amount_minor BIGINT UNSIGNED NULL,
  amount_minor BIGINT NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  reversal_of_event_id BIGINT UNSIGNED NULL,
  idempotency_hash CHAR(64) NOT NULL,
  source_hash CHAR(64) NOT NULL,
  calculation_snapshot_json JSON NOT NULL,
  reason VARCHAR(2000) NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_earning_public (public_id),
  UNIQUE KEY uq_cc_earning_idempotency (campaign_id,idempotency_hash),
  UNIQUE KEY uq_cc_earning_reversal (reversal_of_event_id),
  KEY idx_cc_earning_campaign (campaign_id,created_at,id),
  KEY idx_cc_earning_participant (participant_id,created_at,id),
  KEY idx_cc_earning_creator (creator_user_id,created_at,id),
  KEY idx_cc_earning_rule (rule_id,created_at,id),
  KEY idx_cc_earning_source (source_type,source_public_id,created_at,id),
  CONSTRAINT fk_cc_earning_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_earning_participant FOREIGN KEY (participant_id) REFERENCES creator_campaign_participants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_earning_creator FOREIGN KEY (creator_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_earning_agreement_version FOREIGN KEY (agreement_version_id) REFERENCES creator_campaign_agreement_versions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_earning_rule FOREIGN KEY (rule_id) REFERENCES creator_campaign_compensation_rules(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_earning_rule_version FOREIGN KEY (rule_version_id) REFERENCES creator_campaign_compensation_rule_versions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_earning_reversal FOREIGN KEY (reversal_of_event_id) REFERENCES creator_campaign_earning_events(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_earning_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT chk_cc_earning_nonzero CHECK (amount_minor <> 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.creator_compensation.view','View creator compensation','View campaign compensation rules, immutable versions, and creator earning events.',NOW()),
('merchant.creator_compensation.manage','Manage creator compensation','Create and retire compensation rules, issue adjustments, and reverse earning events.',NOW()),
('merchant.creator_earnings.view','View creator earnings','View participant earnings and append-only earning history.',NOW()),
('creator.campaign_earnings.view_own','View own campaign earnings','View earnings for the authenticated Creator account.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN (
  'merchant.creator_compensation.view',
  'merchant.creator_compensation.manage',
  'merchant.creator_earnings.view'
)
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug='creator.campaign_earnings.view_own'
WHERE r.slug IN ('creator','admin','super_admin');

COMMIT;
