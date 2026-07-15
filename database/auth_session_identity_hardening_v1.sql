-- Auth, Session and Identity Production Hardening v1
-- Import after database/stage_1_identity.sql and database/stage_1_repair_03M.sql.
-- Adds auth-version invalidation, idle/absolute session expiry, session rotation metadata,
-- and encrypted TOTP/recovery-code storage. No existing passwords or sessions are deleted.

DROP PROCEDURE IF EXISTS mg_auth_hardening_add_column;
DROP PROCEDURE IF EXISTS mg_auth_hardening_add_index;
DELIMITER $$
CREATE PROCEDURE mg_auth_hardening_add_column(IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_definition TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=p_table AND COLUMN_NAME=p_column
  ) THEN
    SET @sql=CONCAT('ALTER TABLE `',p_table,'` ADD COLUMN `',p_column,'` ',p_definition);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END$$
CREATE PROCEDURE mg_auth_hardening_add_index(IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_definition TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=p_table AND INDEX_NAME=p_index
  ) THEN
    SET @sql=CONCAT('ALTER TABLE `',p_table,'` ADD ',p_definition);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END$$
DELIMITER ;

CALL mg_auth_hardening_add_column('users','auth_version','INT UNSIGNED NOT NULL DEFAULT 1 AFTER `email_verified_at`');
CALL mg_auth_hardening_add_column('users','password_changed_at','DATETIME NULL AFTER `auth_version`');
CALL mg_auth_hardening_add_column('users','last_login_at','DATETIME NULL AFTER `password_changed_at`');

CALL mg_auth_hardening_add_column('user_sessions','auth_version','INT UNSIGNED NOT NULL DEFAULT 1 AFTER `session_hash`');
CALL mg_auth_hardening_add_column('user_sessions','authentication_method','ENUM(''password'',''mfa'',''recovery'') NOT NULL DEFAULT ''password'' AFTER `auth_version`');
CALL mg_auth_hardening_add_column('user_sessions','idle_expires_at','DATETIME NULL AFTER `last_seen_at`');
CALL mg_auth_hardening_add_column('user_sessions','absolute_expires_at','DATETIME NULL AFTER `idle_expires_at`');
CALL mg_auth_hardening_add_column('user_sessions','last_rotated_at','DATETIME NULL AFTER `absolute_expires_at`');

CALL mg_auth_hardening_add_index('user_sessions','idx_user_sessions_active_auth','KEY `idx_user_sessions_active_auth` (`user_id`,`auth_version`,`revoked_at`)');
CALL mg_auth_hardening_add_index('user_sessions','idx_user_sessions_idle','KEY `idx_user_sessions_idle` (`idle_expires_at`)');
CALL mg_auth_hardening_add_index('user_sessions','idx_user_sessions_absolute','KEY `idx_user_sessions_absolute` (`absolute_expires_at`)');

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

DROP PROCEDURE IF EXISTS mg_auth_hardening_add_column;
DROP PROCEDURE IF EXISTS mg_auth_hardening_add_index;
