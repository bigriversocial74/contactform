CREATE TABLE IF NOT EXISTS microgift_claims (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  instance_id BIGINT UNSIGNED NOT NULL,
  credential_id BIGINT UNSIGNED NOT NULL,
  claimant_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('verified','completed','rejected','reversed') NOT NULL DEFAULT 'verified',
  idempotency_key VARCHAR(190) NOT NULL,
  source_reference VARCHAR(190) NOT NULL,
  previous_owner_user_id BIGINT UNSIGNED NULL,
  pppm_item_id BIGINT UNSIGNED NULL,
  entitlement_transfer_id VARCHAR(64) NULL,
  verified_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgift_claims_public_id (public_id),
  UNIQUE KEY uq_microgift_claims_idempotency (idempotency_key),
  UNIQUE KEY uq_microgift_claims_instance_completed (instance_id,status),
  KEY idx_microgift_claims_claimant (claimant_user_id,created_at),
  CONSTRAINT fk_microgift_claims_instance FOREIGN KEY (instance_id) REFERENCES microgift_instances(id) ON DELETE RESTRICT,
  CONSTRAINT fk_microgift_claims_credential FOREIGN KEY (credential_id) REFERENCES microgift_credentials(id) ON DELETE RESTRICT,
  CONSTRAINT fk_microgift_claims_claimant FOREIGN KEY (claimant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_microgift_claims_previous_owner FOREIGN KEY (previous_owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_claims_pppm FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS microgift_redemptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  instance_id BIGINT UNSIGNED NOT NULL,
  claimant_user_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  location_reference VARCHAR(190) NULL,
  amount_cents BIGINT UNSIGNED NULL,
  currency CHAR(3) NOT NULL,
  status ENUM('completed','reversed','rejected') NOT NULL DEFAULT 'completed',
  idempotency_key VARCHAR(190) NOT NULL,
  source_reference VARCHAR(190) NOT NULL,
  redeemed_at DATETIME NOT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgift_redemptions_public_id (public_id),
  UNIQUE KEY uq_microgift_redemptions_idempotency (idempotency_key),
  UNIQUE KEY uq_microgift_redemptions_instance_completed (instance_id,status),
  KEY idx_microgift_redemptions_merchant (merchant_user_id,redeemed_at),
  KEY idx_microgift_redemptions_claimant (claimant_user_id,redeemed_at),
  CONSTRAINT fk_microgift_redemptions_instance FOREIGN KEY (instance_id) REFERENCES microgift_instances(id) ON DELETE RESTRICT,
  CONSTRAINT fk_microgift_redemptions_claimant FOREIGN KEY (claimant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_microgift_redemptions_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS microgift_lifecycle_actions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  instance_id BIGINT UNSIGNED NOT NULL,
  action_type ENUM('cancel','revoke','expire','replace','refund','dispute_opened','dispute_won','dispute_lost') NOT NULL,
  from_status VARCHAR(40) NOT NULL,
  to_status VARCHAR(40) NOT NULL,
  source_type VARCHAR(80) NOT NULL,
  source_reference VARCHAR(190) NOT NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  replacement_instance_id BIGINT UNSIGNED NULL,
  reason VARCHAR(240) NULL,
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgift_lifecycle_public_id (public_id),
  UNIQUE KEY uq_microgift_lifecycle_idempotency (idempotency_key),
  KEY idx_microgift_lifecycle_instance (instance_id,created_at),
  CONSTRAINT fk_microgift_lifecycle_instance FOREIGN KEY (instance_id) REFERENCES microgift_instances(id) ON DELETE RESTRICT,
  CONSTRAINT fk_microgift_lifecycle_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_lifecycle_replacement FOREIGN KEY (replacement_instance_id) REFERENCES microgift_instances(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('microgift.claim','Claim Microgifts','Claim eligible Microgift instances using secure credentials.',NOW()),
('microgift.redeem','Redeem Microgifts','Redeem owned Microgift instances at eligible merchants and locations.',NOW()),
('microgift.lifecycle.manage','Manage Microgift lifecycle','Cancel, revoke, expire, replace, and apply payment lifecycle policy.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN ('microgift.claim','microgift.redeem')
WHERE r.slug IN ('customer','member','merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='microgift.lifecycle.manage'
WHERE r.slug IN ('admin','super_admin');