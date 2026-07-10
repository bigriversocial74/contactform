CREATE TABLE IF NOT EXISTS lqr_participant_auth_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_public_id VARCHAR(64) NOT NULL,
  purpose ENUM('email_verification','password_reset') NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lqr_participant_auth_token (token_hash),
  KEY idx_lqr_participant_auth_user_purpose (user_public_id,purpose,created_at),
  CONSTRAINT fk_lqr_participant_auth_user FOREIGN KEY (user_public_id) REFERENCES lqr_users(public_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lqr_participant_login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email_hash CHAR(64) NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  succeeded TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_lqr_participant_login_window (email_hash,ip_hash,attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE lqr_users
  ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL AFTER email,
  ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL AFTER password_hash,
  ADD COLUMN IF NOT EXISTS session_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER password_changed_at,
  ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL AFTER session_version;

INSERT INTO lqr_schema_versions (version_key,description,applied_at)
VALUES ('2026.07.10-participant-auth-v1','Participant password recovery, email verification, login throttling, and session versioning.',NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description),applied_at=VALUES(applied_at);