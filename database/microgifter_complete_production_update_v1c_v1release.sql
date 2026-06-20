-- ============================================================================
-- Microgifter Complete Production Update: V1C through V1 Release Hardening
-- ============================================================================
--
-- Intended starting point:
--   microgifter_complete_production_update_stage18f_stage18m.sql was the last
--   production SQL package imported successfully.
--
-- Included migrations, in required order:
--   1. stage_v1c_checkout_session_intent_authority.sql
--   2. stage_v1d_transfer_conversations.sql
--   3. stage_v1f_stripe_payments.sql
--   4. stage_v1_release_trigger_portability.sql
--
-- Import this file once through phpMyAdmin after uploading the matching current
-- application files. Create a database backup before importing.
--
-- The statements are written to be safe to re-run after a partial import:
-- existing columns, indexes, constraints, tables, and migration markers are
-- detected or updated without duplicating them.
-- ============================================================================

SET NAMES utf8mb4;

-- ============================================================================
-- 1. V1 Stage C: checkout session to payment intent authority
-- ============================================================================
-- Every checkout session resolves one specific payment intent. Legacy rows are
-- backfilled to the nearest compatible intent for the same order and provider.

SET @mg_has_checkout_session_intent := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'checkout_sessions'
    AND COLUMN_NAME = 'payment_intent_id'
);
SET @mg_sql := IF(
  @mg_has_checkout_session_intent = 0,
  'ALTER TABLE checkout_sessions ADD COLUMN payment_intent_id BIGINT UNSIGNED NULL AFTER order_id',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

UPDATE checkout_sessions cs
SET cs.payment_intent_id = (
  SELECT pi.id
  FROM payment_intents pi
  WHERE pi.order_id = cs.order_id
    AND pi.provider_key = cs.provider_key
  ORDER BY
    CASE WHEN pi.created_at <= cs.created_at THEN 0 ELSE 1 END,
    ABS(TIMESTAMPDIFF(SECOND, pi.created_at, cs.created_at)),
    pi.id DESC
  LIMIT 1
)
WHERE cs.payment_intent_id IS NULL;

SET @mg_has_checkout_session_intent_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'checkout_sessions'
    AND INDEX_NAME = 'idx_checkout_sessions_payment_intent'
);
SET @mg_sql := IF(
  @mg_has_checkout_session_intent_index = 0,
  'ALTER TABLE checkout_sessions ADD KEY idx_checkout_sessions_payment_intent (payment_intent_id)',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_checkout_session_intent_fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'checkout_sessions'
    AND CONSTRAINT_NAME = 'fk_checkout_sessions_payment_intent'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @mg_sql := IF(
  @mg_has_checkout_session_intent_fk = 0,
  'ALTER TABLE checkout_sessions ADD CONSTRAINT fk_checkout_sessions_payment_intent FOREIGN KEY (payment_intent_id) REFERENCES payment_intents(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_v1c_checkout_session_intent_authority',
  'Bind each checkout session to its canonical payment intent.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- ============================================================================
-- 2. V1 Stage D: transfer-scoped Microgift conversations
-- ============================================================================
-- A regift starts a new private sender/recipient conversation. Historical
-- messages remain isolated from later owners of the same Microgift instance.

SET @mg_has_conversation_key := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='message_threads'
    AND COLUMN_NAME='conversation_key'
);
SET @mg_sql := IF(
  @mg_has_conversation_key=0,
  'ALTER TABLE message_threads ADD COLUMN conversation_key VARCHAR(190) NULL DEFAULT ''legacy'' AFTER microgift_instance_id',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

UPDATE message_threads
SET conversation_key=CONCAT('legacy:',public_id)
WHERE conversation_key IS NULL OR conversation_key='' OR conversation_key='legacy';

-- Add a non-unique supporting index before dropping the old unique index so the
-- existing foreign key on microgift_instance_id always remains indexed.
SET @mg_has_instance_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='message_threads'
    AND INDEX_NAME='idx_message_threads_microgift_instance'
);
SET @mg_sql := IF(
  @mg_has_instance_index=0,
  'ALTER TABLE message_threads ADD KEY idx_message_threads_microgift_instance (microgift_instance_id)',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_old_unique := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='message_threads'
    AND INDEX_NAME='uq_message_threads_microgift_instance'
);
SET @mg_sql := IF(
  @mg_has_old_unique>0,
  'ALTER TABLE message_threads DROP INDEX uq_message_threads_microgift_instance',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_conversation_unique := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='message_threads'
    AND INDEX_NAME='uq_message_threads_microgift_conversation'
);
SET @mg_sql := IF(
  @mg_has_conversation_unique=0,
  'ALTER TABLE message_threads ADD UNIQUE KEY uq_message_threads_microgift_conversation (microgift_instance_id,conversation_key)',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

ALTER TABLE message_threads
  MODIFY COLUMN conversation_key VARCHAR(190) NOT NULL DEFAULT 'legacy';

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_v1d_transfer_conversations',
  'Transfer-scoped Microgift conversations isolate regift participants and follow-up history.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- ============================================================================
-- 3. V1 Stage F: Stripe payments and Connect
-- ============================================================================
-- Adds test/live credentials, Connect onboarding, platform fees, hosted
-- checkout references, webhook reconciliation, and live-readiness state.

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
  CONSTRAINT fk_payment_platform_credentials_updated_by
    FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @mg_has_provider_checkout_url := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='checkout_sessions'
    AND COLUMN_NAME='provider_checkout_url'
);
SET @mg_sql := IF(
  @mg_has_provider_checkout_url=0,
  'ALTER TABLE checkout_sessions ADD COLUMN provider_checkout_url VARCHAR(1000) NULL AFTER provider_session_reference',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_application_fee := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='payment_intents'
    AND COLUMN_NAME='application_fee_cents'
);
SET @mg_sql := IF(
  @mg_has_application_fee=0,
  'ALTER TABLE payment_intents ADD COLUMN application_fee_cents BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER currency',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_destination_account := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='payment_intents'
    AND COLUMN_NAME='destination_account_reference'
);
SET @mg_sql := IF(
  @mg_has_destination_account=0,
  'ALTER TABLE payment_intents ADD COLUMN destination_account_reference VARCHAR(190) NULL AFTER application_fee_cents',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_details_submitted := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='payment_provider_accounts'
    AND COLUMN_NAME='details_submitted'
);
SET @mg_sql := IF(
  @mg_has_details_submitted=0,
  'ALTER TABLE payment_provider_accounts ADD COLUMN details_submitted TINYINT(1) NOT NULL DEFAULT 0 AFTER payouts_enabled',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_onboarding_status := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='payment_provider_accounts'
    AND COLUMN_NAME='onboarding_status'
);
SET @mg_sql := IF(
  @mg_has_onboarding_status=0,
  "ALTER TABLE payment_provider_accounts ADD COLUMN onboarding_status ENUM('not_started','pending','complete','restricted','disabled') NOT NULL DEFAULT 'not_started' AFTER details_submitted",
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_requirements_due := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='payment_provider_accounts'
    AND COLUMN_NAME='requirements_due_json'
);
SET @mg_sql := IF(
  @mg_has_requirements_due=0,
  'ALTER TABLE payment_provider_accounts ADD COLUMN requirements_due_json JSON NULL AFTER capabilities_json',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_last_synced := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='payment_provider_accounts'
    AND COLUMN_NAME='last_synced_at'
);
SET @mg_sql := IF(
  @mg_has_last_synced=0,
  'ALTER TABLE payment_provider_accounts ADD COLUMN last_synced_at DATETIME NULL AFTER requirements_due_json',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_v1f_stripe_payments',
  'Stripe credentials, hosted checkout, Connect onboarding, application fees, webhooks, and readiness.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- ============================================================================
-- 4. V1 Release Hardening: dump-portable catalog moderation trigger
-- ============================================================================
-- Recreates the trigger without retaining a trailing statement terminator in
-- SHOW CREATE TRIGGER or mysqldump output.

DROP TRIGGER IF EXISTS trg_catalog_assets_review_state;

DELIMITER $$
CREATE TRIGGER trg_catalog_assets_review_state
BEFORE UPDATE ON catalog_assets
FOR EACH ROW
SET NEW.status = IF(
  NEW.moderation_status IN ('quarantined','blocked','takedown','removed'),
  'quarantined',
  IF(
    OLD.moderation_status IN ('quarantined','blocked','takedown','removed')
      AND NEW.moderation_status IN ('approved','unreviewed','clear'),
    'ready',
    NEW.status
  )
)
$$
DELIMITER ;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_v1_release_trigger_portability',
  'Recreate the catalog moderation trigger with dump-portable SQL.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- ============================================================================
-- Completion verification
-- ============================================================================

SELECT migration_key, description, applied_at
FROM schema_migrations
WHERE migration_key IN (
  'stage_v1c_checkout_session_intent_authority',
  'stage_v1d_transfer_conversations',
  'stage_v1f_stripe_payments',
  'stage_v1_release_trigger_portability'
)
ORDER BY FIELD(
  migration_key,
  'stage_v1c_checkout_session_intent_authority',
  'stage_v1d_transfer_conversations',
  'stage_v1f_stripe_payments',
  'stage_v1_release_trigger_portability'
);
