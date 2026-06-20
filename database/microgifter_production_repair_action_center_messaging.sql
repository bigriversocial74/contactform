-- ============================================================================
-- Microgifter Production Repair: Action Center Messaging Prerequisite
-- ============================================================================
--
-- Use this file only when the consolidated V1 production update stops with:
--   #1054 Unknown column 'microgift_instance_id' in 'message_threads'
--
-- The live database is missing the Stage 11G durable messaging schema that the
-- V1D transfer-conversation update expects. This repair is idempotent and can be
-- imported after a partial V1C/V1D production update.
--
-- After this repair succeeds, import
--   microgifter_complete_production_update_v1c_v1release.sql
-- again from the beginning. Its completed V1C statements are safe to rerun.
-- ============================================================================

SET NAMES utf8mb4;

-- Ensure the older PPPM thread link exists before placing the Microgift link.
SET @mg_has_thread_pppm_item := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='message_threads'
    AND COLUMN_NAME='pppm_item_id'
);
SET @mg_sql := IF(
  @mg_has_thread_pppm_item=0,
  'ALTER TABLE message_threads ADD COLUMN pppm_item_id BIGINT UNSIGNED NULL AFTER gift_id',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

-- Add the missing Stage 11G Microgift authority column.
SET @mg_has_microgift_instance_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='message_threads'
    AND COLUMN_NAME='microgift_instance_id'
);
SET @mg_sql := IF(
  @mg_has_microgift_instance_id=0,
  'ALTER TABLE message_threads ADD COLUMN microgift_instance_id BIGINT UNSIGNED NULL AFTER pppm_item_id',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_thread_microgift_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='message_threads'
    AND INDEX_NAME='uq_message_threads_microgift_instance'
);
SET @mg_sql := IF(
  @mg_has_thread_microgift_index=0,
  'ALTER TABLE message_threads ADD UNIQUE KEY uq_message_threads_microgift_instance (microgift_instance_id)',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_thread_microgift_fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND TABLE_NAME='message_threads'
    AND CONSTRAINT_NAME='fk_message_threads_microgift_instance'
    AND CONSTRAINT_TYPE='FOREIGN KEY'
);
SET @mg_sql := IF(
  @mg_has_thread_microgift_fk=0,
  'ALTER TABLE message_threads ADD CONSTRAINT fk_message_threads_microgift_instance FOREIGN KEY (microgift_instance_id) REFERENCES microgift_instances(id) ON DELETE RESTRICT',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

-- Complete the remaining Stage 11G message fields while the schema is repaired.
SET @mg_has_message_recipient := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='messages'
    AND COLUMN_NAME='recipient_user_id'
);
SET @mg_sql := IF(
  @mg_has_message_recipient=0,
  'ALTER TABLE messages ADD COLUMN recipient_user_id BIGINT UNSIGNED NULL AFTER sender_user_id',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_message_idempotency := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='messages'
    AND COLUMN_NAME='idempotency_key'
);
SET @mg_sql := IF(
  @mg_has_message_idempotency=0,
  'ALTER TABLE messages ADD COLUMN idempotency_key VARCHAR(190) NULL AFTER body',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_message_source_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='messages'
    AND COLUMN_NAME='source_type'
);
SET @mg_sql := IF(
  @mg_has_message_source_type=0,
  'ALTER TABLE messages ADD COLUMN source_type VARCHAR(80) NOT NULL DEFAULT ''messaging'' AFTER idempotency_key',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_message_source_reference := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='messages'
    AND COLUMN_NAME='source_reference'
);
SET @mg_sql := IF(
  @mg_has_message_source_reference=0,
  'ALTER TABLE messages ADD COLUMN source_reference VARCHAR(190) NULL AFTER source_type',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_message_idempotency_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='messages'
    AND INDEX_NAME='uq_messages_sender_idempotency'
);
SET @mg_sql := IF(
  @mg_has_message_idempotency_index=0,
  'ALTER TABLE messages ADD UNIQUE KEY uq_messages_sender_idempotency (sender_user_id,idempotency_key)',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

SET @mg_has_message_recipient_fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND TABLE_NAME='messages'
    AND CONSTRAINT_NAME='fk_messages_recipient_user'
    AND CONSTRAINT_TYPE='FOREIGN KEY'
);
SET @mg_sql := IF(
  @mg_has_message_recipient_fk=0,
  'ALTER TABLE messages ADD CONSTRAINT fk_messages_recipient_user FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE mg_stmt FROM @mg_sql;
EXECUTE mg_stmt;
DEALLOCATE PREPARE mg_stmt;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_11g_action_center_durable_messaging',
  'Durable Action Center message threads, participant authorization, message idempotency, and delivery integration.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='message_threads' AND COLUMN_NAME='microgift_instance_id') AS microgift_instance_column_ready,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='messages' AND COLUMN_NAME='recipient_user_id') AS message_recipient_column_ready,
  (SELECT COUNT(*) FROM schema_migrations
   WHERE migration_key='stage_11g_action_center_durable_messaging') AS stage_11g_marker_ready;
