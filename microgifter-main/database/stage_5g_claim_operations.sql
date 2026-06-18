CREATE TABLE IF NOT EXISTS merchant_claim_code_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  claim_code_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('created','rotated','activated','deactivated','revoked','expired','limit_changed') NOT NULL,
  previous_claim_code_id BIGINT UNSIGNED NULL,
  metadata_json JSON NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_claim_code_events_public_id (public_id),
  KEY idx_merchant_claim_code_events_code (claim_code_id,created_at,id),
  KEY idx_merchant_claim_code_events_location (location_id,created_at,id),
  CONSTRAINT fk_claim_code_events_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_claim_code_events_code FOREIGN KEY (claim_code_id) REFERENCES merchant_claim_codes(id) ON DELETE RESTRICT,
  CONSTRAINT fk_claim_code_events_location FOREIGN KEY (location_id) REFERENCES merchant_locations(id) ON DELETE RESTRICT,
  CONSTRAINT fk_claim_code_events_previous FOREIGN KEY (previous_claim_code_id) REFERENCES merchant_claim_codes(id) ON DELETE SET NULL,
  CONSTRAINT fk_claim_code_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_claim_exceptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  claim_id BIGINT UNSIGNED NOT NULL,
  pppm_item_id BIGINT UNSIGNED NULL,
  location_id BIGINT UNSIGNED NULL,
  exception_type ENUM('locked','expired','eligibility','code_failure','duplicate','dispute','cancellation','data_issue','other') NOT NULL DEFAULT 'other',
  status ENUM('open','investigating','waiting','resolved','closed') NOT NULL DEFAULT 'open',
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  summary VARCHAR(240) NOT NULL,
  resolution_notes TEXT NULL,
  opened_by_user_id BIGINT UNSIGNED NOT NULL,
  resolved_by_user_id BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  closed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_claim_exceptions_public_id (public_id),
  KEY idx_merchant_claim_exceptions_merchant (merchant_user_id,status,priority,updated_at),
  KEY idx_merchant_claim_exceptions_claim (claim_id,status),
  CONSTRAINT fk_claim_exceptions_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_claim_exceptions_claim FOREIGN KEY (claim_id) REFERENCES gift_claims(id) ON DELETE RESTRICT,
  CONSTRAINT fk_claim_exceptions_pppm FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_claim_exceptions_location FOREIGN KEY (location_id) REFERENCES merchant_locations(id) ON DELETE SET NULL,
  CONSTRAINT fk_claim_exceptions_opened_by FOREIGN KEY (opened_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_claim_exceptions_resolved_by FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.claims.view','View merchant claims','View merchant-scoped claim, verification, redemption, lockout, and exception operations.',NOW()),
('merchant.claims.exceptions.manage','Manage claim exceptions','Open, investigate, resolve, and close merchant claim exceptions.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p
ON p.slug IN ('merchant.claims.view','merchant.claims.exceptions.manage')
WHERE r.slug IN ('merchant','admin','super_admin');
