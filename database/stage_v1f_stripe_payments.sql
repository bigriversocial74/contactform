-- V1 Stage F: Stripe test/live credentials, Connect onboarding, platform fees,
-- hosted checkout references, webhook reconciliation, and live-readiness state.

CREATE TABLE IF NOT EXISTS payment_platform_credentials (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  provider_key VARCHAR(80) NOT NULL,
  mode ENUM('test','live') NOT NULL DEFAULT 'test',
  publishable_key VARCHAR(255) NULL,
  secret_key_ciphertext TEXT NULL,
  webhook_secret_ciphertext TEXT NULL,
  connect_client_id VARCHAR(255) NULL,
  platform_fee_bps SMALLINT UNSIGNED NOT NULL DEFAULT 1500,
  fixed_fee_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  updated_by_user_id BIGINT UNSIGNED NULL,
  last_validated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_platform_credentials_public_id (public_id),
  UNIQUE KEY uq_payment_platform_credentials_provider_mode (provider_key,mode),
  CONSTRAINT fk_payment_platform_credentials_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @mg_has_provider_checkout_url := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='checkout_sessions' AND COLUMN_NAME='provider_checkout_url'
);
SET @mg_sql := IF(@mg_has_provider_checkout_url=0,
  'ALTER TABLE checkout_sessions ADD COLUMN provider_checkout_url VARCHAR(1000) NULL AFTER provider_session_reference',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_application_fee := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_intents' AND COLUMN_NAME='application_fee_cents'
);
SET @mg_sql := IF(@mg_has_application_fee=0,
  'ALTER TABLE payment_intents ADD COLUMN application_fee_cents BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER currency',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_destination_account := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_intents' AND COLUMN_NAME='destination_account_reference'
);
SET @mg_sql := IF(@mg_has_destination_account=0,
  'ALTER TABLE payment_intents ADD COLUMN destination_account_reference VARCHAR(190) NULL AFTER application_fee_cents',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_details_submitted := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_provider_accounts' AND COLUMN_NAME='details_submitted'
);
SET @mg_sql := IF(@mg_has_details_submitted=0,
  'ALTER TABLE payment_provider_accounts ADD COLUMN details_submitted TINYINT(1) NOT NULL DEFAULT 0 AFTER payouts_enabled',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_onboarding_status := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_provider_accounts' AND COLUMN_NAME='onboarding_status'
);
SET @mg_sql := IF(@mg_has_onboarding_status=0,
  "ALTER TABLE payment_provider_accounts ADD COLUMN onboarding_status ENUM('not_started','pending','complete','restricted','disabled') NOT NULL DEFAULT 'not_started' AFTER details_submitted",
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_requirements_due := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_provider_accounts' AND COLUMN_NAME='requirements_due_json'
);
SET @mg_sql := IF(@mg_has_requirements_due=0,
  'ALTER TABLE payment_provider_accounts ADD COLUMN requirements_due_json JSON NULL AFTER capabilities_json',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

SET @mg_has_last_synced := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_provider_accounts' AND COLUMN_NAME='last_synced_at'
);
SET @mg_sql := IF(@mg_has_last_synced=0,
  'ALTER TABLE payment_provider_accounts ADD COLUMN last_synced_at DATETIME NULL AFTER requirements_due_json',
  'SELECT 1');
PREPARE mg_stmt FROM @mg_sql; EXECUTE mg_stmt; DEALLOCATE PREPARE mg_stmt;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_v1f_stripe_payments','Stripe credentials, hosted checkout, Connect onboarding, application fees, webhooks, and readiness.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
