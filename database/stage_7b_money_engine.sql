CREATE TABLE IF NOT EXISTS wallets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_type ENUM('user','merchant','creator','organization','enterprise') NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  status ENUM('active','restricted','closed') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wallets_public_id (public_id),
  UNIQUE KEY uq_wallets_owner_currency (owner_type,owner_user_id,currency),
  CONSTRAINT fk_wallets_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ledger_accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  wallet_id BIGINT UNSIGNED NULL,
  account_code VARCHAR(80) NOT NULL,
  account_class ENUM('asset','liability','equity','revenue','expense') NOT NULL,
  normal_side ENUM('debit','credit') NOT NULL,
  currency CHAR(3) NOT NULL,
  status ENUM('active','closed') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ledger_accounts_public_id (public_id),
  UNIQUE KEY uq_ledger_accounts_wallet_code (wallet_id,account_code,currency),
  KEY idx_ledger_accounts_code (account_code,currency),
  CONSTRAINT fk_ledger_accounts_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ledger_transaction_groups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  transaction_type VARCHAR(80) NOT NULL,
  source_type VARCHAR(80) NOT NULL,
  source_reference VARCHAR(190) NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  currency CHAR(3) NOT NULL,
  status ENUM('pending','posted','reversed','failed') NOT NULL DEFAULT 'pending',
  description VARCHAR(240) NULL,
  metadata_json JSON NULL,
  posted_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ledger_groups_public_id (public_id),
  UNIQUE KEY uq_ledger_groups_idempotency (idempotency_key),
  KEY idx_ledger_groups_source (source_type,source_reference),
  CONSTRAINT fk_ledger_groups_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ledger_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  transaction_group_id BIGINT UNSIGNED NOT NULL,
  ledger_account_id BIGINT UNSIGNED NOT NULL,
  entry_type ENUM('debit','credit') NOT NULL,
  amount_cents BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL,
  description VARCHAR(240) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ledger_entries_public_id (public_id),
  KEY idx_ledger_entries_group (transaction_group_id,id),
  KEY idx_ledger_entries_account (ledger_account_id,id),
  CONSTRAINT fk_ledger_entries_group FOREIGN KEY (transaction_group_id) REFERENCES ledger_transaction_groups(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ledger_entries_account FOREIGN KEY (ledger_account_id) REFERENCES ledger_accounts(id) ON DELETE RESTRICT,
  CONSTRAINT chk_ledger_entries_positive CHECK (amount_cents > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ledger_reversal_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  original_group_id BIGINT UNSIGNED NOT NULL,
  reversal_group_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(240) NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ledger_reversal_original (original_group_id),
  UNIQUE KEY uq_ledger_reversal_group (reversal_group_id),
  CONSTRAINT fk_ledger_reversal_original FOREIGN KEY (original_group_id) REFERENCES ledger_transaction_groups(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ledger_reversal_group FOREIGN KEY (reversal_group_id) REFERENCES ledger_transaction_groups(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ledger_reversal_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_balance_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  wallet_id BIGINT UNSIGNED NOT NULL,
  available_cents BIGINT NOT NULL DEFAULT 0,
  pending_cents BIGINT NOT NULL DEFAULT 0,
  held_cents BIGINT NOT NULL DEFAULT 0,
  cashout_pending_cents BIGINT NOT NULL DEFAULT 0,
  paid_cents BIGINT NOT NULL DEFAULT 0,
  calculated_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_wallet_snapshots_wallet (wallet_id,calculated_at),
  CONSTRAINT fk_wallet_snapshots_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cashout_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  wallet_id BIGINT UNSIGNED NOT NULL,
  requested_by_user_id BIGINT UNSIGNED NOT NULL,
  amount_cents BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL,
  status ENUM('requested','approved','queued','processing','paid','failed','cancelled') NOT NULL DEFAULT 'requested',
  idempotency_key VARCHAR(190) NOT NULL,
  reservation_group_id BIGINT UNSIGNED NOT NULL,
  release_group_id BIGINT UNSIGNED NULL,
  failure_message VARCHAR(500) NULL,
  approved_by_user_id BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cashout_requests_public_id (public_id),
  UNIQUE KEY uq_cashout_requests_idempotency (wallet_id,idempotency_key),
  KEY idx_cashout_requests_status (status,created_at),
  CONSTRAINT fk_cashout_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cashout_requester FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cashout_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_cashout_reservation FOREIGN KEY (reservation_group_id) REFERENCES ledger_transaction_groups(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cashout_release FOREIGN KEY (release_group_id) REFERENCES ledger_transaction_groups(id) ON DELETE SET NULL,
  CONSTRAINT chk_cashout_positive CHECK (amount_cents > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cashout_payout_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cashout_request_id BIGINT UNSIGNED NOT NULL,
  payout_id BIGINT UNSIGNED NOT NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cashout_payout_cashout (cashout_request_id),
  UNIQUE KEY uq_cashout_payout_payout (payout_id),
  UNIQUE KEY uq_cashout_payout_idempotency (idempotency_key),
  CONSTRAINT fk_cashout_payout_cashout FOREIGN KEY (cashout_request_id) REFERENCES cashout_requests(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cashout_payout_record FOREIGN KEY (payout_id) REFERENCES merchant_payouts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payout_holds (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  wallet_id BIGINT UNSIGNED NOT NULL,
  amount_cents BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL,
  reason VARCHAR(240) NOT NULL,
  status ENUM('active','released','expired') NOT NULL DEFAULT 'active',
  hold_group_id BIGINT UNSIGNED NOT NULL,
  release_group_id BIGINT UNSIGNED NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  released_by_user_id BIGINT UNSIGNED NULL,
  expires_at DATETIME NULL,
  released_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payout_holds_public_id (public_id),
  KEY idx_payout_holds_wallet (wallet_id,status),
  CONSTRAINT fk_payout_holds_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_payout_holds_group FOREIGN KEY (hold_group_id) REFERENCES ledger_transaction_groups(id) ON DELETE RESTRICT,
  CONSTRAINT fk_payout_release_group FOREIGN KEY (release_group_id) REFERENCES ledger_transaction_groups(id) ON DELETE SET NULL,
  CONSTRAINT fk_payout_holds_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_payout_holds_releaser FOREIGN KEY (released_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('wallet.view','View wallets','View owned wallet balances and ledger.',NOW()),
('cashouts.request','Request cashouts','Request a cashout from an owned wallet.',NOW()),
('cashouts.view_own','View own cashouts','View own cashout requests and payouts.',NOW()),
('cashouts.manage','Manage cashouts','Approve, cancel and process cashouts.',NOW()),
('payout_holds.manage','Manage payout holds','Create and release payout holds.',NOW()),
('financial.reversals.manage','Manage financial reversals','Create linked ledger reversals.',NOW()),
('financial.reconciliation.manage','Manage financial reconciliation','Run and inspect financial reconciliation.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN ('wallet.view','cashouts.request','cashouts.view_own')
WHERE r.slug IN ('member','merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN ('cashouts.manage','payout_holds.manage','financial.reversals.manage','financial.reconciliation.manage')
WHERE r.slug IN ('admin','super_admin');
