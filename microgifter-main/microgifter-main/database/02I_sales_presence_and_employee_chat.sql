-- 02I Microgifter sales presence and employee chat
-- Requires 02G CRM schema. Safe to rerun.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS sales_presence (
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('online','away','offline') NOT NULL DEFAULT 'offline',
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  KEY idx_sales_presence_status_seen (status, last_seen_at),
  CONSTRAINT fk_sales_presence_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_chat_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  recipient_user_id BIGINT UNSIGNED NOT NULL,
  message TEXT NOT NULL,
  sent_while_offline TINYINT(1) NOT NULL DEFAULT 0,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_employee_chat_messages_public_id (public_id),
  KEY idx_employee_chat_pair_created (sender_user_id, recipient_user_id, created_at),
  KEY idx_employee_chat_recipient_read (recipient_user_id, read_at, created_at),
  CONSTRAINT fk_employee_chat_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_employee_chat_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
