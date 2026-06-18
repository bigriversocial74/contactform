-- Stage 18I recipient notification integrity and deduplication

ALTER TABLE notifications
  ADD COLUMN actor_user_id BIGINT UNSIGNED NULL AFTER user_id,
  ADD COLUMN event_key VARCHAR(190) NULL AFTER type,
  ADD COLUMN occurrence_count INT UNSIGNED NOT NULL DEFAULT 1 AFTER event_key,
  ADD COLUMN context_json JSON NULL AFTER thread_id,
  ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD UNIQUE KEY uq_notifications_user_event (user_id,event_key),
  ADD KEY idx_notifications_actor_created (actor_user_id,created_at),
  ADD CONSTRAINT fk_notifications_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_18i_recipient_notifications',
  'Adds actor context, event deduplication, aggregation, and durable recipient notification metadata.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);
