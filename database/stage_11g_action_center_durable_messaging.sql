-- Stage 11G Action Center durable messaging authority
-- Links canonical message threads and messages to Microgift instances.

SET @has_microgift_instance_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='message_threads' AND COLUMN_NAME='microgift_instance_id'
);
SET @sql := IF(
  @has_microgift_instance_id=0,
  'ALTER TABLE message_threads ADD COLUMN microgift_instance_id BIGINT UNSIGNED NULL AFTER pppm_item_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_thread_microgift_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='message_threads' AND INDEX_NAME='uq_message_threads_microgift_instance'
);
SET @sql := IF(
  @has_thread_microgift_index=0,
  'ALTER TABLE message_threads ADD UNIQUE KEY uq_message_threads_microgift_instance (microgift_instance_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_thread_microgift_fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='message_threads' AND CONSTRAINT_NAME='fk_message_threads_microgift_instance'
);
SET @sql := IF(
  @has_thread_microgift_fk=0,
  'ALTER TABLE message_threads ADD CONSTRAINT fk_message_threads_microgift_instance FOREIGN KEY (microgift_instance_id) REFERENCES microgift_instances(id) ON DELETE RESTRICT',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_message_recipient := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='messages' AND COLUMN_NAME='recipient_user_id'
);
SET @sql := IF(
  @has_message_recipient=0,
  'ALTER TABLE messages ADD COLUMN recipient_user_id BIGINT UNSIGNED NULL AFTER sender_user_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_message_idempotency := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='messages' AND COLUMN_NAME='idempotency_key'
);
SET @sql := IF(
  @has_message_idempotency=0,
  'ALTER TABLE messages ADD COLUMN idempotency_key VARCHAR(190) NULL AFTER body',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_message_source_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='messages' AND COLUMN_NAME='source_type'
);
SET @sql := IF(
  @has_message_source_type=0,
  'ALTER TABLE messages ADD COLUMN source_type VARCHAR(80) NOT NULL DEFAULT ''messaging'' AFTER idempotency_key',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_message_source_reference := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='messages' AND COLUMN_NAME='source_reference'
);
SET @sql := IF(
  @has_message_source_reference=0,
  'ALTER TABLE messages ADD COLUMN source_reference VARCHAR(190) NULL AFTER source_type',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_message_idempotency_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='messages' AND INDEX_NAME='uq_messages_sender_idempotency'
);
SET @sql := IF(
  @has_message_idempotency_index=0,
  'ALTER TABLE messages ADD UNIQUE KEY uq_messages_sender_idempotency (sender_user_id,idempotency_key)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_message_recipient_fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='messages' AND CONSTRAINT_NAME='fk_messages_recipient_user'
);
SET @sql := IF(
  @has_message_recipient_fk=0,
  'ALTER TABLE messages ADD CONSTRAINT fk_messages_recipient_user FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_11g_action_center_durable_messaging','Durable Action Center message threads, participant authorization, message idempotency, and delivery integration.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
