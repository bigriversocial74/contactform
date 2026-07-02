-- ------------------------------------------------------------
-- Microgifter Story Highlights
-- Saved story highlights for public profile Stories tabs.
--
-- Safe deployment notes:
-- - No foreign keys are used so this migration imports safely on shared hosts.
-- - Story/profile ownership is enforced in application code.
-- - Highlighted story media is allowed publicly only while the highlight is active.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS microgifter_story_highlights (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  profile_user_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  story_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(120) NULL,
  display_order INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('active','deleted','blocked') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_microgifter_story_highlights_public_id (public_id),
  UNIQUE KEY uq_microgifter_story_highlights_story (profile_user_id,story_id),
  KEY idx_microgifter_story_highlights_profile (profile_user_id,status,display_order,created_at),
  KEY idx_microgifter_story_highlights_owner (owner_user_id,status,created_at),
  KEY idx_microgifter_story_highlights_story_lookup (story_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
