-- V1 Stage G: official Stripe Connect Standard OAuth onboarding for merchants.
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS payment_connect_oauth_states (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  provider_key VARCHAR(80) NOT NULL DEFAULT 'stripe',
  mode ENUM('test','live') NOT NULL DEFAULT 'test',
  state_hash CHAR(64) NOT NULL,
  redirect_uri VARCHAR(1000) NOT NULL,
  return_path VARCHAR(500) NOT NULL DEFAULT '/merchant-payments.php',
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_connect_oauth_state_hash (state_hash),
  KEY idx_payment_connect_oauth_merchant (merchant_user_id,provider_key,mode,expires_at),
  CONSTRAINT fk_payment_connect_oauth_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @mg_has_connection_method := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_provider_accounts' AND COLUMN_NAME='connection_method'
);
SET @mg_sql := IF(@mg_has_connection_method=0,
  "ALTER TABLE payment_provider_accounts ADD COLUMN connection_method VARCHAR(40) NOT NULL DEFAULT 'express_account_link' AFTER provider_account_reference",
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_account_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_provider_accounts' AND COLUMN_NAME='account_type'
);
SET @mg_sql := IF(@mg_has_account_type=0,
  'ALTER TABLE payment_provider_accounts ADD COLUMN account_type VARCHAR(40) NULL AFTER connection_method',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_oauth_scope := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_provider_accounts' AND COLUMN_NAME='oauth_scope'
);
SET @mg_sql := IF(@mg_has_oauth_scope=0,
  'ALTER TABLE payment_provider_accounts ADD COLUMN oauth_scope VARCHAR(40) NULL AFTER account_type',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_connected_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_provider_accounts' AND COLUMN_NAME='connected_at'
);
SET @mg_sql := IF(@mg_has_connected_at=0,
  'ALTER TABLE payment_provider_accounts ADD COLUMN connected_at DATETIME NULL AFTER last_synced_at',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_disconnected_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_provider_accounts' AND COLUMN_NAME='disconnected_at'
);
SET @mg_sql := IF(@mg_has_disconnected_at=0,
  'ALTER TABLE payment_provider_accounts ADD COLUMN disconnected_at DATETIME NULL AFTER connected_at',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('merchant.payments.manage','Manage merchant payment connection','Connect, refresh, or disconnect the merchant Stripe account.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug='merchant.payments.manage'
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_v1g_stripe_connect_oauth','Official Stripe Connect Standard OAuth onboarding, replay-safe state storage, connection metadata, and merchant management permission.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
