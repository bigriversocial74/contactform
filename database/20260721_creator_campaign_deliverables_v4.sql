-- Creator Campaign Deliverables and Content Review v4
-- Scope: campaign deliverable definitions, participant assignments, creator submissions,
-- immutable revisions, publication proof, merchant review, verification, assets, and permissions.
-- Tracking, CRM attribution, compensation, earnings, payouts, disputes, and MCP remain later phases.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS creator_campaign_deliverables (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  deliverable_type ENUM('photo','short_video','long_video','story','reel','post','article','audio','livestream','event_appearance','product_review','other') NOT NULL,
  platform VARCHAR(80) NULL,
  content_format VARCHAR(120) NULL,
  quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  instructions MEDIUMTEXT NULL,
  required_talking_points_json JSON NULL,
  required_disclosures_json JSON NULL,
  publication_required TINYINT(1) NOT NULL DEFAULT 0,
  proof_required TINYINT(1) NOT NULL DEFAULT 0,
  merchant_review_required TINYINT(1) NOT NULL DEFAULT 1,
  revision_limit SMALLINT UNSIGNED NOT NULL DEFAULT 2,
  due_offset_days SMALLINT UNSIGNED NULL,
  due_at DATETIME NULL,
  status ENUM('draft','active','retired') NOT NULL DEFAULT 'draft',
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_deliverable_public (public_id),
  KEY idx_cc_deliverable_campaign_status (campaign_id,status,sort_order,id),
  CONSTRAINT fk_cc_deliverable_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_deliverable_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_deliverable_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_participant_deliverables (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  campaign_deliverable_id BIGINT UNSIGNED NOT NULL,
  agreement_version_id BIGINT UNSIGNED NOT NULL,
  creator_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('assigned','in_progress','submitted','revision_requested','approved','rejected','published','verified','waived','cancelled') NOT NULL DEFAULT 'assigned',
  assigned_at DATETIME NOT NULL,
  due_at DATETIME NULL,
  started_at DATETIME NULL,
  submitted_at DATETIME NULL,
  approved_at DATETIME NULL,
  rejected_at DATETIME NULL,
  published_at DATETIME NULL,
  verified_at DATETIME NULL,
  waived_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  status_reason VARCHAR(2000) NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_participant_deliverable_public (public_id),
  UNIQUE KEY uq_cc_participant_deliverable_assignment (participant_id,campaign_deliverable_id),
  KEY idx_cc_participant_deliverable_campaign (campaign_id,status,due_at,id),
  KEY idx_cc_participant_deliverable_creator (creator_user_id,status,due_at,id),
  KEY idx_cc_participant_deliverable_agreement (agreement_version_id,id),
  CONSTRAINT fk_cc_pd_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_pd_participant FOREIGN KEY (participant_id) REFERENCES creator_campaign_participants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_pd_deliverable FOREIGN KEY (campaign_deliverable_id) REFERENCES creator_campaign_deliverables(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_pd_agreement_version FOREIGN KEY (agreement_version_id) REFERENCES creator_campaign_agreement_versions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_pd_creator FOREIGN KEY (creator_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_pd_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_pd_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_submissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  participant_deliverable_id BIGINT UNSIGNED NOT NULL,
  agreement_version_id BIGINT UNSIGNED NOT NULL,
  creator_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('draft','submitted','under_review','revision_requested','approved','rejected','published','proof_submitted','verified','withdrawn') NOT NULL DEFAULT 'draft',
  caption_text MEDIUMTEXT NULL,
  content_url VARCHAR(1000) NULL,
  platform VARCHAR(80) NULL,
  disclosure_text VARCHAR(1000) NULL,
  creator_note VARCHAR(2000) NULL,
  merchant_feedback MEDIUMTEXT NULL,
  publication_url VARCHAR(1000) NULL,
  publication_platform VARCHAR(80) NULL,
  publication_identifier VARCHAR(255) NULL,
  current_revision_number INT UNSIGNED NOT NULL DEFAULT 0,
  submitted_at DATETIME NULL,
  reviewed_at DATETIME NULL,
  approved_at DATETIME NULL,
  rejected_at DATETIME NULL,
  published_at DATETIME NULL,
  proof_submitted_at DATETIME NULL,
  verified_at DATETIME NULL,
  withdrawn_at DATETIME NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_submission_public (public_id),
  UNIQUE KEY uq_cc_submission_assignment (participant_deliverable_id),
  KEY idx_cc_submission_campaign_status (campaign_id,status,updated_at,id),
  KEY idx_cc_submission_creator_status (creator_user_id,status,updated_at,id),
  KEY idx_cc_submission_participant (participant_id,updated_at,id),
  CONSTRAINT fk_cc_submission_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_submission_participant FOREIGN KEY (participant_id) REFERENCES creator_campaign_participants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_submission_pd FOREIGN KEY (participant_deliverable_id) REFERENCES creator_campaign_participant_deliverables(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_submission_agreement_version FOREIGN KEY (agreement_version_id) REFERENCES creator_campaign_agreement_versions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_submission_creator FOREIGN KEY (creator_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_submission_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_submission_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_submission_revisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  submission_id BIGINT UNSIGNED NOT NULL,
  participant_deliverable_id BIGINT UNSIGNED NOT NULL,
  agreement_version_id BIGINT UNSIGNED NOT NULL,
  revision_number INT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  change_type ENUM('creator_save','creator_submit','creator_withdraw','merchant_review','merchant_revision_request','merchant_approve','merchant_reject','publication_proof','merchant_verify') NOT NULL,
  content_snapshot_json JSON NOT NULL,
  feedback TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_submission_revision_public (public_id),
  UNIQUE KEY uq_cc_submission_revision_number (submission_id,revision_number),
  KEY idx_cc_submission_revision_assignment (participant_deliverable_id,created_at,id),
  KEY idx_cc_submission_revision_actor (actor_user_id,created_at,id),
  CONSTRAINT fk_cc_revision_submission FOREIGN KEY (submission_id) REFERENCES creator_campaign_submissions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_revision_pd FOREIGN KEY (participant_deliverable_id) REFERENCES creator_campaign_participant_deliverables(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_revision_agreement_version FOREIGN KEY (agreement_version_id) REFERENCES creator_campaign_agreement_versions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_revision_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_assets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  submission_id BIGINT UNSIGNED NOT NULL,
  revision_id BIGINT UNSIGNED NULL,
  asset_type ENUM('content_file','thumbnail','screenshot','publication_proof','external_url') NOT NULL,
  storage_key VARCHAR(500) NULL,
  original_name VARCHAR(255) NULL,
  mime_type VARCHAR(120) NULL,
  size_bytes BIGINT UNSIGNED NULL,
  external_url VARCHAR(1000) NULL,
  content_hash CHAR(64) NULL,
  metadata_json JSON NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_asset_public (public_id),
  KEY idx_cc_asset_submission (submission_id,created_at,id),
  KEY idx_cc_asset_revision (revision_id,id),
  CONSTRAINT fk_cc_asset_submission FOREIGN KEY (submission_id) REFERENCES creator_campaign_submissions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_asset_revision FOREIGN KEY (revision_id) REFERENCES creator_campaign_submission_revisions(id) ON DELETE SET NULL,
  CONSTRAINT fk_cc_asset_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.creator_deliverables.view','View creator deliverables','View deliverable definitions and participant assignments for creator campaigns owned by the active merchant workspace.',NOW()),
('merchant.creator_deliverables.manage','Manage creator deliverables','Create, update, retire, assign, waive, and cancel creator campaign deliverables.',NOW()),
('merchant.creator_submissions.view','View creator submissions','View creator content submissions and immutable revision history.',NOW()),
('merchant.creator_submissions.review','Review creator submissions','Approve, reject, request revisions, and verify creator publication proof.',NOW()),
('creator.campaign_deliverables.view_own','View own campaign deliverables','View deliverables assigned to the authenticated Creator account.',NOW()),
('creator.campaign_submissions.manage_own','Manage own campaign submissions','Save, submit, revise, withdraw, and provide publication proof for the authenticated Creator account.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN (
  'merchant.creator_deliverables.view',
  'merchant.creator_deliverables.manage',
  'merchant.creator_submissions.view',
  'merchant.creator_submissions.review'
)
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN (
  'creator.campaign_deliverables.view_own',
  'creator.campaign_submissions.manage_own'
)
WHERE r.slug IN ('creator','admin','super_admin');

COMMIT;
