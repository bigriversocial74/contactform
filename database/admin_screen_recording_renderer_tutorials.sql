-- Microgifter Admin Screen Recording Renderer + Voiceover + Tutorials
-- Stage 3: FFmpeg export jobs, audio tracks, and public tutorial publishing
-- Safe to run more than once: uses CREATE TABLE IF NOT EXISTS and INSERT IGNORE.

CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  migration_key VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_schema_migrations_key (migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_screen_recording_export_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  recording_id BIGINT UNSIGNED NOT NULL,
  version_id BIGINT UNSIGNED NULL,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  job_type ENUM('render','thumbnail','audio_mix','transcode') NOT NULL DEFAULT 'render',
  renderer ENUM('ffmpeg','browser_fallback','manual') NOT NULL DEFAULT 'ffmpeg',
  requested_format ENUM('webm','mp4') NOT NULL DEFAULT 'webm',
  burn_overlays TINYINT(1) NOT NULL DEFAULT 1,
  include_audio TINYINT(1) NOT NULL DEFAULT 1,
  mute_original_audio TINYINT(1) NOT NULL DEFAULT 0,
  original_audio_volume DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  voiceover_volume DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  status ENUM('queued','processing','exported','failed','cancelled') NOT NULL DEFAULT 'queued',
  priority TINYINT UNSIGNED NOT NULL DEFAULT 5,
  input_path VARCHAR(700) NULL,
  output_path VARCHAR(700) NULL,
  thumbnail_path VARCHAR(700) NULL,
  log_path VARCHAR(700) NULL,
  ffmpeg_command_hash CHAR(64) NULL,
  error_message VARCHAR(500) NULL,
  edit_manifest_json LONGTEXT NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_screen_recording_export_jobs_public_id (public_id),
  KEY idx_admin_screen_recording_export_jobs_recording (recording_id),
  KEY idx_admin_screen_recording_export_jobs_version (version_id),
  KEY idx_admin_screen_recording_export_jobs_admin (admin_user_id),
  KEY idx_admin_screen_recording_export_jobs_status (status, priority, created_at),
  KEY idx_admin_screen_recording_export_jobs_created (created_at),
  CONSTRAINT fk_asrej_recording FOREIGN KEY (recording_id) REFERENCES admin_screen_recordings(id) ON DELETE CASCADE,
  CONSTRAINT fk_asrej_version FOREIGN KEY (version_id) REFERENCES admin_screen_recording_versions(id) ON DELETE SET NULL,
  CONSTRAINT fk_asrej_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_screen_recording_audio_tracks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  recording_id BIGINT UNSIGNED NOT NULL,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  track_type ENUM('voiceover','uploaded_audio','original_audio_reference') NOT NULL DEFAULT 'voiceover',
  title VARCHAR(180) NOT NULL DEFAULT 'Voiceover',
  file_path VARCHAR(700) NULL,
  original_filename VARCHAR(255) NULL,
  mime_type VARCHAR(120) NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  duration_seconds DECIMAL(12,3) NULL,
  start_seconds DECIMAL(12,3) NOT NULL DEFAULT 0,
  volume DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  status ENUM('draft','ready','used','archived','failed') NOT NULL DEFAULT 'draft',
  error_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_screen_recording_audio_tracks_public_id (public_id),
  KEY idx_admin_screen_recording_audio_tracks_recording (recording_id),
  KEY idx_admin_screen_recording_audio_tracks_admin (admin_user_id),
  KEY idx_admin_screen_recording_audio_tracks_status (status),
  KEY idx_admin_screen_recording_audio_tracks_deleted (deleted_at),
  CONSTRAINT fk_asrat_recording FOREIGN KEY (recording_id) REFERENCES admin_screen_recordings(id) ON DELETE CASCADE,
  CONSTRAINT fk_asrat_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS public_tutorials (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  recording_id BIGINT UNSIGNED NULL,
  version_id BIGINT UNSIGNED NULL,
  export_job_id BIGINT UNSIGNED NULL,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(180) NOT NULL,
  summary TEXT NULL,
  body LONGTEXT NULL,
  category VARCHAR(120) NULL,
  difficulty ENUM('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
  status ENUM('draft','published','unlisted','archived') NOT NULL DEFAULT 'draft',
  featured TINYINT(1) NOT NULL DEFAULT 0,
  video_path VARCHAR(700) NULL,
  thumbnail_path VARCHAR(700) NULL,
  duration_seconds DECIMAL(12,3) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_public_tutorials_public_id (public_id),
  UNIQUE KEY uq_public_tutorials_slug (slug),
  KEY idx_public_tutorials_recording (recording_id),
  KEY idx_public_tutorials_version (version_id),
  KEY idx_public_tutorials_export_job (export_job_id),
  KEY idx_public_tutorials_admin (admin_user_id),
  KEY idx_public_tutorials_status (status, published_at),
  KEY idx_public_tutorials_featured (featured, status),
  KEY idx_public_tutorials_category (category),
  KEY idx_public_tutorials_deleted (deleted_at),
  CONSTRAINT fk_public_tutorials_recording FOREIGN KEY (recording_id) REFERENCES admin_screen_recordings(id) ON DELETE SET NULL,
  CONSTRAINT fk_public_tutorials_version FOREIGN KEY (version_id) REFERENCES admin_screen_recording_versions(id) ON DELETE SET NULL,
  CONSTRAINT fk_public_tutorials_export_job FOREIGN KEY (export_job_id) REFERENCES admin_screen_recording_export_jobs(id) ON DELETE SET NULL,
  CONSTRAINT fk_public_tutorials_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug, name) VALUES
('admin.tutorials.view', 'View admin tutorials'),
('admin.tutorials.manage', 'Create and manage admin tutorials'),
('admin.screen_recordings.render', 'Render admin screen recording exports'),
('admin.screen_recordings.publish', 'Publish screen recordings as tutorials');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.slug IN (
  'admin.tutorials.view',
  'admin.tutorials.manage',
  'admin.screen_recordings.render',
  'admin.screen_recordings.publish'
)
WHERE r.slug IN ('admin','super_admin');

INSERT IGNORE INTO schema_migrations (migration_key, description) VALUES
('admin_screen_recording_renderer_tutorials_stage3', 'Adds FFmpeg export jobs, voiceover audio tracks, and public tutorials publishing.');
