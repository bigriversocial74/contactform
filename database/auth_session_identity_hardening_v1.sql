-- Auth, Session and Identity Production Hardening v1
-- Import after database/stage_1_identity.sql and database/stage_1_repair_03M.sql.
-- Adds auth-version invalidation, idle/absolute session expiry, session rotation metadata,
-- and encrypted TOTP/recovery-code storage. No existing passwords or sessions are deleted.
--
-- This migration intentionally avoids DELIMITER and stored-procedure directives so it can
-- run through the canonical PDO migration runner as well as the MySQL command-line client.

SET @has_users_auth_version := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='auth_version'
);
SET @sql := IF(
  @has_users_auth_version=0,
  'ALTER TABLE `users` ADD COLUMN `auth_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `email_verified_at`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_users_password_changed_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='password_changed_at'
);
SET @sql := IF(
  @has_users_password_changed_at=0,
  'ALTER TABLE `users` ADD COLUMN `password_changed_at` DATETIME NULL AFTER `auth_version`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_users_last_login_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='last_login_at'
);
SET @sql := IF(
  @has_users_last_login_at=0,
  'ALTER TABLE `users` ADD COLUMN `last_login_at` DATETIME NULL AFTER `password_changed_at`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_sessions_auth_version := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' AND COLUMN_NAME='auth_version'
);
SET @sql := IF(
  @has_sessions_auth_version=0,
  'ALTER TABLE `user_sessions` ADD COLUMN `auth_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `session_hash`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_sessions_authentication_method := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' AND COLUMN_NAME='authentication_method'
);
SET @sql := IF(
  @has_sessions_authentication_method=0,
  'ALTER TABLE `user_sessions` ADD COLUMN `authentication_method` ENUM(''password'',''mfa'',''recovery'') NOT NULL DEFAULT ''password'' AFTER `auth_version`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_sessions_idle_expires_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' AND COLUMN_NAME='idle_expires_at'
);
SET @sql := IF(
  @has_sessions_idle_expires_at=0,
  'ALTER TABLE `user_sessions` ADD COLUMN `idle_expires_at` DATETIME NULL AFTER `last_seen_at`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_sessions_absolute_expires_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' AND COLUMN_NAME='absolute_expires_at'
);
SET @sql := IF(
  @has_sessions_absolute_expires_at=0,
  'ALTER TABLE `user_sessions` ADD COLUMN `absolute_expires_at` DATETIME NULL AFTER `idle_expires_at`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_sessions_last_rotated_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' AND COLUMN_NAME='last_rotated_at'
);
SET @sql := IF(
  @has_sessions_last_rotated_at=0,
  'ALTER TABLE `user_sessions` ADD COLUMN `last_rotated_at` DATETIME NULL AFTER `absolute_expires_at`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_sessions_active_auth_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' AND INDEX_NAME='idx_user_sessions_active_auth'
);
SET @sql := IF(
  @has_sessions_active_auth_index=0,
  'ALTER TABLE `user_sessions` ADD KEY `idx_user_sessions_active_auth` (`user_id`,`auth_version`,`revoked_at`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_sessions_idle_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' AND INDEX_NAME='idx_user_sessions_idle'
);
SET @sql := IF(
  @has_sessions_idle_index=0,
  'ALTER TABLE `user_sessions` ADD KEY `idx_user_sessions_idle` (`idle_expires_at`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_sessions_absolute_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_sessions' AND INDEX_NAME='idx_user_sessions_absolute'
);
SET @sql := IF(
  @has_sessions_absolute_index=0,
  'ALTER TABLE `user_sessions` ADD KEY `idx_user_sessions_absolute` (`absolute_expires_at`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE users SET auth_version=1 WHERE auth_version IS NULL OR auth_version<1;
-- Existing active accounts predate the mandatory verification gate; preserve access during rollout.
UPDATE users SET email_verified_at=COALESCE(email_verified_at,created_at) WHERE status='active' AND email_verified_at IS NULL;
UPDATE user_sessions
SET auth_version=1,
    authentication_method=COALESCE(authentication_method,'password'),
    idle_expires_at=COALESCE(idle_expires_at,DATE_ADD(NOW(),INTERVAL 720 MINUTE)),
    absolute_expires_at=COALESCE(absolute_expires_at,expires_at,DATE_ADD(NOW(),INTERVAL 30 DAY)),
    last_rotated_at=COALESCE(last_rotated_at,created_at,NOW())
WHERE revoked_at IS NULL;

CREATE TABLE IF NOT EXISTS user_mfa_methods (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  method_type ENUM('totp') NOT NULL DEFAULT 'totp',
  label VARCHAR(120) NOT NULL DEFAULT 'Authenticator app',
  secret_encrypted TEXT NOT NULL,
  last_counter BIGINT NULL,
  confirmed_at DATETIME NULL,
  last_used_at DATETIME NULL,
  disabled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user_mfa_methods_user (user_id,disabled_at,confirmed_at),
  CONSTRAINT fk_user_mfa_methods_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_mfa_recovery_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  method_id BIGINT UNSIGNED NOT NULL,
  code_hash CHAR(64) NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_mfa_recovery_code_hash (code_hash),
  KEY idx_user_mfa_recovery_user (user_id,used_at),
  CONSTRAINT fk_user_mfa_recovery_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_mfa_recovery_method FOREIGN KEY (method_id) REFERENCES user_mfa_methods(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
