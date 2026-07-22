-- Creator Campaign Participation v3
-- Scope: creator discovery, applications, existing-account invitations, approval workflows,
-- participants, immutable versioned agreements, creator active-campaign workspace, events, and permissions.
-- Deliverables/content review begins in Phase 4; tracking, compensation, payouts, disputes, and MCP remain later phases.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS creator_campaign_applications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  creator_user_id BIGINT UNSIGNED NOT NULL,
  creator_profile_id BIGINT UNSIGNED NOT NULL,
  status ENUM('draft','submitted','under_review','information_requested','approved','declined','withdrawn') NOT NULL DEFAULT 'draft',
  cover_note TEXT NULL,
  portfolio_url VARCHAR(600) NULL,
  creator_snapshot_json JSON NULL,
  decision_note VARCHAR(1000) NULL,
  internal_note TEXT NULL,
  submitted_at DATETIME NULL,
  reviewed_at DATETIME NULL,
  decided_at DATETIME NULL,
  withdrawn_at DATETIME NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_application_public (public_id),
  UNIQUE KEY uq_creator_campaign_application_creator (campaign_id,creator_user_id),
  KEY idx_creator_campaign_application_campaign_status (campaign_id,status,submitted_at,id),
  KEY idx_creator_campaign_application_creator_status (creator_user_id,status,updated_at,id),
  CONSTRAINT fk_creator_campaign_application_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_application_creator FOREIGN KEY (creator_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_application_profile FOREIGN KEY (creator_profile_id) REFERENCES creator_profiles(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_application_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_application_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_application_answers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  application_id BIGINT UNSIGNED NOT NULL,
  question_id BIGINT UNSIGNED NOT NULL,
  answer_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_application_answer_public (public_id),
  UNIQUE KEY uq_creator_campaign_application_answer_question (application_id,question_id),
  KEY idx_creator_campaign_application_answers_application (application_id,id),
  CONSTRAINT fk_creator_campaign_application_answer_application FOREIGN KEY (application_id) REFERENCES creator_campaign_applications(id) ON DELETE CASCADE,
  CONSTRAINT fk_creator_campaign_application_answer_question FOREIGN KEY (question_id) REFERENCES creator_campaign_application_questions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_invitations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  creator_user_id BIGINT UNSIGNED NOT NULL,
  creator_profile_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','accepted','declined','cancelled','expired') NOT NULL DEFAULT 'pending',
  invitation_message TEXT NULL,
  internal_note TEXT NULL,
  response_deadline_at DATETIME NULL,
  sent_at DATETIME NOT NULL,
  responded_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  idempotency_hash CHAR(64) NOT NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_invitation_public (public_id),
  UNIQUE KEY uq_creator_campaign_invitation_creator (campaign_id,creator_user_id),
  UNIQUE KEY uq_creator_campaign_invitation_idempotency (campaign_id,idempotency_hash),
  KEY idx_creator_campaign_invitation_campaign_status (campaign_id,status,response_deadline_at,id),
  KEY idx_creator_campaign_invitation_creator_status (creator_user_id,status,updated_at,id),
  CONSTRAINT fk_creator_campaign_invitation_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_invitation_creator FOREIGN KEY (creator_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_invitation_profile FOREIGN KEY (creator_profile_id) REFERENCES creator_profiles(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_invitation_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_invitation_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_participants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  creator_user_id BIGINT UNSIGNED NOT NULL,
  creator_profile_id BIGINT UNSIGNED NOT NULL,
  source_type ENUM('application','invitation','manual') NOT NULL,
  source_application_id BIGINT UNSIGNED NULL,
  source_invitation_id BIGINT UNSIGNED NULL,
  status ENUM('approved','agreement_pending','active','completed','declined','removed','suspended') NOT NULL DEFAULT 'agreement_pending',
  approved_at DATETIME NULL,
  agreement_pending_at DATETIME NULL,
  activated_at DATETIME NULL,
  completed_at DATETIME NULL,
  removed_at DATETIME NULL,
  suspended_at DATETIME NULL,
  status_reason VARCHAR(1000) NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_participant_public (public_id),
  UNIQUE KEY uq_creator_campaign_participant_creator (campaign_id,creator_user_id),
  KEY idx_creator_campaign_participant_campaign_status (campaign_id,status,updated_at,id),
  KEY idx_creator_campaign_participant_creator_status (creator_user_id,status,updated_at,id),
  CONSTRAINT fk_creator_campaign_participant_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_participant_creator FOREIGN KEY (creator_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_participant_profile FOREIGN KEY (creator_profile_id) REFERENCES creator_profiles(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_participant_application FOREIGN KEY (source_application_id) REFERENCES creator_campaign_applications(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_participant_invitation FOREIGN KEY (source_invitation_id) REFERENCES creator_campaign_invitations(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_participant_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_participant_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_participation_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  application_id BIGINT UNSIGNED NULL,
  invitation_id BIGINT UNSIGNED NULL,
  participant_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  from_status VARCHAR(40) NULL,
  to_status VARCHAR(40) NULL,
  reason VARCHAR(1000) NULL,
  context_json JSON NULL,
  idempotency_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_participation_event_public (public_id),
  UNIQUE KEY uq_creator_campaign_participation_event_idempotency (campaign_id,idempotency_hash),
  KEY idx_creator_campaign_participation_timeline (campaign_id,created_at,id),
  KEY idx_creator_campaign_participation_application (application_id,created_at,id),
  KEY idx_creator_campaign_participation_invitation (invitation_id,created_at,id),
  KEY idx_creator_campaign_participation_participant (participant_id,created_at,id),
  KEY idx_creator_campaign_participation_actor (actor_user_id,created_at,id),
  CONSTRAINT fk_creator_campaign_participation_event_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_participation_event_application FOREIGN KEY (application_id) REFERENCES creator_campaign_applications(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_participation_event_invitation FOREIGN KEY (invitation_id) REFERENCES creator_campaign_invitations(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_participation_event_participant FOREIGN KEY (participant_id) REFERENCES creator_campaign_participants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_participation_event_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_agreements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  creator_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('draft','offered','accepted','declined','cancelled') NOT NULL DEFAULT 'draft',
  current_version_id BIGINT UNSIGNED NULL,
  latest_accepted_version_id BIGINT UNSIGNED NULL,
  offered_at DATETIME NULL,
  accepted_at DATETIME NULL,
  declined_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_agreement_public (public_id),
  UNIQUE KEY uq_creator_campaign_agreement_participant (participant_id),
  KEY idx_creator_campaign_agreement_campaign_status (campaign_id,status,updated_at,id),
  KEY idx_creator_campaign_agreement_creator_status (creator_user_id,status,updated_at,id),
  CONSTRAINT fk_creator_campaign_agreement_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_agreement_participant FOREIGN KEY (participant_id) REFERENCES creator_campaign_participants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_agreement_creator FOREIGN KEY (creator_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_agreement_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_agreement_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_agreement_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  agreement_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  status ENUM('draft','offered','accepted','declined','superseded','cancelled') NOT NULL DEFAULT 'draft',
  summary VARCHAR(1000) NULL,
  terms_text MEDIUMTEXT NOT NULL,
  snapshot_json JSON NOT NULL,
  change_summary VARCHAR(2000) NULL,
  content_hash CHAR(64) NOT NULL,
  requires_reacceptance TINYINT(1) NOT NULL DEFAULT 1,
  offered_at DATETIME NULL,
  accepted_at DATETIME NULL,
  declined_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_agreement_version_public (public_id),
  UNIQUE KEY uq_creator_campaign_agreement_version_number (agreement_id,version_number),
  UNIQUE KEY uq_creator_campaign_agreement_version_hash (agreement_id,content_hash),
  KEY idx_creator_campaign_agreement_version_campaign (campaign_id,created_at,id),
  KEY idx_creator_campaign_agreement_version_status (agreement_id,status,version_number),
  CONSTRAINT fk_creator_campaign_agreement_version_agreement FOREIGN KEY (agreement_id) REFERENCES creator_campaign_agreements(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_agreement_version_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_agreement_version_participant FOREIGN KEY (participant_id) REFERENCES creator_campaign_participants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_agreement_version_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_agreement_acceptances (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  agreement_id BIGINT UNSIGNED NOT NULL,
  agreement_version_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  creator_user_id BIGINT UNSIGNED NOT NULL,
  decision ENUM('accepted','declined') NOT NULL,
  content_hash CHAR(64) NOT NULL,
  response_note VARCHAR(2000) NULL,
  request_context_json JSON NULL,
  decided_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_agreement_acceptance_public (public_id),
  UNIQUE KEY uq_creator_campaign_agreement_acceptance_decision (agreement_version_id,creator_user_id),
  KEY idx_creator_campaign_agreement_acceptance_agreement (agreement_id,decided_at,id),
  KEY idx_creator_campaign_agreement_acceptance_creator (creator_user_id,decided_at,id),
  CONSTRAINT fk_creator_campaign_agreement_acceptance_agreement FOREIGN KEY (agreement_id) REFERENCES creator_campaign_agreements(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_agreement_acceptance_version FOREIGN KEY (agreement_version_id) REFERENCES creator_campaign_agreement_versions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_agreement_acceptance_participant FOREIGN KEY (participant_id) REFERENCES creator_campaign_participants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_creator_campaign_agreement_acceptance_creator FOREIGN KEY (creator_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE creator_campaign_agreements
  ADD CONSTRAINT fk_creator_campaign_agreement_current_version FOREIGN KEY (current_version_id) REFERENCES creator_campaign_agreement_versions(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_creator_campaign_agreement_accepted_version FOREIGN KEY (latest_accepted_version_id) REFERENCES creator_campaign_agreement_versions(id) ON DELETE SET NULL;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.creator_applications.view','View creator applications','View applications submitted to creator campaigns owned by the active merchant workspace.',NOW()),
('merchant.creator_applications.manage','Manage creator applications','Review, request information, approve, and decline creator campaign applications.',NOW()),
('merchant.creator_invitations.manage','Manage creator invitations','Invite eligible existing Creator accounts and manage invitation status.',NOW()),
('merchant.creator_participants.view','View creator participants','View creator participants scoped to the active merchant workspace.',NOW()),
('merchant.creator_participants.manage','Manage creator participants','Remove, suspend, restore, and complete creator participants.',NOW()),
('merchant.creator_agreements.view','View creator agreements','View agreement versions for creator campaign participants in the active merchant workspace.',NOW()),
('merchant.creator_agreements.manage','Manage creator agreements','Create and offer immutable creator agreement versions.',NOW()),
('creator.campaigns.discover','Discover creator campaigns','Discover creator campaigns available to the active approved Creator account.',NOW()),
('creator.campaign_applications.manage_own','Manage own creator campaign applications','Create, update, submit, withdraw, and resubmit the authenticated creator application.',NOW()),
('creator.campaign_invitations.respond_own','Respond to own creator invitations','Accept or decline invitations addressed to the authenticated creator.',NOW()),
('creator.campaign_participants.view_own','View own creator participation','View the authenticated creator participation and active campaign workspace.',NOW()),
('creator.campaign_agreements.view_own','View own creator agreements','View and export agreement versions offered to the authenticated creator.',NOW()),
('creator.campaign_agreements.respond_own','Respond to own creator agreements','Accept or decline the current immutable agreement version.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN (
  'merchant.creator_applications.view',
  'merchant.creator_applications.manage',
  'merchant.creator_invitations.manage',
  'merchant.creator_participants.view',
  'merchant.creator_participants.manage',
  'merchant.creator_agreements.view',
  'merchant.creator_agreements.manage'
)
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN (
  'creator.campaigns.discover',
  'creator.campaign_applications.manage_own',
  'creator.campaign_invitations.respond_own',
  'creator.campaign_participants.view_own',
  'creator.campaign_agreements.view_own',
  'creator.campaign_agreements.respond_own'
)
WHERE r.slug IN ('creator','admin','super_admin');

COMMIT;
