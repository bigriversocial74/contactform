-- Wallet and Redemption Experience v1
-- Safe to rerun after successful import.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS wallet_reward_claim_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  wallet_item_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  token_last4 CHAR(4) NOT NULL,
  status ENUM('active','used','revoked','expired') NOT NULL DEFAULT 'active',
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wallet_reward_claim_token_public (public_id),
  UNIQUE KEY uq_wallet_reward_claim_token_hash (token_hash),
  KEY idx_wallet_reward_claim_token_item_status (wallet_item_id,status,expires_at),
  KEY idx_wallet_reward_claim_token_user_created (user_id,created_at),
  KEY idx_wallet_reward_claim_token_merchant_status (merchant_user_id,status,created_at),
  CONSTRAINT fk_wallet_reward_claim_token_item FOREIGN KEY (wallet_item_id) REFERENCES wallet_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_wallet_reward_claim_token_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wallet_reward_claim_token_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_reward_support_cases (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  wallet_item_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  category ENUM('claim_code','merchant_redemption','reward_missing','expired_reward','wrong_reward','regift','other') NOT NULL DEFAULT 'other',
  status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  subject VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  resolution_note TEXT NULL,
  resolved_by_user_id BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wallet_reward_support_case_public (public_id),
  KEY idx_wallet_reward_support_user_status (user_id,status,created_at),
  KEY idx_wallet_reward_support_merchant_status (merchant_user_id,status,created_at),
  KEY idx_wallet_reward_support_item (wallet_item_id,created_at),
  CONSTRAINT fk_wallet_reward_support_item FOREIGN KEY (wallet_item_id) REFERENCES wallet_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_wallet_reward_support_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wallet_reward_support_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wallet_reward_support_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
