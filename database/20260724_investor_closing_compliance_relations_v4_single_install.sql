-- Microgifter Investor Closing, Compliance & Post-Investment Relations v4
-- Additive single-install migration. Requires Investor Wizard v1, Pipeline v2, and Diligence v3.
-- This module does not process funds, verify identity/accreditation, sign documents, submit filings,
-- issue securities, replace the legal stock ledger, or provide legal advice.

INSERT INTO permissions (slug,name,created_at) VALUES
('investment.closing.view','View funded-investor closing confirmations and post-investment reports',NOW()),
('investment.relations.view','View funded-investor relations updates and reporting periods',NOW()),
('admin.investment.closing.view','View investment closing administration',NOW()),
('admin.investment.closing.manage','Manage investor closing records, batches, packets and reconciliation',NOW()),
('admin.investment.closing.verify','Approve maker-checker signed and funded verification requests',NOW()),
('admin.investment.compliance.manage','Manage counsel-supplied compliance and filing records',NOW()),
('admin.investment.relations.manage','Manage post-investment reporting and funded-investor relations',NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN
('investment.closing.view','investment.relations.view')
WHERE r.slug='investor';

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN
('admin.investment.closing.view','admin.investment.closing.manage','admin.investment.closing.verify','admin.investment.compliance.manage','admin.investment.relations.manage')
WHERE r.slug IN ('admin','super_admin');

CREATE TABLE IF NOT EXISTS investment_closing_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  round_id BIGINT UNSIGNED NOT NULL,
  stage ENUM('planning','pre_closing_review','documents_ready','investor_signing','funding_pending','rolling_close','final_close','post_close_review','complete','paused','cancelled') NOT NULL DEFAULT 'planning',
  readiness_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  counsel_status ENUM('not_started','requested','in_review','approved','changes_required','not_applicable') NOT NULL DEFAULT 'not_started',
  board_status ENUM('not_started','requested','approved','changes_required','not_applicable') NOT NULL DEFAULT 'not_started',
  planned_first_close_at DATETIME NULL,
  planned_final_close_at DATETIME NULL,
  actual_final_close_at DATETIME NULL,
  blockers_json JSON NULL,
  closing_notes TEXT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_closing_profile_public (public_id),
  UNIQUE KEY uq_investment_closing_profile_round (round_id),
  KEY idx_investment_closing_profile_stage (stage,updated_at),
  CONSTRAINT fk_investment_closing_profile_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_closing_profile_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_closing_profile_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_closing_batches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  round_id BIGINT UNSIGNED NOT NULL,
  batch_name VARCHAR(180) NOT NULL,
  sequence_number INT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('planning','review','ready','closing','completed','reopened','cancelled') NOT NULL DEFAULT 'planning',
  planned_close_at DATETIME NULL,
  actual_close_at DATETIME NULL,
  counsel_status ENUM('not_started','in_review','approved','changes_required','not_applicable') NOT NULL DEFAULT 'not_started',
  board_status ENUM('not_started','in_review','approved','changes_required','not_applicable') NOT NULL DEFAULT 'not_started',
  included_amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  notes TEXT NULL,
  locked_at DATETIME NULL,
  locked_by_user_id BIGINT UNSIGNED NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_closing_batch_public (public_id),
  UNIQUE KEY uq_investment_closing_batch_sequence (round_id,sequence_number),
  KEY idx_investment_closing_batch_round (round_id,status,planned_close_at),
  CONSTRAINT fk_investment_closing_batch_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_closing_batch_locker FOREIGN KEY (locked_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_closing_batch_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_closing_batch_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investor_closing_records (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  round_id BIGINT UNSIGNED NOT NULL,
  investor_user_id BIGINT UNSIGNED NOT NULL,
  batch_id BIGINT UNSIGNED NULL,
  status ENUM('interested','soft_committed','documents_requested','documents_sent','investor_reviewing','signed','funding_pending','funds_reported','funds_verified','included_in_closing','closing_complete','withdrawn','declined') NOT NULL DEFAULT 'interested',
  instrument_type ENUM('not_finalized','post_money_safe','convertible_note','priced_equity','other') NOT NULL DEFAULT 'not_finalized',
  proposed_amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  final_amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  signed_amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  reported_funded_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  verified_funded_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  agreement_reference VARCHAR(220) NULL,
  funding_reference VARCHAR(220) NULL,
  documents_sent_at DATETIME NULL,
  investor_signed_at DATETIME NULL,
  company_countersigned_at DATETIME NULL,
  funds_reported_at DATETIME NULL,
  funds_verified_at DATETIME NULL,
  closing_completed_at DATETIME NULL,
  verified_by_user_id BIGINT UNSIGNED NULL,
  internal_notes TEXT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investor_closing_record_public (public_id),
  UNIQUE KEY uq_investor_closing_record_round_user (round_id,investor_user_id),
  KEY idx_investor_closing_record_status (round_id,status,updated_at),
  KEY idx_investor_closing_record_batch (batch_id,status),
  CONSTRAINT fk_investor_closing_record_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_closing_record_user FOREIGN KEY (investor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_closing_record_batch FOREIGN KEY (batch_id) REFERENCES investment_closing_batches(id) ON DELETE SET NULL,
  CONSTRAINT fk_investor_closing_record_verifier FOREIGN KEY (verified_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investor_closing_record_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investor_closing_record_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investor_closing_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  closing_record_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  event_type ENUM('created','status_changed','terms_updated','documents_sent','signature_reported','funding_reported','funding_verified','batch_assigned','closing_completed','adjustment','note') NOT NULL,
  from_status VARCHAR(60) NULL,
  to_status VARCHAR(60) NULL,
  amount_cents BIGINT UNSIGNED NULL,
  details_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investor_closing_event_public (public_id),
  KEY idx_investor_closing_event_record (closing_record_id,created_at),
  CONSTRAINT fk_investor_closing_event_record FOREIGN KEY (closing_record_id) REFERENCES investor_closing_records(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_closing_event_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_closing_batch_investors (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  batch_id BIGINT UNSIGNED NOT NULL,
  closing_record_id BIGINT UNSIGNED NOT NULL,
  included_amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  added_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_closing_batch_record (batch_id,closing_record_id),
  KEY idx_investment_closing_batch_investor_record (closing_record_id),
  CONSTRAINT fk_investment_closing_batch_investor_batch FOREIGN KEY (batch_id) REFERENCES investment_closing_batches(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_closing_batch_investor_record FOREIGN KEY (closing_record_id) REFERENCES investor_closing_records(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_closing_batch_investor_adder FOREIGN KEY (added_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_compliance_requirements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  round_id BIGINT UNSIGNED NOT NULL,
  requirement_key VARCHAR(120) NOT NULL,
  category ENUM('exemption','federal_filing','state_notice','board_approval','counsel_review','investor_notice','tax','other') NOT NULL DEFAULT 'other',
  title VARCHAR(220) NOT NULL,
  description TEXT NULL,
  status ENUM('not_started','requested','in_progress','filed','confirmed','approved','changes_required','not_applicable','overdue') NOT NULL DEFAULT 'not_started',
  counsel_required TINYINT(1) NOT NULL DEFAULT 0,
  due_at DATETIME NULL,
  completed_at DATETIME NULL,
  external_reference VARCHAR(220) NULL,
  external_url VARCHAR(500) NULL,
  assigned_user_id BIGINT UNSIGNED NULL,
  notes TEXT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_compliance_public (public_id),
  UNIQUE KEY uq_investment_compliance_round_key (round_id,requirement_key),
  KEY idx_investment_compliance_due (round_id,status,due_at),
  CONSTRAINT fk_investment_compliance_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_compliance_assignee FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_compliance_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_compliance_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_compliance_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  requirement_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  event_type ENUM('created','status_changed','assigned','deadline_changed','reference_added','completed','reopened','note') NOT NULL,
  details_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_compliance_event_public (public_id),
  KEY idx_investment_compliance_event_requirement (requirement_id,created_at),
  CONSTRAINT fk_investment_compliance_event_requirement FOREIGN KEY (requirement_id) REFERENCES investment_compliance_requirements(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_compliance_event_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investor_onboarding_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  round_id BIGINT UNSIGNED NOT NULL,
  investor_user_id BIGINT UNSIGNED NOT NULL,
  investor_entity_type ENUM('individual','entity','trust','fund','other') NOT NULL DEFAULT 'individual',
  legal_name VARCHAR(220) NOT NULL,
  organization_name VARCHAR(220) NULL,
  authorized_signatory VARCHAR(220) NULL,
  tax_country VARCHAR(120) NULL,
  tax_document_status ENUM('not_requested','requested','received','reviewed','expired','not_applicable') NOT NULL DEFAULT 'not_requested',
  beneficial_owner_status ENUM('not_requested','requested','received','reviewed','issues_found','not_applicable') NOT NULL DEFAULT 'not_requested',
  kyc_status ENUM('not_started','submitted_external','pending_external','passed_external','failed_external','expired','not_applicable') NOT NULL DEFAULT 'not_started',
  kyc_provider_reference VARCHAR(220) NULL,
  accreditation_status ENUM('not_started','submitted_external','pending_external','verified_external','not_verified','expired','not_required') NOT NULL DEFAULT 'not_started',
  accreditation_provider VARCHAR(220) NULL,
  accreditation_reviewed_at DATETIME NULL,
  accreditation_expires_at DATETIME NULL,
  counsel_status ENUM('not_started','in_review','approved','changes_required','not_applicable') NOT NULL DEFAULT 'not_started',
  restriction_notes TEXT NULL,
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investor_onboarding_public (public_id),
  UNIQUE KEY uq_investor_onboarding_round_user (round_id,investor_user_id),
  KEY idx_investor_onboarding_status (round_id,kyc_status,accreditation_status,counsel_status),
  CONSTRAINT fk_investor_onboarding_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_onboarding_user FOREIGN KEY (investor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_onboarding_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investor_onboarding_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investor_onboarding_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_closing_packets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  round_id BIGINT UNSIGNED NOT NULL,
  investor_user_id BIGINT UNSIGNED NOT NULL,
  packet_name VARCHAR(220) NOT NULL,
  status ENUM('draft','assembling','investor_review','company_review','counsel_review','complete','archived') NOT NULL DEFAULT 'draft',
  required_document_count INT UNSIGNED NOT NULL DEFAULT 0,
  completed_document_count INT UNSIGNED NOT NULL DEFAULT 0,
  external_packet_reference VARCHAR(220) NULL,
  notes TEXT NULL,
  completed_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_closing_packet_public (public_id),
  KEY idx_investment_closing_packet_round_user (round_id,investor_user_id,status),
  CONSTRAINT fk_investment_closing_packet_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_closing_packet_user FOREIGN KEY (investor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_closing_packet_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_closing_packet_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_closing_documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  packet_id BIGINT UNSIGNED NOT NULL,
  document_type ENUM('investor_questionnaire','subscription_agreement','safe_or_note','accreditation_evidence','tax_form','side_letter','board_consent','countersigned_agreement','funding_confirmation','closing_certificate','form_d_receipt','state_notice_receipt','other') NOT NULL,
  title VARCHAR(220) NOT NULL,
  status ENUM('not_started','requested','received','review','approved','executed','complete','rejected','expired','not_applicable') NOT NULL DEFAULT 'not_started',
  required_document TINYINT(1) NOT NULL DEFAULT 1,
  external_url VARCHAR(500) NULL,
  version_number INT UNSIGNED NOT NULL DEFAULT 1,
  investor_signature_status ENUM('not_required','not_started','sent','signed','declined') NOT NULL DEFAULT 'not_started',
  company_signature_status ENUM('not_required','not_started','signed') NOT NULL DEFAULT 'not_started',
  counsel_status ENUM('not_required','not_started','in_review','approved','changes_required') NOT NULL DEFAULT 'not_started',
  expires_at DATETIME NULL,
  completed_at DATETIME NULL,
  notes TEXT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_closing_document_public (public_id),
  KEY idx_investment_closing_document_packet (packet_id,status,required_document),
  CONSTRAINT fk_investment_closing_document_packet FOREIGN KEY (packet_id) REFERENCES investment_closing_packets(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_closing_document_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_closing_document_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_financial_verification_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  round_id BIGINT UNSIGNED NOT NULL,
  closing_record_id BIGINT UNSIGNED NOT NULL,
  verification_type ENUM('signed_amount','funded_amount','funded_reversal','signed_reversal','adjustment') NOT NULL,
  requested_amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  previous_amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  evidence_reference VARCHAR(220) NULL,
  request_reason TEXT NOT NULL,
  submitted_by_user_id BIGINT UNSIGNED NOT NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_financial_verification_public (public_id),
  KEY idx_investment_financial_verification_pending (round_id,status,submitted_at),
  CONSTRAINT fk_investment_financial_verification_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_financial_verification_record FOREIGN KEY (closing_record_id) REFERENCES investor_closing_records(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_financial_verification_submitter FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_financial_verification_decisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  request_id BIGINT UNSIGNED NOT NULL,
  reviewer_user_id BIGINT UNSIGNED NOT NULL,
  decision ENUM('approved','rejected') NOT NULL,
  decision_notes TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_financial_decision_public (public_id),
  UNIQUE KEY uq_investment_financial_decision_request (request_id),
  CONSTRAINT fk_investment_financial_decision_request FOREIGN KEY (request_id) REFERENCES investment_financial_verification_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_financial_decision_reviewer FOREIGN KEY (reviewer_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_cap_reconciliation_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  round_id BIGINT UNSIGNED NOT NULL,
  snapshot_type ENUM('manual','pre_close','rolling_close','final_close','post_close') NOT NULL DEFAULT 'manual',
  source_scenario_public_id CHAR(36) NULL,
  signed_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  verified_funded_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  target_raise_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  available_capacity_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  modeled_dilution_bps INT UNSIGNED NOT NULL DEFAULT 0,
  actual_estimated_dilution_bps INT UNSIGNED NOT NULL DEFAULT 0,
  inputs_json JSON NOT NULL,
  output_json JSON NOT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_cap_reconciliation_public (public_id),
  KEY idx_investment_cap_reconciliation_round (round_id,created_at),
  CONSTRAINT fk_investment_cap_reconciliation_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_cap_reconciliation_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_reporting_periods (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  round_id BIGINT UNSIGNED NOT NULL,
  period_name VARCHAR(180) NOT NULL,
  period_type ENUM('monthly','quarterly','annual','milestone','closing','other') NOT NULL DEFAULT 'quarterly',
  starts_at DATE NOT NULL,
  ends_at DATE NOT NULL,
  due_at DATETIME NULL,
  status ENUM('planning','collecting','draft','internal_review','approved','published','archived') NOT NULL DEFAULT 'planning',
  published_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_reporting_period_public (public_id),
  UNIQUE KEY uq_investment_reporting_period_round_name (round_id,period_name),
  KEY idx_investment_reporting_period_due (round_id,status,due_at),
  CONSTRAINT fk_investment_reporting_period_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_reporting_period_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_reporting_period_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_reporting_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  reporting_period_id BIGINT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL DEFAULT 1,
  headline VARCHAR(220) NOT NULL,
  narrative LONGTEXT NULL,
  metrics_json JSON NOT NULL,
  use_of_funds_json JSON NOT NULL,
  milestones_json JSON NOT NULL,
  risks_json JSON NOT NULL,
  status ENUM('draft','internal_review','approved','published','superseded','archived') NOT NULL DEFAULT 'draft',
  approved_by_user_id BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  published_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_reporting_snapshot_public (public_id),
  UNIQUE KEY uq_investment_reporting_snapshot_version (reporting_period_id,version_number),
  KEY idx_investment_reporting_snapshot_period (reporting_period_id,status),
  CONSTRAINT fk_investment_reporting_snapshot_period FOREIGN KEY (reporting_period_id) REFERENCES investment_reporting_periods(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_reporting_snapshot_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_reporting_snapshot_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_use_of_funds_actuals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  round_id BIGINT UNSIGNED NOT NULL,
  reporting_period_id BIGINT UNSIGNED NULL,
  budget_category VARCHAR(180) NOT NULL,
  amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  spent_at DATE NOT NULL,
  description TEXT NOT NULL,
  evidence_reference VARCHAR(220) NULL,
  investor_visible TINYINT(1) NOT NULL DEFAULT 0,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_use_of_funds_actual_public (public_id),
  KEY idx_investment_use_of_funds_actual_round (round_id,spent_at),
  CONSTRAINT fk_investment_use_of_funds_actual_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_use_of_funds_actual_period FOREIGN KEY (reporting_period_id) REFERENCES investment_reporting_periods(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_use_of_funds_actual_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (migration_key,description,applied_at)
VALUES ('20260724_investor_closing_compliance_relations_v4','Adds closing command center, investor closing lifecycle, rolling-close batches, counsel-supplied compliance tracking, onboarding reviews, document packets, maker-checker financial verification, capitalization reconciliation and post-investment reporting.',NOW());
