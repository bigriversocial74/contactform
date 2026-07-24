-- Microgifter Investor Due Diligence, Data Room & Communications v3
-- Additive single-install migration. Requires Investor Wizard v1 and Investor Pipeline v2.
-- This module does not issue securities, verify accreditation, process funds, sign documents, or provide legal approval.

INSERT INTO permissions (slug,name,created_at) VALUES
('investment.diligence.view','View investor data room, Q&A, diligence responses and communications',NOW()),
('investment.diligence.submit','Submit investor diligence questions and document requests',NOW()),
('investment.interest.submit','Submit a non-binding indication of investment interest',NOW()),
('admin.investment.diligence.view','View investor diligence administration',NOW()),
('admin.investment.diligence.manage','Manage data room, diligence, Q&A, meetings and communications',NOW()),
('admin.investment.diligence.publish','Approve and publish investor diligence materials',NOW()),
('admin.investment.engagement.view','View deterministic investor engagement analytics',NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN
('investment.diligence.view','investment.diligence.submit','investment.interest.submit')
WHERE r.slug='investor';

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN
('admin.investment.diligence.view','admin.investment.diligence.manage','admin.investment.diligence.publish','admin.investment.engagement.view')
WHERE r.slug IN ('admin','super_admin');

CREATE TABLE IF NOT EXISTS investment_dataroom_folders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  round_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  category VARCHAR(80) NOT NULL,
  description TEXT NULL,
  visibility ENUM('approved_investors','selected_investors','funded_investors') NOT NULL DEFAULT 'approved_investors',
  status ENUM('active','hidden','archived') NOT NULL DEFAULT 'active',
  sort_order INT NOT NULL DEFAULT 0,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_dataroom_folder_public (public_id),
  KEY idx_investment_dataroom_folder_round (round_id,status,sort_order),
  CONSTRAINT fk_investment_dataroom_folder_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_dataroom_folder_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_dataroom_documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  round_id BIGINT UNSIGNED NOT NULL,
  folder_id BIGINT UNSIGNED NULL,
  investment_document_id BIGINT UNSIGNED NULL,
  title VARCHAR(220) NOT NULL,
  internal_description TEXT NULL,
  investor_description TEXT NULL,
  classification ENUM('standard','confidential','highly_sensitive') NOT NULL DEFAULT 'standard',
  status ENUM('draft','internal_review','legal_review','approved','published','superseded','archived') NOT NULL DEFAULT 'draft',
  visibility ENUM('approved_investors','selected_investors','funded_investors') NOT NULL DEFAULT 'approved_investors',
  external_url VARCHAR(500) NULL,
  download_allowed TINYINT(1) NOT NULL DEFAULT 1,
  requires_legal_review TINYINT(1) NOT NULL DEFAULT 0,
  expires_at DATETIME NULL,
  current_version_number INT UNSIGNED NOT NULL DEFAULT 1,
  approved_by_user_id BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  published_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_dataroom_document_public (public_id),
  KEY idx_investment_dataroom_document_round (round_id,status,visibility),
  KEY idx_investment_dataroom_document_folder (folder_id,status),
  CONSTRAINT fk_investment_dataroom_document_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_dataroom_document_folder FOREIGN KEY (folder_id) REFERENCES investment_dataroom_folders(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_dataroom_document_source FOREIGN KEY (investment_document_id) REFERENCES investment_documents(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_dataroom_document_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_dataroom_document_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_dataroom_document_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_dataroom_document_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id BIGINT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  external_url VARCHAR(500) NULL,
  version_label VARCHAR(120) NULL,
  notes TEXT NULL,
  status ENUM('draft','internal_review','legal_review','approved','published','superseded') NOT NULL DEFAULT 'draft',
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_dataroom_version (document_id,version_number),
  CONSTRAINT fk_investment_dataroom_version_document FOREIGN KEY (document_id) REFERENCES investment_dataroom_documents(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_dataroom_version_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investor_diligence_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  round_id BIGINT UNSIGNED NOT NULL,
  investor_user_id BIGINT UNSIGNED NOT NULL,
  category ENUM('company','corporate','capitalization','financial','product','technology','market','operations','contracts','intellectual_property','risk','round_terms','documents','other') NOT NULL DEFAULT 'other',
  request_type ENUM('question','document_request','clarification') NOT NULL DEFAULT 'question',
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status ENUM('submitted','acknowledged','assigned','researching','draft_response','internal_review','legal_review','answered','closed','declined') NOT NULL DEFAULT 'submitted',
  subject VARCHAR(220) NOT NULL,
  request_text TEXT NOT NULL,
  assigned_user_id BIGINT UNSIGNED NULL,
  due_at DATETIME NULL,
  internal_notes TEXT NULL,
  approved_response MEDIUMTEXT NULL,
  answered_by_user_id BIGINT UNSIGNED NULL,
  answered_at DATETIME NULL,
  closed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investor_diligence_request_public (public_id),
  KEY idx_investor_diligence_request_queue (status,priority,due_at),
  KEY idx_investor_diligence_request_investor (investor_user_id,round_id,created_at),
  CONSTRAINT fk_investor_diligence_request_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_diligence_request_investor FOREIGN KEY (investor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_diligence_request_assignee FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investor_diligence_request_answerer FOREIGN KEY (answered_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investor_diligence_response_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id BIGINT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  response_text MEDIUMTEXT NOT NULL,
  status ENUM('draft','internal_review','legal_review','approved','published','rejected') NOT NULL DEFAULT 'draft',
  change_reason VARCHAR(500) NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investor_diligence_response_version (request_id,version_number),
  CONSTRAINT fk_investor_diligence_response_request FOREIGN KEY (request_id) REFERENCES investor_diligence_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_diligence_response_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investor_diligence_request_documents (
  request_id BIGINT UNSIGNED NOT NULL,
  document_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (request_id,document_id),
  CONSTRAINT fk_investor_diligence_request_doc_request FOREIGN KEY (request_id) REFERENCES investor_diligence_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_diligence_request_doc_document FOREIGN KEY (document_id) REFERENCES investment_dataroom_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_qa_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  round_id BIGINT UNSIGNED NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'general',
  question VARCHAR(500) NOT NULL,
  answer MEDIUMTEXT NOT NULL,
  status ENUM('draft','internal_review','legal_review','approved','published','archived') NOT NULL DEFAULT 'draft',
  requires_legal_review TINYINT(1) NOT NULL DEFAULT 0,
  source_request_id BIGINT UNSIGNED NULL,
  version_number INT UNSIGNED NOT NULL DEFAULT 1,
  published_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investment_qa_public (public_id),
  KEY idx_investment_qa_round_status (round_id,status,category),
  CONSTRAINT fk_investment_qa_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investment_qa_request FOREIGN KEY (source_request_id) REFERENCES investor_diligence_requests(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_qa_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investment_qa_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investor_meetings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  round_id BIGINT UNSIGNED NULL,
  investor_user_id BIGINT UNSIGNED NOT NULL,
  meeting_type ENUM('intro','follow_up','demo','diligence','terms','closing','other') NOT NULL DEFAULT 'intro',
  status ENUM('planned','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'planned',
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  location VARCHAR(300) NULL,
  meeting_url VARCHAR(500) NULL,
  attendees_json JSON NULL,
  agenda TEXT NULL,
  preparation_notes TEXT NULL,
  outcome TEXT NULL,
  sentiment ENUM('unknown','negative','neutral','positive','strong') NOT NULL DEFAULT 'unknown',
  next_step VARCHAR(500) NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investor_meeting_public (public_id),
  KEY idx_investor_meeting_investor (investor_user_id,starts_at,status),
  CONSTRAINT fk_investor_meeting_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE SET NULL,
  CONSTRAINT fk_investor_meeting_investor FOREIGN KEY (investor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_meeting_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investor_meeting_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investor_communications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  round_id BIGINT UNSIGNED NULL,
  communication_type ENUM('individual_follow_up','selected_investor_update','round_update','monthly_report','milestone_update','document_announcement','important_notice','meeting_recap') NOT NULL DEFAULT 'round_update',
  audience_type ENUM('individual','selected_investors','approved_investors','funded_investors') NOT NULL DEFAULT 'approved_investors',
  subject VARCHAR(220) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  status ENUM('draft','internal_review','legal_review','approved','published','archived') NOT NULL DEFAULT 'draft',
  requires_legal_review TINYINT(1) NOT NULL DEFAULT 0,
  published_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investor_communication_public (public_id),
  KEY idx_investor_communication_round_status (round_id,status,published_at),
  CONSTRAINT fk_investor_communication_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_communication_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investor_communication_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investor_communication_recipients (
  communication_id BIGINT UNSIGNED NOT NULL,
  investor_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('eligible','published','viewed','revoked') NOT NULL DEFAULT 'eligible',
  published_at DATETIME NULL,
  first_viewed_at DATETIME NULL,
  last_viewed_at DATETIME NULL,
  view_count INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (communication_id,investor_user_id),
  CONSTRAINT fk_investor_communication_recipient_message FOREIGN KEY (communication_id) REFERENCES investor_communications(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_communication_recipient_user FOREIGN KEY (investor_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investor_interest_submissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  round_id BIGINT UNSIGNED NOT NULL,
  investor_user_id BIGINT UNSIGNED NOT NULL,
  proposed_range VARCHAR(80) NOT NULL,
  expected_timing VARCHAR(120) NULL,
  questions_conditions TEXT NULL,
  preferred_next_step VARCHAR(220) NULL,
  meeting_requested TINYINT(1) NOT NULL DEFAULT 0,
  acknowledgement_at DATETIME NOT NULL,
  status ENUM('submitted','reviewing','accepted_for_discussion','declined','converted','withdrawn') NOT NULL DEFAULT 'submitted',
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  internal_notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investor_interest_public (public_id),
  KEY idx_investor_interest_queue (status,created_at),
  KEY idx_investor_interest_investor (investor_user_id,round_id,created_at),
  CONSTRAINT fk_investor_interest_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_interest_user FOREIGN KEY (investor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_interest_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investor_engagement_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  investor_user_id BIGINT UNSIGNED NOT NULL,
  round_id BIGINT UNSIGNED NULL,
  portal_sessions INT UNSIGNED NOT NULL DEFAULT 0,
  round_views INT UNSIGNED NOT NULL DEFAULT 0,
  document_views INT UNSIGNED NOT NULL DEFAULT 0,
  unique_documents_viewed INT UNSIGNED NOT NULL DEFAULT 0,
  metric_views INT UNSIGNED NOT NULL DEFAULT 0,
  questions_submitted INT UNSIGNED NOT NULL DEFAULT 0,
  communications_viewed INT UNSIGNED NOT NULL DEFAULT 0,
  meetings_completed INT UNSIGNED NOT NULL DEFAULT 0,
  days_since_last_engagement INT UNSIGNED NULL,
  engagement_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  stalled TINYINT(1) NOT NULL DEFAULT 0,
  calculation_json JSON NOT NULL,
  snapshot_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investor_engagement_public (public_id),
  KEY idx_investor_engagement_investor (investor_user_id,snapshot_at),
  CONSTRAINT fk_investor_engagement_user FOREIGN KEY (investor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_engagement_round FOREIGN KEY (round_id) REFERENCES investment_rounds(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (migration_key,description,applied_at)
VALUES ('20260723_investor_diligence_communications_v3','Adds governed data room, diligence requests, Q&A, meetings, portal communications, non-binding interest and engagement snapshots.',NOW());
