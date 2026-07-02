-- Microgifter Admin Screen Recordings + Video Editor
-- Stage 1: admin recording library, browser screen recorder, secure downloads
-- Stage 2: full-page editor shell, timeline model, positioned text overlays

CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  migration_key VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_schema_migrations_key (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_screen_recordings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  original_filename VARCHAR(255) NULL,
  original_path VARCHAR(700) NULL,
  edited_filename VARCHAR(255) NULL,
  edited_path VARCHAR(700) NULL,
  thumbnail_path VARCHAR(700) NULL,
  mime_type VARCHAR(120) NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  duration_seconds DECIMAL(12,3) NULL,
  width INT UNSIGNED NULL,
  height INT UNSIGNED NULL,
  capture_surface VARCHAR(80) NULL,
  status ENUM('recording','processing','saved','edited','export_pending','exported','failed','archived') NOT NULL DEFAULT 'recording',
  edit_manifest_json LONGTEXT NULL,
  error_message VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_screen_recordings_public_id (public_id),
  KEY idx_admin_screen_recordings_admin (admin_user_id),
  KEY idx_admin_screen_recordings_status (status),
  KEY idx_admin_screen_recordings_created (created_at),
  KEY idx_admin_screen_recordings_deleted (deleted_at),
  CONSTRAINT fk_admin_screen_recordings_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_screen_recording_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  recording_id BIGINT UNSIGNED NOT NULL,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  version_label VARCHAR(120) NOT NULL DEFAULT 'Edited export',
  file_path VARCHAR(700) NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  duration_seconds DECIMAL(12,3) NULL,
  edit_manifest_json LONGTEXT NULL,
  status ENUM('draft','export_pending','exported','failed') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_screen_recording_versions_recording (recording_id),
  KEY idx_admin_screen_recording_versions_admin (admin_user_id),
  KEY idx_admin_screen_recording_versions_status (status),
  CONSTRAINT fk_admin_screen_recording_versions_recording FOREIGN KEY (recording_id) REFERENCES admin_screen_recordings(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_screen_recording_versions_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_screen_recording_text_overlays (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  recording_id BIGINT UNSIGNED NOT NULL,
  overlay_key VARCHAR(80) NOT NULL,
  overlay_text VARCHAR(500) NOT NULL,
  start_seconds DECIMAL(12,3) NOT NULL DEFAULT 0,
  end_seconds DECIMAL(12,3) NOT NULL DEFAULT 0,
  x_percent DECIMAL(7,3) NOT NULL DEFAULT 50.000,
  y_percent DECIMAL(7,3) NOT NULL DEFAULT 50.000,
  font_size INT UNSIGNED NOT NULL DEFAULT 28,
  text_color VARCHAR(24) NOT NULL DEFAULT '#ffffff',
  background_color VARCHAR(32) NULL,
  font_weight VARCHAR(24) NOT NULL DEFAULT '700',
  text_align ENUM('left','center','right') NOT NULL DEFAULT 'center',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_screen_recording_overlay_key (recording_id, overlay_key),
  KEY idx_admin_screen_recording_overlays_time (recording_id, start_seconds, end_seconds),
  CONSTRAINT fk_admin_screen_recording_overlays_recording FOREIGN KEY (recording_id) REFERENCES admin_screen_recordings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug, name) VALUES
('admin.screen_recordings.view', 'View admin screen recordings'),
('admin.screen_recordings.manage', 'Create and manage admin screen recordings');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.slug IN ('admin.screen_recordings.view', 'admin.screen_recordings.manage')
WHERE r.slug IN ('admin','super_admin');

INSERT IGNORE INTO schema_migrations (migration_key, description) VALUES
('admin_screen_recordings_stage1_stage2', 'Adds admin screen recording library, secure recording metadata, versions, and text overlay timeline data.');
