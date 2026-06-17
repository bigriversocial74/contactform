-- Microgifter Stage 3 Gift Activity Persistence
-- Server-authoritative gifts, lifecycle events, notifications, and message threads.

CREATE TABLE IF NOT EXISTS gifts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(32) NOT NULL,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  recipient_user_id BIGINT UNSIGNED NULL,
  recipient_name VARCHAR(120) NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NULL,
  gift_type VARCHAR(80) NOT NULL,
  value_cents INT UNSIGNED NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  status ENUM('draft','sent','delivered','claimed','expired','cancelled') NOT NULL DEFAULT 'draft',
  metadata_json JSON NULL,
  sent_at DATETIME NULL,
  delivered_at DATETIME NULL,
  claimed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gifts_public_id (public_id),
  KEY idx_gifts_sender_status (sender_user_id, status, updated_at),
  KEY idx_gifts_recipient_status (recipient_user_id, status, updated_at),
  CONSTRAINT fk_gifts_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_gifts_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gift_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  gift_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  event_type ENUM('created','sent','delivered','viewed','claimed','message','tipped','cancelled','expired') NOT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gift_events_gift_created (gift_id, created_at),
  KEY idx_gift_events_actor_created (actor_user_id, created_at),
  CONSTRAINT fk_gift_events_gift FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_gift_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_threads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  gift_id BIGINT UNSIGNED NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  subject VARCHAR(160) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_message_threads_public_id (public_id),
  KEY idx_message_threads_gift (gift_id),
  CONSTRAINT fk_message_threads_gift FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_message_threads_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_thread_participants (
  thread_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  last_read_at DATETIME NULL,
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (thread_id, user_id),
  KEY idx_message_participants_user (user_id, last_read_at),
  CONSTRAINT fk_message_participants_thread FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_participants_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  thread_id BIGINT UNSIGNED NOT NULL,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_messages_public_id (public_id),
  KEY idx_messages_thread_created (thread_id, created_at),
  CONSTRAINT fk_messages_thread FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT chk_messages_body_nonempty CHECK (CHAR_LENGTH(TRIM(body)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(80) NOT NULL,
  title VARCHAR(160) NOT NULL,
  body VARCHAR(500) NULL,
  action_url VARCHAR(255) NULL,
  gift_id BIGINT UNSIGNED NULL,
  thread_id BIGINT UNSIGNED NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notifications_public_id (public_id),
  KEY idx_notifications_user_read_created (user_id, read_at, created_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_gift FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_notifications_thread FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug, name, description, created_at) VALUES
('gift.activity.view', 'View gift activity', 'View owned or received gift activity.', NOW()),
('gift.message.send', 'Send gift messages', 'Send messages within authorized gift threads.', NOW()),
('notification.view', 'View notifications', 'View and mark own notifications read.', NOW());

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('gift.activity.view','gift.message.send','notification.view')
WHERE r.slug IN ('customer','merchant','admin','super_admin');