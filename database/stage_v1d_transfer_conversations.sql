-- V1 Stage D: transfer-scoped Microgift conversations
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
VALUES ('stage_v1d_transfer_conversations','Transfer-scoped Microgift conversations isolate regift participants and follow-up history.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
