-- Local Quest Rewards admin auth extension
-- Apply after database/local_quest_rewards.sql when using SQL runtime storage.

CREATE TABLE IF NOT EXISTS lqr_admin_password_resets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_public_id VARCHAR(64) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  requested_by_admin_public_id VARCHAR(64) DEFAULT NULL,
  request_ip VARCHAR(64) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lqr_admin_reset_token_hash (token_hash),
  KEY idx_lqr_admin_reset_admin (admin_public_id),
  KEY idx_lqr_admin_reset_expiry (expires_at),
  KEY idx_lqr_admin_reset_used (used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE lqr_admin_users
  ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL AFTER username,
  ADD COLUMN IF NOT EXISTS force_password_change TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN IF NOT EXISTS failed_login_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER force_password_change,
  ADD COLUMN IF NOT EXISTS locked_until DATETIME DEFAULT NULL AFTER failed_login_count;
