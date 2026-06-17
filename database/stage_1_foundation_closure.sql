-- Microgifter Stage 1 Foundation Closure
-- Idempotent compatibility migration for durable identity metadata.

SET @has_last_login_at := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'last_login_at'
);

SET @add_last_login_at := IF(
  @has_last_login_at = 0,
  'ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER email_verified_at, ADD INDEX idx_users_last_login_at (last_login_at)',
  'SELECT 1'
);

PREPARE stage1_foundation_stmt FROM @add_last_login_at;
EXECUTE stage1_foundation_stmt;
DEALLOCATE PREPARE stage1_foundation_stmt;

DROP TRIGGER IF EXISTS trg_audit_auth_login_last_seen;

CREATE TRIGGER trg_audit_auth_login_last_seen
AFTER INSERT ON audit_logs
FOR EACH ROW
UPDATE users
SET last_login_at = NEW.created_at,
    updated_at = GREATEST(updated_at, NEW.created_at)
WHERE NEW.action = 'auth.login'
  AND NEW.user_id IS NOT NULL
  AND id = NEW.user_id;
