-- Stage 18P Admin Queue Notifications
-- Adds durable alert and digest records for the admin follow-up queue.

CREATE TABLE IF NOT EXISTS admin_queue_notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  note_id BIGINT UNSIGNED NULL,
  target_user_id BIGINT UNSIGNED NULL,
  assigned_admin_user_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  notification_type ENUM('assigned','overdue','due_soon','escalated','reopened','review_flag','digest') NOT NULL,
  severity ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
  title VARCHAR(160) NOT NULL,
  message VARCHAR(500) NOT NULL,
  metadata_json JSON NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_queue_notifications_public_id (public_id),
  KEY idx_admin_queue_notifications_note_type (note_id,notification_type,created_at),
  KEY idx_admin_queue_notifications_assigned_read (assigned_admin_user_id,read_at,created_at),
  KEY idx_admin_queue_notifications_type_created (notification_type,created_at),
  CONSTRAINT fk_admin_queue_notifications_note FOREIGN KEY (note_id) REFERENCES admin_user_notes(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_queue_notifications_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_queue_notifications_assigned FOREIGN KEY (assigned_admin_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_queue_notifications_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
