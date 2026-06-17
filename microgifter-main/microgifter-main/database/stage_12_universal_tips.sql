CREATE TABLE IF NOT EXISTS tips (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  recipient_user_id BIGINT UNSIGNED NOT NULL,
  target_type ENUM('profile','creator','merchant','location','product','post','gift','claim') NOT NULL,
  target_reference VARCHAR(190) NOT NULL,
  amount_cents BIGINT UNSIGNED NOT NULL,
  fee_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  net_cents BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  funding_type ENUM('wallet','stripe') NOT NULL,
  status ENUM('pending','funded','posted','failed','reversed') NOT NULL DEFAULT 'pending',
  idempotency_key VARCHAR(190) NOT NULL,
  provider_payment_id VARCHAR(190) NULL,
  ledger_group_id BIGINT UNSIGNED NULL,
  reversal_group_id BIGINT UNSIGNED NULL,
  fee_snapshot_json JSON NOT NULL,
  metadata_json JSON NULL,
  failure_message VARCHAR(500) NULL,
  funded_at DATETIME NULL,
  posted_at DATETIME NULL,
  reversed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tips_public_id (public_id),
  UNIQUE KEY uq_tips_sender_idempotency (sender_user_id,idempotency_key),
  UNIQUE KEY uq_tips_provider_payment (provider_payment_id),
  KEY idx_tips_recipient_status (recipient_user_id,status,created_at),
  KEY idx_tips_target (target_type,target_reference,created_at),
  CONSTRAINT fk_tips_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_tips_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_tips_ledger_group FOREIGN KEY (ledger_group_id) REFERENCES ledger_transaction_groups(id) ON DELETE SET NULL,
  CONSTRAINT fk_tips_reversal_group FOREIGN KEY (reversal_group_id) REFERENCES ledger_transaction_groups(id) ON DELETE SET NULL,
  CONSTRAINT chk_tips_amount_positive CHECK (amount_cents > 0),
  CONSTRAINT chk_tips_net_valid CHECK (net_cents > 0 AND amount_cents = fee_cents + net_cents)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tip_velocity_counters (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  window_key VARCHAR(40) NOT NULL,
  tip_count INT UNSIGNED NOT NULL DEFAULT 0,
  amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tip_velocity_sender_window (sender_user_id,window_key),
  CONSTRAINT fk_tip_velocity_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tip_reversals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  tip_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(500) NOT NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  reversed_by_user_id BIGINT UNSIGNED NOT NULL,
  ledger_group_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tip_reversals_public_id (public_id),
  UNIQUE KEY uq_tip_reversals_tip (tip_id),
  UNIQUE KEY uq_tip_reversals_idempotency (idempotency_key),
  CONSTRAINT fk_tip_reversal_tip FOREIGN KEY (tip_id) REFERENCES tips(id) ON DELETE RESTRICT,
  CONSTRAINT fk_tip_reversal_user FOREIGN KEY (reversed_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_tip_reversal_ledger FOREIGN KEY (ledger_group_id) REFERENCES ledger_transaction_groups(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('tips.create','Create tips','Create wallet-funded or card-funded tips.',NOW()),
('tips.view_own','View own tips','View tips sent or received by the current user.',NOW()),
('tips.reverse','Reverse tips','Administratively reverse posted tips.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN ('tips.create','tips.view_own')
WHERE r.slug IN ('customer','member','merchant','creator','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='tips.reverse'
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_12_universal_tips','Universal tip targets, wallet and Stripe funding, ledger settlement, velocity controls, and reversals.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
