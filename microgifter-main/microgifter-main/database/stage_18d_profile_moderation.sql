-- Stage 18D Profile Moderation Foundation
-- Canonical administrative cases, actions, and owner appeals for public profiles.
-- Safe to rerun after successful import.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS profile_moderation_cases (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  profile_id BIGINT UNSIGNED NOT NULL,
  opened_by_user_id BIGINT UNSIGNED NULL,
  reporter_user_id BIGINT UNSIGNED NULL,
  assigned_user_id BIGINT UNSIGNED NULL,
  source ENUM('admin','user_report','automated','appeal') NOT NULL DEFAULT 'admin',
  category ENUM('impersonation','harassment','spam','fraud','unsafe_content','copyright','privacy','policy','other') NOT NULL DEFAULT 'other',
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status ENUM('open','in_review','actioned','resolved','dismissed','appealed') NOT NULL DEFAULT 'open',
  summary VARCHAR(220) NOT NULL,
  details TEXT NULL,
  evidence_json JSON NULL,
  opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_profile_moderation_cases_public_id (public_id),
  KEY idx_profile_moderation_cases_queue (status, priority, opened_at, id),
  KEY idx_profile_moderation_cases_profile (profile_id, status, opened_at),
  KEY idx_profile_moderation_cases_assignee (assigned_user_id, status, updated_at),
  CONSTRAINT fk_profile_moderation_cases_profile FOREIGN KEY (profile_id) REFERENCES public_profiles(id) ON DELETE CASCADE,
  CONSTRAINT fk_profile_moderation_cases_opened_by FOREIGN KEY (opened_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_profile_moderation_cases_reporter FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_profile_moderation_cases_assignee FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profile_moderation_actions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  case_id BIGINT UNSIGNED NOT NULL,
  profile_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  actor_type ENUM('moderator','owner','system') NOT NULL DEFAULT 'moderator',
  action_type ENUM('case_opened','claim','note','warn','hide','suspend','restore','dismiss','escalate','appeal_submitted','appeal_accept','appeal_deny') NOT NULL,
  reason_code VARCHAR(80) NULL,
  reason_text TEXT NULL,
  previous_profile_status ENUM('draft','active','hidden','suspended') NULL,
  resulting_profile_status ENUM('draft','active','hidden','suspended') NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_profile_moderation_actions_public_id (public_id),
  KEY idx_profile_moderation_actions_case (case_id, created_at, id),
  KEY idx_profile_moderation_actions_profile (profile_id, created_at, id),
  KEY idx_profile_moderation_actions_actor (actor_user_id, created_at),
  CONSTRAINT fk_profile_moderation_actions_case FOREIGN KEY (case_id) REFERENCES profile_moderation_cases(id) ON DELETE CASCADE,
  CONSTRAINT fk_profile_moderation_actions_profile FOREIGN KEY (profile_id) REFERENCES public_profiles(id) ON DELETE CASCADE,
  CONSTRAINT fk_profile_moderation_actions_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profile_moderation_appeals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  case_id BIGINT UNSIGNED NOT NULL,
  profile_id BIGINT UNSIGNED NOT NULL,
  appellant_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('submitted','in_review','accepted','denied','withdrawn') NOT NULL DEFAULT 'submitted',
  statement TEXT NOT NULL,
  decision_reason TEXT NULL,
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_profile_moderation_appeals_public_id (public_id),
  UNIQUE KEY uq_profile_moderation_appeals_case (case_id),
  KEY idx_profile_moderation_appeals_profile (profile_id, status, submitted_at),
  KEY idx_profile_moderation_appeals_appellant (appellant_user_id, submitted_at),
  CONSTRAINT fk_profile_moderation_appeals_case FOREIGN KEY (case_id) REFERENCES profile_moderation_cases(id) ON DELETE CASCADE,
  CONSTRAINT fk_profile_moderation_appeals_profile FOREIGN KEY (profile_id) REFERENCES public_profiles(id) ON DELETE CASCADE,
  CONSTRAINT fk_profile_moderation_appeals_appellant FOREIGN KEY (appellant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_profile_moderation_appeals_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug, name) VALUES
('admin.profiles.moderation.view', 'View profile moderation'),
('admin.profiles.moderation.manage', 'Manage profile moderation');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.slug IN ('admin.profiles.moderation.view','admin.profiles.moderation.manage')
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key, description, checksum, applied_at)
VALUES ('stage_18d_profile_moderation', 'Profile moderation cases actions appeals and permissions', NULL, NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description), applied_at=applied_at;

COMMIT;
