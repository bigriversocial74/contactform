-- Microgifter Investor Invite and Onboarding v1
-- Additive, import-safe migration applied after Investor Center v6.
-- Invitations are token-hashed, email-bound, expiring, revocable, and convert into
-- the existing investor_access_requests approval workflow. They do not grant
-- Investor role, portal, Data Room, round, or securities access automatically.
-- round_id and request_id are intentionally indexed without foreign-key constraints
-- so the canonical migration chain remains installable before optional Investor
-- phase tables are present. Application services enforce those relationships.

CREATE TABLE IF NOT EXISTS investor_invitations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  invited_email VARCHAR(254) NOT NULL,
  invited_email_hash CHAR(64) NOT NULL,
  contact_name VARCHAR(180) NULL,
  firm_name VARCHAR(180) NULL,
  investor_type ENUM('individual','angel','investment_firm','venture_fund','family_office','strategic_partner','company_entity','other') NOT NULL DEFAULT 'individual',
  expected_investment_range ENUM('undecided','under_10k','10k_25k','25k_50k','50k_100k','100k_250k','over_250k') NOT NULL DEFAULT 'undecided',
  round_id BIGINT UNSIGNED NULL,
  personal_message TEXT NULL,
  status ENUM('created','sent','viewed','accepted','expired','revoked') NOT NULL DEFAULT 'created',
  delivery_status ENUM('not_sent','sent','failed') NOT NULL DEFAULT 'not_sent',
  token_hash CHAR(64) NOT NULL,
  token_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  invited_by_user_id BIGINT UNSIGNED NOT NULL,
  sent_at DATETIME NULL,
  last_sent_at DATETIME NULL,
  send_count INT UNSIGNED NOT NULL DEFAULT 0,
  first_viewed_at DATETIME NULL,
  last_viewed_at DATETIME NULL,
  view_count INT UNSIGNED NOT NULL DEFAULT 0,
  accepted_by_user_id BIGINT UNSIGNED NULL,
  accepted_at DATETIME NULL,
  request_id BIGINT UNSIGNED NULL,
  disclosure_version VARCHAR(80) NULL,
  disclosure_accepted_at DATETIME NULL,
  revoked_by_user_id BIGINT UNSIGNED NULL,
  revoked_at DATETIME NULL,
  revocation_reason TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_investor_invitation_public (public_id),
  UNIQUE KEY uq_investor_invitation_token (token_hash),
  KEY idx_investor_invitation_email (invited_email_hash,status,expires_at),
  KEY idx_investor_invitation_queue (status,expires_at,created_at),
  KEY idx_investor_invitation_round (round_id,status,created_at),
  KEY idx_investor_invitation_request (request_id),
  CONSTRAINT fk_investor_invitation_inviter FOREIGN KEY (invited_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_investor_invitation_acceptor FOREIGN KEY (accepted_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_investor_invitation_revoker FOREIGN KEY (revoked_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investor_invitation_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  invitation_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  details_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_investor_invitation_event (invitation_id,created_at),
  KEY idx_investor_invitation_event_type (event_type,created_at),
  CONSTRAINT fk_investor_invitation_event_invitation FOREIGN KEY (invitation_id) REFERENCES investor_invitations(id) ON DELETE CASCADE,
  CONSTRAINT fk_investor_invitation_event_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (migration_key,description,applied_at)
VALUES ('20260729_investor_invite_onboarding_v1','Adds token-hashed Super Admin Investor invitations, email-bound account linking, onboarding disclosures, invitation events, and conversion into the existing Investor Access approval workflow.',NOW());
