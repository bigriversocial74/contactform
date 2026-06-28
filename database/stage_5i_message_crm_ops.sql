CREATE TABLE IF NOT EXISTS message_thread_crm_state (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  thread_id BIGINT UNSIGNED NOT NULL,
  status ENUM('open','resolved') NOT NULL DEFAULT 'open',
  label VARCHAR(80) NULL,
  assigned_user_id BIGINT UNSIGNED NULL,
  resolved_by_user_id BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_message_thread_crm_state_public_id (public_id),
  UNIQUE KEY uq_message_thread_crm_state_thread (thread_id),
  KEY idx_message_thread_crm_state_status (status,label,updated_at),
  KEY idx_message_thread_crm_state_assigned (assigned_user_id,status,updated_at),
  CONSTRAINT fk_message_thread_crm_state_thread FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_thread_crm_state_assigned FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_message_thread_crm_state_resolved_by FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_message_thread_crm_state_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_message_thread_crm_state_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_thread_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  thread_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  note_body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_message_thread_notes_public_id (public_id),
  UNIQUE KEY uq_message_thread_notes_thread_user (thread_id,user_id),
  KEY idx_message_thread_notes_thread (thread_id,updated_at),
  CONSTRAINT fk_message_thread_notes_thread FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_thread_notes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_thread_drafts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  thread_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_message_thread_drafts_public_id (public_id),
  UNIQUE KEY uq_message_thread_drafts_thread_user (thread_id,user_id),
  KEY idx_message_thread_drafts_user (user_id,updated_at),
  CONSTRAINT fk_message_thread_drafts_thread FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_thread_drafts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
