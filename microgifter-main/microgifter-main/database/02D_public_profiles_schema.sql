-- 02D Microgifter public profile schema
-- Purpose: public identity/profile layer for Stage 2.
-- Safe to rerun after successful import.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS public_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(120) NOT NULL,
  display_name VARCHAR(160) NOT NULL,
  headline VARCHAR(180) NULL,
  bio TEXT NULL,
  avatar_url VARCHAR(500) NULL,
  cover_url VARCHAR(500) NULL,
  location_label VARCHAR(160) NULL,
  website_url VARCHAR(500) NULL,
  profile_type VARCHAR(64) NOT NULL DEFAULT 'customer',
  visibility ENUM('public','private','unlisted') NOT NULL DEFAULT 'public',
  status ENUM('draft','active','hidden','suspended') NOT NULL DEFAULT 'draft',
  completion_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  metadata_json JSON NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_public_profiles_public_id (public_id),
  UNIQUE KEY uq_public_profiles_user (user_id),
  UNIQUE KEY uq_public_profiles_slug (slug),
  KEY idx_public_profiles_status_visibility (status, visibility),
  KEY idx_public_profiles_profile_type (profile_type),
  CONSTRAINT fk_public_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS public_profile_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  profile_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(120) NOT NULL,
  url VARCHAR(600) NOT NULL,
  link_type VARCHAR(60) NOT NULL DEFAULT 'custom',
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  is_active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_public_profile_links_public_id (public_id),
  KEY idx_public_profile_links_profile (profile_id, is_active, sort_order),
  CONSTRAINT fk_public_profile_links_profile FOREIGN KEY (profile_id) REFERENCES public_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS public_profile_sections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  profile_id BIGINT UNSIGNED NOT NULL,
  section_type VARCHAR(80) NOT NULL,
  title VARCHAR(160) NULL,
  body TEXT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  is_active TINYINT NOT NULL DEFAULT 1,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_public_profile_sections_public_id (public_id),
  KEY idx_public_profile_sections_profile (profile_id, is_active, sort_order),
  CONSTRAINT fk_public_profile_sections_profile FOREIGN KEY (profile_id) REFERENCES public_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO public_profiles (public_id, user_id, slug, display_name, profile_type, visibility, status, completion_score, created_at, updated_at)
SELECT CONCAT('pp_', u.id),
       u.id,
       CONCAT('user-', u.id),
       COALESCE(NULLIF(u.display_name, ''), NULLIF(u.full_name, ''), u.email),
       'customer',
       'public',
       'draft',
       20,
       NOW(),
       NOW()
FROM users u
WHERE NOT EXISTS (SELECT 1 FROM public_profiles pp WHERE pp.user_id = u.id);

COMMIT;
