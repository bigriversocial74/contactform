-- Microgifter Personal Gifting Workflows Phase 3.
-- Import after database/20260714_personal_gifting_agent_phase2.sql.
-- Safe to rerun after a partial or successful import.
-- Cross-table context rules are enforced by the application service layer.
-- CHECK constraints are intentionally omitted for MySQL/MariaDB compatibility
-- when the same columns participate in cascading or SET NULL foreign keys.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS user_gifting_schedules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  scheduled_for DATETIME NOT NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
  status ENUM('draft','approved','paused','prepared','completed','cancelled') NOT NULL DEFAULT 'draft',
  execution_mode ENUM('prepare_only') NOT NULL DEFAULT 'prepare_only',
  approval_required TINYINT(1) NOT NULL DEFAULT 1,
  prepared_at DATETIME NULL,
  completed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_gifting_schedules_public_id (public_id),
  KEY idx_user_gifting_schedules_owner_due (owner_user_id,status,scheduled_for),
  KEY idx_user_gifting_schedules_plan (plan_id,status,scheduled_for),
  CONSTRAINT fk_user_gifting_schedules_owner
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_gifting_schedules_plan
    FOREIGN KEY (plan_id) REFERENCES user_gifting_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_recurring_gift_programs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  list_id BIGINT UNSIGNED NULL,
  user_contact_id BIGINT UNSIGNED NULL,
  contact_user_id BIGINT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  occasion_type VARCHAR(64) NOT NULL DEFAULT 'general',
  occasion_label VARCHAR(160) NULL,
  cadence ENUM('weekly','monthly','quarterly','yearly','custom') NOT NULL DEFAULT 'yearly',
  interval_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  next_run_at DATETIME NOT NULL,
  end_at DATETIME NULL,
  budget_min_cents BIGINT UNSIGNED NULL,
  budget_max_cents BIGINT UNSIGNED NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  status ENUM('draft','active','paused','completed','cancelled') NOT NULL DEFAULT 'draft',
  generation_mode ENUM('draft_plan_only') NOT NULL DEFAULT 'draft_plan_only',
  run_sequence INT UNSIGNED NOT NULL DEFAULT 0,
  last_generated_at DATETIME NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_recurring_gift_programs_public_id (public_id),
  KEY idx_user_recurring_gift_programs_owner_due (owner_user_id,status,next_run_at),
  KEY idx_user_recurring_gift_programs_list (list_id,status,next_run_at),
  KEY idx_user_recurring_gift_programs_contact (user_contact_id,status,next_run_at),
  KEY idx_user_recurring_gift_programs_linked (contact_user_id,status,next_run_at),
  CONSTRAINT fk_user_recurring_programs_owner
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_recurring_programs_list
    FOREIGN KEY (list_id) REFERENCES user_contact_lists(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_recurring_programs_contact
    FOREIGN KEY (user_contact_id) REFERENCES user_contacts(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_recurring_programs_linked
    FOREIGN KEY (contact_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_recurring_gift_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  program_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  run_sequence INT UNSIGNED NOT NULL,
  scheduled_for DATETIME NOT NULL,
  plan_id BIGINT UNSIGNED NULL,
  status ENUM('due','draft_created','skipped','cancelled') NOT NULL DEFAULT 'due',
  idempotency_key CHAR(64) NOT NULL,
  generated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_recurring_gift_runs_public_id (public_id),
  UNIQUE KEY uq_user_recurring_gift_runs_sequence (program_id,run_sequence),
  UNIQUE KEY uq_user_recurring_gift_runs_idempotency (idempotency_key),
  KEY idx_user_recurring_gift_runs_owner (owner_user_id,status,scheduled_for),
  CONSTRAINT fk_user_recurring_runs_program
    FOREIGN KEY (program_id) REFERENCES user_recurring_gift_programs(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_recurring_runs_owner
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_recurring_runs_plan
    FOREIGN KEY (plan_id) REFERENCES user_gifting_plans(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_group_gifts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  organizer_user_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NULL,
  list_id BIGINT UNSIGNED NULL,
  recipient_user_contact_id BIGINT UNSIGNED NULL,
  recipient_user_id BIGINT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  goal_cents BIGINT UNSIGNED NOT NULL,
  min_contribution_cents BIGINT UNSIGNED NULL,
  max_contribution_cents BIGINT UNSIGNED NULL,
  pledged_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  deadline_at DATETIME NOT NULL,
  status ENUM('draft','open','locked','fulfilled','closed','cancelled') NOT NULL DEFAULT 'draft',
  contribution_mode ENUM('pledge_only') NOT NULL DEFAULT 'pledge_only',
  contributor_names_visible TINYINT(1) NOT NULL DEFAULT 1,
  anonymous_allowed TINYINT(1) NOT NULL DEFAULT 1,
  opened_at DATETIME NULL,
  closed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_group_gifts_public_id (public_id),
  KEY idx_user_group_gifts_organizer (organizer_user_id,status,deadline_at),
  KEY idx_user_group_gifts_list (list_id,status,deadline_at),
  CONSTRAINT fk_user_group_gifts_organizer
    FOREIGN KEY (organizer_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_group_gifts_plan
    FOREIGN KEY (plan_id) REFERENCES user_gifting_plans(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_group_gifts_list
    FOREIGN KEY (list_id) REFERENCES user_contact_lists(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_group_gifts_private_recipient
    FOREIGN KEY (recipient_user_contact_id) REFERENCES user_contacts(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_group_gifts_linked_recipient
    FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_group_gift_participants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  group_gift_id BIGINT UNSIGNED NOT NULL,
  organizer_user_id BIGINT UNSIGNED NOT NULL,
  invited_user_id BIGINT UNSIGNED NULL,
  private_contact_id BIGINT UNSIGNED NULL,
  role_key ENUM('contributor','recipient') NOT NULL DEFAULT 'contributor',
  display_name_snapshot VARCHAR(190) NOT NULL,
  status ENUM('draft','invited','joined','declined','removed') NOT NULL DEFAULT 'draft',
  pledge_cents BIGINT UNSIGNED NULL,
  is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
  invite_message VARCHAR(1000) NULL,
  invited_at DATETIME NULL,
  responded_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_group_gift_participants_public_id (public_id),
  UNIQUE KEY uq_user_group_gift_participant_user (group_gift_id,invited_user_id),
  UNIQUE KEY uq_user_group_gift_participant_private (group_gift_id,private_contact_id),
  KEY idx_user_group_gift_participants_invited (invited_user_id,status,updated_at),
  KEY idx_user_group_gift_participants_group (group_gift_id,status,updated_at),
  CONSTRAINT fk_user_group_participants_group
    FOREIGN KEY (group_gift_id) REFERENCES user_group_gifts(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_group_participants_organizer
    FOREIGN KEY (organizer_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_group_participants_user
    FOREIGN KEY (invited_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_group_participants_private
    FOREIGN KEY (private_contact_id) REFERENCES user_contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_recipient_data_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  requester_user_id BIGINT UNSIGNED NOT NULL,
  subject_user_id BIGINT UNSIGNED NOT NULL,
  imported_contact_id BIGINT UNSIGNED NULL,
  request_type ENUM('preferences','address','mixed') NOT NULL DEFAULT 'preferences',
  requested_scopes_json JSON NOT NULL,
  approved_scopes_json JSON NULL,
  message VARCHAR(1000) NULL,
  response_note VARCHAR(1000) NULL,
  status ENUM('pending','approved','partially_approved','declined','cancelled','expired','revoked') NOT NULL DEFAULT 'pending',
  expires_at DATETIME NULL,
  responded_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_recipient_data_requests_public_id (public_id),
  KEY idx_user_recipient_requests_requester (requester_user_id,status,created_at),
  KEY idx_user_recipient_requests_subject (subject_user_id,status,created_at),
  CONSTRAINT fk_user_recipient_requests_requester
    FOREIGN KEY (requester_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_recipient_requests_subject
    FOREIGN KEY (subject_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_recipient_requests_contact
    FOREIGN KEY (imported_contact_id) REFERENCES user_contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_contact_profile_import_fields (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  import_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  subject_user_id BIGINT UNSIGNED NOT NULL,
  permission_scope VARCHAR(100) NOT NULL,
  field_name VARCHAR(100) NOT NULL,
  value_hash CHAR(64) NOT NULL,
  status ENUM('active','stale','revoked') NOT NULL DEFAULT 'active',
  imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_contact_import_field (import_id,field_name),
  KEY idx_user_contact_import_fields_owner (owner_user_id,status,permission_scope),
  KEY idx_user_contact_import_fields_subject (subject_user_id,status,permission_scope),
  CONSTRAINT fk_user_contact_import_fields_import
    FOREIGN KEY (import_id) REFERENCES user_contact_profile_imports(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_contact_import_fields_owner
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_contact_import_fields_subject
    FOREIGN KEY (subject_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_gift_bundles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NULL,
  list_id BIGINT UNSIGNED NULL,
  user_contact_id BIGINT UNSIGNED NULL,
  contact_user_id BIGINT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  target_budget_cents BIGINT UNSIGNED NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  status ENUM('draft','ready','archived') NOT NULL DEFAULT 'draft',
  approval_required TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_gift_bundles_public_id (public_id),
  KEY idx_user_gift_bundles_owner (owner_user_id,status,updated_at),
  CONSTRAINT fk_user_gift_bundles_owner
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_gift_bundles_plan
    FOREIGN KEY (plan_id) REFERENCES user_gifting_plans(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_gift_bundles_list
    FOREIGN KEY (list_id) REFERENCES user_contact_lists(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_gift_bundles_contact
    FOREIGN KEY (user_contact_id) REFERENCES user_contacts(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_gift_bundles_linked
    FOREIGN KEY (contact_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_gift_bundle_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  bundle_id BIGINT UNSIGNED NOT NULL,
  catalog_product_id BIGINT UNSIGNED NULL,
  item_type ENUM('catalog_product','custom') NOT NULL DEFAULT 'custom',
  custom_label VARCHAR(190) NULL,
  quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  unit_value_cents BIGINT UNSIGNED NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  notes VARCHAR(1000) NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  status ENUM('suggested','selected','removed') NOT NULL DEFAULT 'selected',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_gift_bundle_items_public_id (public_id),
  KEY idx_user_gift_bundle_items_bundle (bundle_id,status,sort_order),
  KEY idx_user_gift_bundle_items_product (catalog_product_id,status),
  CONSTRAINT fk_user_gift_bundle_items_bundle
    FOREIGN KEY (bundle_id) REFERENCES user_gift_bundles(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_gift_bundle_items_product
    FOREIGN KEY (catalog_product_id) REFERENCES catalog_products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_gift_lifecycle_reminders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  gift_id BIGINT UNSIGNED NOT NULL,
  target_user_id BIGINT UNSIGNED NOT NULL,
  notification_id BIGINT UNSIGNED NULL,
  reminder_kind ENUM('claim','redemption','expiry') NOT NULL DEFAULT 'claim',
  remind_at DATETIME NOT NULL,
  status ENUM('draft','scheduled','sent','dismissed','cancelled') NOT NULL DEFAULT 'draft',
  delivery_channel ENUM('in_app') NOT NULL DEFAULT 'in_app',
  message VARCHAR(500) NULL,
  sent_at DATETIME NULL,
  dismissed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_gift_lifecycle_reminders_public_id (public_id),
  UNIQUE KEY uq_user_gift_lifecycle_reminder_slot (owner_user_id,gift_id,reminder_kind,remind_at),
  KEY idx_user_gift_lifecycle_reminders_owner (owner_user_id,status,remind_at),
  KEY idx_user_gift_lifecycle_reminders_target (target_user_id,status,remind_at),
  CONSTRAINT fk_user_gift_lifecycle_reminders_owner
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_gift_lifecycle_reminders_gift
    FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_gift_lifecycle_reminders_target
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_gift_lifecycle_reminders_notification
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('agent.personal.workflows.manage','Manage personal gifting workflows','Create approval-first schedules, recurring draft programs, group pledges, bundles, data requests, and lifecycle reminders.',NOW()),
('agent.personal.requests.respond','Respond to personal gifting data requests','Approve, partially approve, decline, or revoke recipient preference and address-sharing requests.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('agent.personal.workflows.manage','agent.personal.requests.respond')
WHERE r.slug IN ('customer','member','merchant','creator','admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  '20260714_personal_gifting_workflows_phase3',
  'Approval-first schedules, recurring draft programs, list snapshots, pledge-only group gifts, recipient consent requests, draft bundles, and lifecycle reminders.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
