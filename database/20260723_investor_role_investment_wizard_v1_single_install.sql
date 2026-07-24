-- Microgifter Investor Role and Investment Wizard v1
-- Import-safe single-install migration.
-- Planning and access management only: this migration does not issue securities,
-- accept investment funds, verify accreditation, or replace the legal cap table.

SET @mg_schema := DATABASE();

INSERT INTO roles (slug,name,created_at) VALUES
('investor','Investor',NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO permissions (slug,name,created_at) VALUES
('investment.portal.view','View the private Investor Portal',NOW()),
('investment.access.request','Request Investor Portal access',NOW()),
('investment.profile.view','View own investor profile',NOW()),
('investment.profile.manage','Manage own investor profile',NOW()),
('investment.round.view','View approved investment rounds',NOW()),
('investment.documents.view','View approved investment documents',NOW()),
('investment.interest.submit','Submit non-binding investment interest',NOW()),
('admin.investment.view','View investment administration',NOW()),
('admin.investment.manage','Manage investment workspaces, scenarios and rounds',NOW()),
('admin.investor_access.view','View investor-access requests',NOW()),
('admin.investor_access.manage','Review and decide investor-access requests',NOW()),
('admin.investment.ai','Run draft-only Claude investment analysis',NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r JOIN permissions p ON p.slug IN (
  'investment.portal.view','investment.profile.view','investment.profile.manage',
  'investment.round.view','investment.documents.view','investment.interest.submit'
)
WHERE r.slug='investor';

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r JOIN permissions p ON p.slug IN (
  'investment.access.request','investment.profile.view','investment.profile.manage'
)
WHERE r.slug='customer';

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r JOIN permissions p ON p.slug IN (
  'admin.investment.view','admin.investment.manage','admin.investor_access.view',
  'admin.investor_access.manage','admin.investment.ai'
)
WHERE r.slug IN ('admin','super_admin');

CREATE TABLE IF NOT EXISTS investor_access_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','more_information_requested','approved','denied','revoked','withdrawn') NOT NULL DEFAULT 'pending',
  firm_name VARCHAR(180) NOT NULL,
  job_title VARCHAR(160) NULL,
  website_url VARCHAR(500) NULL,
  primary_social_url VARCHAR(500) NOT NULL,
  linkedin_url VARCHAR(500) NULL,
  additional_social_url VARCHAR(500) NULL,
  investor_type ENUM('individual','angel','investment_firm','venture_fund','family_office','strategic_partner','company_entity','other') NOT NULL DEFAULT 'individual',
  expected_investment_range ENUM('undecided','under_10k','10k_25k','25k_50k','50k_100k','100k_250k','over_250k') NOT NULL DEFAULT 'undecided',
  referral_source VARCHAR(180) NULL,
  phone VARCHAR(60) NULL,
  request_reason TEXT NOT NULL,
  acknowledgement_at DATETIME NOT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_notes TEXT NULL,
  more_information_message TEXT NULL,
  reapplication_allowed TINYINT(1) NOT NULL DEFAULT 1,
  reapplication_after DATETIME NULL,
  revoked_by_user_id BIGINT UNSIGNED NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investor_access_public_id (public_id),
  KEY idx_investor_access_user_status (user_id,status),
  KEY idx_investor_access_queue (status,requested_at),
  CONSTRAINT fk_investor_access_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_access_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investor_access_revoker FOREIGN KEY (revoked_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investor_access_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  details_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_investor_access_events (request_id,created_at),
  CONSTRAINT fk_investor_access_events_request FOREIGN KEY (request_id) REFERENCES investor_access_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_access_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investor_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  source_request_id BIGINT UNSIGNED NULL,
  status ENUM('active','suspended','revoked','closed') NOT NULL DEFAULT 'active',
  firm_name VARCHAR(180) NULL,
  job_title VARCHAR(160) NULL,
  website_url VARCHAR(500) NULL,
  primary_social_url VARCHAR(500) NULL,
  investor_type VARCHAR(60) NULL,
  expected_investment_range VARCHAR(60) NULL,
  preferred_contact_method ENUM('email','phone','portal') NOT NULL DEFAULT 'portal',
  notes TEXT NULL,
  approved_by_user_id BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  access_expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investor_profiles_public_id (public_id),
  UNIQUE KEY uq_investor_profiles_user (user_id),
  CONSTRAINT fk_investor_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_profiles_request FOREIGN KEY (source_request_id) REFERENCES investor_access_requests(id) ON DELETE SET NULL,
  CONSTRAINT fk_investor_profiles_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_workspaces (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  status ENUM('draft','under_review','ready_for_counsel','private_preview','archived') NOT NULL DEFAULT 'draft',
  active_step VARCHAR(60) NOT NULL DEFAULT 'company',
  company_json JSON NULL,
  capitalization_json JSON NULL,
  operating_plan_json JSON NULL,
  assumptions_json JSON NULL,
  notes TEXT NULL,
  preferred_scenario_id BIGINT UNSIGNED NULL,
  last_saved_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_workspaces_public_id (public_id),
  KEY idx_investment_workspaces_status (status,updated_at),
  CONSTRAINT fk_investment_workspaces_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_investment_workspaces_saver FOREIGN KEY (last_saved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_scenarios (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  status ENUM('draft','under_review','preferred','illustrative','approved','archived') NOT NULL DEFAULT 'draft',
  instrument_type ENUM('not_finalized','post_money_safe','convertible_note','priced_equity') NOT NULL DEFAULT 'not_finalized',
  minimum_raise_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  target_raise_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  maximum_raise_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  valuation_cap_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  pre_money_valuation_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  discount_bps INT UNSIGNED NOT NULL DEFAULT 0,
  target_dilution_bps INT UNSIGNED NOT NULL DEFAULT 0,
  maximum_dilution_bps INT UNSIGNED NOT NULL DEFAULT 1000,
  minimum_investment_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  desired_runway_months SMALLINT UNSIGNED NOT NULL DEFAULT 12,
  option_pool_bps INT UNSIGNED NOT NULL DEFAULT 0,
  existing_safe_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  forecast_months SMALLINT UNSIGNED NOT NULL DEFAULT 24,
  forecast_case ENUM('conservative','expected','upside') NOT NULL DEFAULT 'expected',
  assumptions_json JSON NULL,
  stress_tests_json JSON NULL,
  calculations_json JSON NULL,
  projection_json JSON NULL,
  narrative TEXT NULL,
  internal_notes TEXT NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_scenarios_public_id (public_id),
  KEY idx_investment_scenarios_workspace (workspace_id,status,updated_at),
  CONSTRAINT fk_investment_scenarios_workspace FOREIGN KEY (workspace_id) REFERENCES investment_workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_scenarios_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_investment_scenarios_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_scenario_budgets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  scenario_id BIGINT UNSIGNED NOT NULL,
  category VARCHAR(100) NOT NULL,
  description VARCHAR(500) NULL,
  amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  priority ENUM('critical','high','normal','optional') NOT NULL DEFAULT 'normal',
  investor_visible TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_investment_budget_scenario (scenario_id,sort_order),
  CONSTRAINT fk_investment_budget_scenario FOREIGN KEY (scenario_id) REFERENCES investment_scenarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_scenario_goals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  scenario_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  rationale TEXT NULL,
  metric_key VARCHAR(120) NULL,
  baseline_value DECIMAL(20,4) NULL,
  target_value DECIMAL(20,4) NULL,
  unit VARCHAR(40) NULL,
  budget_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  target_date DATE NULL,
  status ENUM('planned','active','at_risk','achieved','missed','cancelled') NOT NULL DEFAULT 'planned',
  investor_visible TINYINT(1) NOT NULL DEFAULT 1,
  public_description TEXT NULL,
  internal_notes TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_goals_public_id (public_id),
  KEY idx_investment_goals_scenario (scenario_id,sort_order),
  CONSTRAINT fk_investment_goals_scenario FOREIGN KEY (scenario_id) REFERENCES investment_scenarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_rounds (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  adopted_scenario_id BIGINT UNSIGNED NULL,
  internal_name VARCHAR(180) NOT NULL,
  public_name VARCHAR(180) NOT NULL,
  status ENUM('planning','awaiting_counsel','private_preview','open','minimum_reached','closing','closed','paused','cancelled') NOT NULL DEFAULT 'planning',
  visibility ENUM('super_admin','approved_investors','selected_investors','funded_investors','public_summary') NOT NULL DEFAULT 'super_admin',
  instrument_type VARCHAR(60) NOT NULL DEFAULT 'not_finalized',
  minimum_raise_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  target_raise_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  maximum_raise_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  valuation_cap_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  discount_bps INT UNSIGNED NOT NULL DEFAULT 0,
  minimum_investment_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  soft_commitment_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  signed_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  funded_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  opens_at DATETIME NULL,
  target_close_at DATETIME NULL,
  final_close_at DATETIME NULL,
  counsel_status ENUM('not_started','drafting','under_review','approved') NOT NULL DEFAULT 'not_started',
  offering_exemption VARCHAR(120) NULL,
  general_solicitation TINYINT(1) NOT NULL DEFAULT 0,
  accredited_investors_required TINYINT(1) NULL,
  first_sale_at DATETIME NULL,
  form_d_status VARCHAR(80) NULL,
  snapshot_json JSON NOT NULL,
  published_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_rounds_public_id (public_id),
  KEY idx_investment_rounds_status (status,visibility,updated_at),
  CONSTRAINT fk_investment_rounds_workspace FOREIGN KEY (workspace_id) REFERENCES investment_workspaces(id) ON DELETE RESTRICT,
  CONSTRAINT fk_investment_rounds_scenario FOREIGN KEY (adopted_scenario_id) REFERENCES investment_scenarios(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_rounds_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_investment_rounds_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_round_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  round_id BIGINT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  snapshot_json JSON NOT NULL,
  change_reason VARCHAR(500) NOT NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_round_version (round_id,version_number),
  CONSTRAINT fk_investment_round_versions_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_round_versions_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_metrics (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  metric_key VARCHAR(120) NOT NULL,
  name VARCHAR(180) NOT NULL,
  description TEXT NULL,
  source_system VARCHAR(120) NULL,
  calculation_method TEXT NULL,
  unit VARCHAR(40) NULL,
  value_type ENUM('actual','projected','manual') NOT NULL DEFAULT 'manual',
  confidence ENUM('verified','system_calculated','admin_confirmed','estimated','projected','unavailable') NOT NULL DEFAULT 'unavailable',
  current_value DECIMAL(20,4) NULL,
  investor_visible TINYINT(1) NOT NULL DEFAULT 0,
  refresh_frequency VARCHAR(40) NULL,
  last_calculated_at DATETIME NULL,
  last_verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_metrics_public_id (public_id),
  UNIQUE KEY uq_investment_metrics_workspace_key (workspace_id,metric_key),
  CONSTRAINT fk_investment_metrics_workspace FOREIGN KEY (workspace_id) REFERENCES investment_workspaces(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_metric_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  metric_id BIGINT UNSIGNED NOT NULL,
  round_id BIGINT UNSIGNED NULL,
  snapshot_type ENUM('round_start','monthly','quarterly','closing','manual') NOT NULL DEFAULT 'manual',
  value DECIMAL(20,4) NULL,
  confidence VARCHAR(40) NOT NULL,
  definition_version VARCHAR(40) NULL,
  source_reference VARCHAR(500) NULL,
  snapshot_at DATETIME NOT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_investment_metric_snapshots (metric_id,snapshot_at),
  CONSTRAINT fk_investment_metric_snapshots_metric FOREIGN KEY (metric_id) REFERENCES investment_metrics(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_metric_snapshots_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_metric_snapshots_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  round_id BIGINT UNSIGNED NULL,
  document_type VARCHAR(100) NOT NULL,
  title VARCHAR(180) NOT NULL,
  status ENUM('missing','draft','internal_review','counsel_review','approved','published','superseded','archived') NOT NULL DEFAULT 'missing',
  storage_path VARCHAR(500) NULL,
  external_url VARCHAR(500) NULL,
  visibility ENUM('super_admin','approved_investors','selected_investors','funded_investors','public_summary') NOT NULL DEFAULT 'super_admin',
  notes TEXT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_documents_public_id (public_id),
  KEY idx_investment_documents_workspace (workspace_id,status),
  CONSTRAINT fk_investment_documents_workspace FOREIGN KEY (workspace_id) REFERENCES investment_workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_documents_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_documents_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_ai_analyses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  scenario_id BIGINT UNSIGNED NULL,
  round_id BIGINT UNSIGNED NULL,
  requested_by_user_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(40) NOT NULL DEFAULT 'anthropic',
  model VARCHAR(120) NOT NULL,
  analysis_type VARCHAR(80) NOT NULL,
  input_snapshot_json JSON NOT NULL,
  response_text MEDIUMTEXT NULL,
  structured_json JSON NULL,
  status ENUM('requested','completed','failed','accepted','edited','rejected') NOT NULL DEFAULT 'requested',
  error_message VARCHAR(1000) NULL,
  input_tokens INT UNSIGNED NULL,
  output_tokens INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_ai_public_id (public_id),
  KEY idx_investment_ai_workspace (workspace_id,created_at),
  CONSTRAINT fk_investment_ai_workspace FOREIGN KEY (workspace_id) REFERENCES investment_workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_ai_scenario FOREIGN KEY (scenario_id) REFERENCES investment_scenarios(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_ai_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_ai_user FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_round_access (
  round_id BIGINT UNSIGNED NOT NULL,
  investor_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('granted','revoked','expired') NOT NULL DEFAULT 'granted',
  granted_by_user_id BIGINT UNSIGNED NULL,
  granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  revoked_at DATETIME NULL,
  PRIMARY KEY (round_id,investor_user_id),
  CONSTRAINT fk_investment_round_access_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_round_access_user FOREIGN KEY (investor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_round_access_granter FOREIGN KEY (granted_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS mg_investor_privacy_after_user_update;
DELIMITER $$
CREATE TRIGGER mg_investor_privacy_after_user_update
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
  IF NEW.privacy_state IN ('restricted','anonymized') AND OLD.privacy_state <> NEW.privacy_state THEN
    UPDATE investor_profiles
      SET status = IF(NEW.privacy_state='anonymized','closed','suspended'), updated_at=NOW()
      WHERE user_id=NEW.id;
    UPDATE investment_round_access
      SET status='revoked', revoked_at=NOW()
      WHERE investor_user_id=NEW.id AND status='granted';
  END IF;
  IF NEW.privacy_state='anonymized' AND OLD.privacy_state <> 'anonymized' THEN
    UPDATE investor_access_requests
      SET firm_name='Erased investor', job_title=NULL, website_url=NULL,
          primary_social_url='https://microgifter.com/privacy', linkedin_url=NULL,
          additional_social_url=NULL, referral_source=NULL, phone=NULL,
          request_reason='Retained minimum investor-access decision evidence.',
          review_notes=NULL, more_information_message=NULL, updated_at=NOW()
      WHERE user_id=NEW.id;
    UPDATE investor_profiles
      SET firm_name='Erased investor', job_title=NULL, website_url=NULL,
          primary_social_url=NULL, notes=NULL, updated_at=NOW()
      WHERE user_id=NEW.id;
  END IF;
END$$
DELIMITER ;

INSERT IGNORE INTO privacy_retention_policies
(policy_key,data_category,default_action,retention_days,jurisdiction,legal_basis,is_enabled,created_at,updated_at)
VALUES
('investor_access_profile','Investor access requests and non-financial profile data','anonymize',2555,'global','Retain minimum access-decision and audit evidence while removing unnecessary identity details.',1,NOW(),NOW()),
('investment_round_records','Official round versions, corporate planning and published investor evidence','retain',2555,'global','Retain corporate, accounting, securities-law, audit and legal-claim evidence.',1,NOW(),NOW());

INSERT IGNORE INTO schema_migrations (migration_key,description,applied_at)
VALUES ('20260723_investor_role_investment_wizard_v1','Adds Investor access, multi-scenario investment planning, official round snapshots, evidence metrics and draft-only Claude analysis.',NOW());
