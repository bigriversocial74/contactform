-- Microgifter Loyalty Quest Participant Experience v1
-- Safe additive schema for participant enrollment, evidence, review, and completion state.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS loyalty_quest_participations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  participant_user_id BIGINT UNSIGNED NOT NULL,
  contact_id BIGINT UNSIGNED NULL,
  status ENUM('joined','in_progress','pending_review','completed','rejected','cancelled') NOT NULL DEFAULT 'joined',
  progress_count INT UNSIGNED NOT NULL DEFAULT 0,
  required_count INT UNSIGNED NOT NULL DEFAULT 1,
  completion_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  wallet_item_id BIGINT UNSIGNED NULL,
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at DATETIME NULL,
  submitted_at DATETIME NULL,
  reviewed_at DATETIME NULL,
  completed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_loyalty_quest_participant_campaign_user (campaign_id, participant_user_id),
  UNIQUE KEY uq_loyalty_quest_participation_public_id (public_id),
  KEY idx_loyalty_quest_participation_user_status (participant_user_id, status, updated_at),
  KEY idx_loyalty_quest_participation_merchant_status (merchant_user_id, status, updated_at),
  KEY idx_loyalty_quest_participation_campaign_status (campaign_id, status, updated_at),
  CONSTRAINT fk_lq_participation_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_lq_participation_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_lq_participation_user FOREIGN KEY (participant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_lq_participation_contact FOREIGN KEY (contact_id) REFERENCES campaign_contacts(id) ON DELETE SET NULL,
  CONSTRAINT fk_lq_participation_wallet FOREIGN KEY (wallet_item_id) REFERENCES wallet_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyalty_quest_evidence (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  participation_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  participant_user_id BIGINT UNSIGNED NOT NULL,
  evidence_type ENUM('qr','manual_code','geolocation','purchase','receipt','staff_confirmation','event_checkin','referral','social','milestone','note') NOT NULL,
  status ENUM('submitted','verified','rejected') NOT NULL DEFAULT 'submitted',
  code_hash CHAR(64) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  accuracy_meters DECIMAL(10,2) NULL,
  distance_meters DECIMAL(10,2) NULL,
  proof_url VARCHAR(700) NULL,
  proof_note TEXT NULL,
  reference_id VARCHAR(190) NULL,
  reviewer_user_id BIGINT UNSIGNED NULL,
  review_note TEXT NULL,
  verified_at DATETIME NULL,
  rejected_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_loyalty_quest_evidence_public_id (public_id),
  KEY idx_lq_evidence_participation_status (participation_id, status, created_at),
  KEY idx_lq_evidence_campaign_user (campaign_id, participant_user_id, created_at),
  KEY idx_lq_evidence_merchant_review (merchant_user_id, status, created_at),
  CONSTRAINT fk_lq_evidence_participation FOREIGN KEY (participation_id) REFERENCES loyalty_quest_participations(id) ON DELETE CASCADE,
  CONSTRAINT fk_lq_evidence_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_lq_evidence_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_lq_evidence_user FOREIGN KEY (participant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_lq_evidence_reviewer FOREIGN KEY (reviewer_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyalty_quest_code_uses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id BIGINT UNSIGNED NOT NULL,
  participant_user_id BIGINT UNSIGNED NOT NULL,
  code_hash CHAR(64) NOT NULL,
  nonce_hash CHAR(64) NULL,
  used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lq_code_user_replay (campaign_id, participant_user_id, code_hash),
  UNIQUE KEY uq_lq_code_nonce_replay (campaign_id, nonce_hash),
  CONSTRAINT fk_lq_code_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_lq_code_user FOREIGN KEY (participant_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
