-- Stage 18G PPPM sent/resend delivery timeline
-- Every send and resend is an immutable timestamped event for one Microgift/PPPM unit.

CREATE TABLE IF NOT EXISTS microgift_delivery_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  instance_id BIGINT UNSIGNED NOT NULL,
  pppm_item_id BIGINT UNSIGNED NULL,
  event_type ENUM('sent','resent','delivered') NOT NULL,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  recipient_user_id BIGINT UNSIGNED NOT NULL,
  action_item_public_id CHAR(36) NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  occurred_at DATETIME NOT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgift_delivery_public_id (public_id),
  UNIQUE KEY uq_microgift_delivery_idempotency (idempotency_key),
  KEY idx_microgift_delivery_instance_time (instance_id,occurred_at,id),
  KEY idx_microgift_delivery_pppm_time (pppm_item_id,occurred_at,id),
  KEY idx_microgift_delivery_sender_time (sender_user_id,occurred_at,id),
  KEY idx_microgift_delivery_recipient_time (recipient_user_id,occurred_at,id),
  CONSTRAINT fk_microgift_delivery_instance
    FOREIGN KEY (instance_id) REFERENCES microgift_instances(id) ON DELETE RESTRICT,
  CONSTRAINT fk_microgift_delivery_pppm
    FOREIGN KEY (pppm_item_id) REFERENCES pppm_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_microgift_delivery_sender
    FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_microgift_delivery_recipient
    FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_18g_pppm_resend_timeline',
  'Adds immutable timestamped send, resend, and delivery events for each PPPM-linked Microgift.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);
