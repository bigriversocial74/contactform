-- Admin Queue Schema Repair - 2026-07-01
-- Purpose: repair partially imported Stage 18O/18R/18U/18V admin queue schemas.
-- Compatibility: avoids ALTER TABLE ... ADD COLUMN IF NOT EXISTS for older MySQL/MariaDB hosts.
-- Safe to re-run. Existing columns are skipped.

CREATE TABLE IF NOT EXISTS admin_queue_notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  note_id BIGINT UNSIGNED NULL,
  target_user_id BIGINT UNSIGNED NULL,
  assigned_admin_user_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  notification_type ENUM(
    'assigned','overdue','due_soon','escalated','reopened','review_flag','digest',
    'auto_routed','sla_breach','auto_escalated','workload_balance','playbook_applied',
    'template_used','checklist_completed','case_comment','case_comment_pinned','timeline_viewed',
    'automation_summary','automation_failed','quality_review'
  ) NOT NULL DEFAULT 'digest',
  severity ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
  title VARCHAR(160) NOT NULL,
  message VARCHAR(500) NOT NULL,
  metadata_json JSON NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_queue_notifications_public_id (public_id),
  KEY idx_admin_queue_notifications_note_type (note_id,notification_type,created_at),
  KEY idx_admin_queue_notifications_assigned_read (assigned_admin_user_id,read_at,created_at),
  KEY idx_admin_queue_notifications_type_created (notification_type,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND COLUMN_NAME = 'assigned_admin_user_id') = 0,
  'ALTER TABLE admin_user_notes ADD COLUMN assigned_admin_user_id BIGINT UNSIGNED NULL',
  'SELECT "admin_user_notes.assigned_admin_user_id exists or table missing" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND COLUMN_NAME = 'due_at') = 0,
  'ALTER TABLE admin_user_notes ADD COLUMN due_at DATETIME NULL',
  'SELECT "admin_user_notes.due_at exists or table missing" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND COLUMN_NAME = 'closed_at') = 0,
  'ALTER TABLE admin_user_notes ADD COLUMN closed_at DATETIME NULL',
  'SELECT "admin_user_notes.closed_at exists or table missing" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND COLUMN_NAME = 'routed_lane') = 0,
  'ALTER TABLE admin_user_notes ADD COLUMN routed_lane ENUM(''support'',''risk'',''billing'',''merchant_onboarding'',''product_catalog'',''crm_campaigns'',''general'') NOT NULL DEFAULT ''general''',
  'SELECT "admin_user_notes.routed_lane exists or table missing" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND COLUMN_NAME = 'sla_due_at') = 0,
  'ALTER TABLE admin_user_notes ADD COLUMN sla_due_at DATETIME NULL',
  'SELECT "admin_user_notes.sla_due_at exists or table missing" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND COLUMN_NAME = 'sla_status') = 0,
  'ALTER TABLE admin_user_notes ADD COLUMN sla_status ENUM(''compliant'',''at_risk'',''breached'',''paused'',''resolved'') NOT NULL DEFAULT ''compliant''',
  'SELECT "admin_user_notes.sla_status exists or table missing" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND COLUMN_NAME = 'auto_escalated_at') = 0,
  'ALTER TABLE admin_user_notes ADD COLUMN auto_escalated_at DATETIME NULL',
  'SELECT "admin_user_notes.auto_escalated_at exists or table missing" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND COLUMN_NAME = 'last_routed_at') = 0,
  'ALTER TABLE admin_user_notes ADD COLUMN last_routed_at DATETIME NULL',
  'SELECT "admin_user_notes.last_routed_at exists or table missing" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND COLUMN_NAME = 'sla_policy_json') = 0,
  'ALTER TABLE admin_user_notes ADD COLUMN sla_policy_json JSON NULL',
  'SELECT "admin_user_notes.sla_policy_json exists or table missing" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND COLUMN_NAME = 'resolution_outcome') = 0,
  'ALTER TABLE admin_user_notes ADD COLUMN resolution_outcome ENUM(''resolved_successfully'',''escalated_externally'',''merchant_action_required'',''customer_action_required'',''billing_adjustment'',''risk_restriction'',''catalog_correction'',''no_action_needed'') NULL',
  'SELECT "admin_user_notes.resolution_outcome exists or table missing" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND COLUMN_NAME = 'resolution_confidence') = 0,
  'ALTER TABLE admin_user_notes ADD COLUMN resolution_confidence ENUM(''high'',''medium'',''low'',''unknown'') NOT NULL DEFAULT ''unknown''',
  'SELECT "admin_user_notes.resolution_confidence exists or table missing" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND COLUMN_NAME = 'followup_required') = 0,
  'ALTER TABLE admin_user_notes ADD COLUMN followup_required TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT "admin_user_notes.followup_required exists or table missing" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND COLUMN_NAME = 'reopened_after_resolution') = 0,
  'ALTER TABLE admin_user_notes ADD COLUMN reopened_after_resolution TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT "admin_user_notes.reopened_after_resolution exists or table missing" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND COLUMN_NAME = 'notes_incomplete') = 0,
  'ALTER TABLE admin_user_notes ADD COLUMN notes_incomplete TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT "admin_user_notes.notes_incomplete exists or table missing" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_user_notes' AND COLUMN_NAME = 'resolution_reviewed_at') = 0,
  'ALTER TABLE admin_user_notes ADD COLUMN resolution_reviewed_at DATETIME NULL',
  'SELECT "admin_user_notes.resolution_reviewed_at exists or table missing" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_queue_notifications' AND COLUMN_NAME = 'target_user_id') = 0,
  'ALTER TABLE admin_queue_notifications ADD COLUMN target_user_id BIGINT UNSIGNED NULL',
  'SELECT "admin_queue_notifications.target_user_id exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_queue_notifications' AND COLUMN_NAME = 'assigned_admin_user_id') = 0,
  'ALTER TABLE admin_queue_notifications ADD COLUMN assigned_admin_user_id BIGINT UNSIGNED NULL',
  'SELECT "admin_queue_notifications.assigned_admin_user_id exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_queue_notifications' AND COLUMN_NAME = 'actor_user_id') = 0,
  'ALTER TABLE admin_queue_notifications ADD COLUMN actor_user_id BIGINT UNSIGNED NULL',
  'SELECT "admin_queue_notifications.actor_user_id exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_queue_notifications' AND COLUMN_NAME = 'metadata_json') = 0,
  'ALTER TABLE admin_queue_notifications ADD COLUMN metadata_json JSON NULL',
  'SELECT "admin_queue_notifications.metadata_json exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_queue_notifications' AND COLUMN_NAME = 'read_at') = 0,
  'ALTER TABLE admin_queue_notifications ADD COLUMN read_at DATETIME NULL',
  'SELECT "admin_queue_notifications.read_at exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE admin_queue_notifications
  MODIFY notification_type ENUM(
    'assigned','overdue','due_soon','escalated','reopened','review_flag','digest',
    'auto_routed','sla_breach','auto_escalated','workload_balance','playbook_applied',
    'template_used','checklist_completed','case_comment','case_comment_pinned','timeline_viewed',
    'automation_summary','automation_failed','quality_review'
  ) NOT NULL DEFAULT 'digest';

CREATE TABLE IF NOT EXISTS admin_queue_automation_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  run_mode ENUM('manual','scheduled','system') NOT NULL DEFAULT 'manual',
  status ENUM('started','completed','failed') NOT NULL DEFAULT 'started',
  processed_count INT UNSIGNED NOT NULL DEFAULT 0,
  alerts_created_count INT UNSIGNED NOT NULL DEFAULT 0,
  sla_updated_count INT UNSIGNED NOT NULL DEFAULT 0,
  auto_routed_count INT UNSIGNED NOT NULL DEFAULT 0,
  auto_escalated_count INT UNSIGNED NOT NULL DEFAULT 0,
  quality_flags_count INT UNSIGNED NOT NULL DEFAULT 0,
  unresolved_aging_count INT UNSIGNED NOT NULL DEFAULT 0,
  summary_json JSON NULL,
  error_message VARCHAR(500) NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_queue_automation_runs_public_id (public_id),
  KEY idx_admin_queue_automation_runs_status_started (status,started_at),
  KEY idx_admin_queue_automation_runs_actor_started (actor_user_id,started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.support_queue.view','View admin support queue','View internal user-note queue across support, billing, onboarding, catalog, CRM, and review work.',NOW()),
('admin.support_queue.manage','Manage admin support queue','Update internal note lifecycle, assignment, due date, and review state from the admin queue.',NOW()),
('admin.queue_reporting.view','View admin queue reporting','View support resolution, SLA, playbook, outcome, and aging reports.',NOW()),
('admin.queue_reporting.manage','Manage admin queue reporting','Set resolution outcome and quality review fields on queue notes.',NOW()),
('admin.queue_sla.view','View admin queue SLA','View admin follow-up queue SLA, routing, workload, and breach metrics.',NOW()),
('admin.queue_sla.manage','Manage admin queue SLA','Run SLA routing, auto-escalation, and workload routing actions.',NOW()),
('admin.queue_automation.view','View admin queue automation','View scheduled queue automation run logs, summaries, and automation health.',NOW()),
('admin.queue_automation.run','Run admin queue automation','Run queue automation jobs for alerts, SLA, routing, reporting, and quality flags.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN (
  'admin.support_queue.view','admin.support_queue.manage',
  'admin.queue_reporting.view','admin.queue_reporting.manage',
  'admin.queue_sla.view','admin.queue_sla.manage',
  'admin.queue_automation.view','admin.queue_automation.run'
)
WHERE r.slug IN ('admin','super_admin');
