-- Microgifter user lists, private contacts, and profile-import permissions.
-- Import after database/stage_14_posts_feed_social.sql.
-- Safe to rerun after a successful import.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS user_contact_preferences (
  user_id BIGINT UNSIGNED NOT NULL,
  allow_list_membership TINYINT(1) NOT NULL DEFAULT 1,
  allow_profile_import_requests TINYINT(1) NOT NULL DEFAULT 1,
  allow_birthday_reminders TINYINT(1) NOT NULL DEFAULT 0,
  allow_agent_suggestions TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_user_contact_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_contact_lists (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  description VARCHAR(1000) NULL,
  list_type VARCHAR(64) NOT NULL DEFAULT 'custom',
  icon_key VARCHAR(64) NOT NULL DEFAULT 'people',
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_contact_lists_public_id (public_id),
  UNIQUE KEY uq_user_contact_lists_owner_slug (owner_user_id, slug),
  KEY idx_user_contact_lists_owner_state (owner_user_id, is_archived, sort_order, updated_at),
  CONSTRAINT fk_user_contact_lists_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_contacts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  linked_user_id BIGINT UNSIGNED NULL,
  first_name VARCHAR(120) NULL,
  middle_name VARCHAR(120) NULL,
  last_name VARCHAR(120) NULL,
  display_name VARCHAR(180) NOT NULL,
  nickname VARCHAR(120) NULL,
  email VARCHAR(190) NULL,
  phone_ciphertext TEXT NULL,
  phone_last4 CHAR(4) NULL,
  phone_hash CHAR(64) NULL,
  birthdate DATE NULL,
  birth_year_visible TINYINT(1) NOT NULL DEFAULT 0,
  relationship_type VARCHAR(64) NULL,
  relationship_label VARCHAR(120) NULL,
  company VARCHAR(180) NULL,
  job_title VARCHAR(180) NULL,
  address_line_1 VARCHAR(190) NULL,
  address_line_2 VARCHAR(190) NULL,
  city VARCHAR(120) NULL,
  state_region VARCHAR(120) NULL,
  postal_code VARCHAR(40) NULL,
  country_code CHAR(2) NULL,
  notes TEXT NULL,
  gift_preferences TEXT NULL,
  interests TEXT NULL,
  allergies_or_restrictions TEXT NULL,
  preferred_merchants TEXT NULL,
  preferred_categories TEXT NULL,
  budget_min DECIMAL(12,2) NULL,
  budget_max DECIMAL(12,2) NULL,
  source ENUM('manual','profile_import','contact_sync') NOT NULL DEFAULT 'manual',
  source_user_id BIGINT UNSIGNED NULL,
  profile_imported_at DATETIME NULL,
  profile_data_version VARCHAR(64) NULL,
  archived_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_contacts_public_id (public_id),
  UNIQUE KEY uq_user_contacts_owner_linked (owner_user_id, linked_user_id),
  KEY idx_user_contacts_owner_name (owner_user_id, archived_at, display_name),
  KEY idx_user_contacts_owner_birthdate (owner_user_id, archived_at, birthdate),
  KEY idx_user_contacts_phone_hash (owner_user_id, phone_hash),
  CONSTRAINT fk_user_contacts_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_contacts_linked FOREIGN KEY (linked_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_contacts_source_user FOREIGN KEY (source_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_contact_list_members (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  list_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  contact_user_id BIGINT UNSIGNED NULL,
  user_contact_id BIGINT UNSIGNED NULL,
  relationship_type VARCHAR(64) NULL,
  relationship_label VARCHAR(120) NULL,
  private_notes VARCHAR(2000) NULL,
  added_by BIGINT UNSIGNED NOT NULL,
  added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_contact_list_members_public_id (public_id),
  UNIQUE KEY uq_user_contact_list_member_linked (list_id, contact_user_id),
  UNIQUE KEY uq_user_contact_list_member_private (list_id, user_contact_id),
  KEY idx_user_contact_list_members_owner (owner_user_id, list_id, added_at),
  KEY idx_user_contact_list_members_contact_user (contact_user_id, owner_user_id),
  KEY idx_user_contact_list_members_private_contact (user_contact_id, owner_user_id),
  CONSTRAINT fk_user_contact_list_members_list FOREIGN KEY (list_id) REFERENCES user_contact_lists(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_contact_list_members_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_contact_list_members_contact_user FOREIGN KEY (contact_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_contact_list_members_private_contact FOREIGN KEY (user_contact_id) REFERENCES user_contacts(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_contact_list_members_added_by FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT chk_user_contact_list_member_target CHECK ((contact_user_id IS NOT NULL AND user_contact_id IS NULL) OR (contact_user_id IS NULL AND user_contact_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_contact_dates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  user_contact_id BIGINT UNSIGNED NOT NULL,
  date_type VARCHAR(64) NOT NULL DEFAULT 'important_date',
  label VARCHAR(160) NOT NULL,
  event_date DATE NOT NULL,
  repeats_annually TINYINT(1) NOT NULL DEFAULT 1,
  reminder_days_before SMALLINT UNSIGNED NOT NULL DEFAULT 14,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_contact_dates_public_id (public_id),
  KEY idx_user_contact_dates_upcoming (owner_user_id, event_date, repeats_annually),
  KEY idx_user_contact_dates_contact (user_contact_id, event_date),
  CONSTRAINT fk_user_contact_dates_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_contact_dates_contact FOREIGN KEY (user_contact_id) REFERENCES user_contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_contact_profile_permissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  subject_user_id BIGINT UNSIGNED NOT NULL,
  grantee_user_id BIGINT UNSIGNED NOT NULL,
  permission_scope VARCHAR(100) NOT NULL,
  status ENUM('granted','denied','revoked') NOT NULL DEFAULT 'denied',
  granted_at DATETIME NULL,
  revoked_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_contact_profile_permissions_public_id (public_id),
  UNIQUE KEY uq_user_contact_profile_permission_scope (subject_user_id, grantee_user_id, permission_scope),
  KEY idx_user_contact_profile_permissions_grantee (grantee_user_id, status, permission_scope),
  CONSTRAINT fk_user_contact_profile_permissions_subject FOREIGN KEY (subject_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_contact_profile_permissions_grantee FOREIGN KEY (grantee_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT chk_user_contact_profile_permission_distinct CHECK (subject_user_id <> grantee_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_contact_profile_imports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  subject_user_id BIGINT UNSIGNED NOT NULL,
  user_contact_id BIGINT UNSIGNED NOT NULL,
  imported_scopes_json JSON NOT NULL,
  profile_data_version VARCHAR(64) NULL,
  status ENUM('active','stale','revoked') NOT NULL DEFAULT 'active',
  imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_contact_profile_imports_public_id (public_id),
  KEY idx_user_contact_profile_import_owner (owner_user_id, status, imported_at),
  KEY idx_user_contact_profile_import_subject (subject_user_id, status, imported_at),
  CONSTRAINT fk_user_contact_profile_import_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_contact_profile_import_subject FOREIGN KEY (subject_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_contact_profile_import_contact FOREIGN KEY (user_contact_id) REFERENCES user_contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO user_contact_preferences (user_id)
SELECT id FROM users;

INSERT IGNORE INTO permissions (slug, name, description, created_at) VALUES
('contacts.lists.manage', 'Manage personal contact lists', 'Create and manage private contacts, lists, dates, and permission-safe profile imports.', NOW());

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r
JOIN permissions p ON p.slug='contacts.lists.manage'
WHERE r.slug IN ('customer','member','merchant','creator','admin','super_admin');

INSERT INTO schema_migrations (migration_key, description, checksum, applied_at)
VALUES ('20260714_user_contact_lists_phase1', 'Personal contact lists, private contacts, mutual-follow membership, important dates, and profile import permissions.', NULL, NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
