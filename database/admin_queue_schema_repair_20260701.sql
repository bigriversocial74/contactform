-- Admin Queue Schema Repair - 2026-07-01
-- Purpose: repair partially imported Stage 18O/18R/18U/18V admin queue schemas.
-- Run this only if the admin dashboard, queue reporting, or queue automation show missing-column/schema-required warnings.

ALTER TABLE admin_user_notes
  ADD COLUMN IF NOT EXISTS assigned_admin_user_id BIGINT UNSIGNED NULL AFTER admin_user_id,
  ADD COLUMN IF NOT EXISTS due_at DATETIME NULL AFTER flag_state,
  ADD COLUMN IF NOT EXISTS closed_at DATETIME NULL AFTER resolved_at,
  ADD COLUMN IF NOT EXISTS routed_lane ENUM('support','risk','billing','merchant_onboarding','product_catalog','crm_campaigns','general') NOT NULL DEFAULT 'general' AFTER flag_state,
  ADD COLUMN IF NOT EXISTS sla_due_at DATETIME NULL AFTER due_at,
  ADD COLUMN IF NOT EXISTS sla_status ENUM('compliant','at_risk','breached','paused','resolved') NOT NULL DEFAULT 'compliant' AFTER sla_due_at,
  ADD COLUMN IF NOT EXISTS auto_escalated_at DATETIME NULL AFTER closed_at,
  ADD COLUMN IF NOT EXISTS last_routed_at DATETIME NULL AFTER auto_escalated_at,
  ADD COLUMN IF NOT EXISTS sla_policy_json JSON NULL AFTER last_routed_at,
  ADD COLUMN IF NOT EXISTS resolution_outcome ENUM('resolved_successfully','escalated_externally','merchant_action_required','customer_action_required','billing_adjustment','risk_restriction','catalog_correction','no_action_needed') NULL AFTER resolution_template_slug,
  ADD COLUMN IF NOT EXISTS resolution_confidence ENUM('high','medium','low','unknown') NOT NULL DEFAULT 'unknown' AFTER resolution_outcome,
  ADD COLUMN IF NOT EXISTS followup_required TINYINT(1) NOT NULL DEFAULT 0 AFTER resolution_confidence,
  ADD COLUMN IF NOT EXISTS reopened_after_resolution TINYINT(1) NOT NULL DEFAULT 0 AFTER followup_required,
  ADD COLUMN IF NOT EXISTS notes_incomplete TINYINT(1) NOT NULL DEFAULT 0 AFTER reopened_after_resolution,
  ADD COLUMN IF NOT EXISTS resolution_reviewed_at DATETIME NULL AFTER notes_incomplete;

ALTER TABLE admin_queue_notifications
  ADD COLUMN IF NOT EXISTS target_user_id BIGINT UNSIGNED NULL AFTER note_id,
  ADD COLUMN IF NOT EXISTS assigned_admin_user_id BIGINT UNSIGNED NULL AFTER target_user_id,
  ADD COLUMN IF NOT EXISTS actor_user_id BIGINT UNSIGNED NULL AFTER assigned_admin_user_id,
  ADD COLUMN IF NOT EXISTS metadata_json JSON NULL AFTER message,
  ADD COLUMN IF NOT EXISTS read_at DATETIME NULL AFTER metadata_json;

ALTER TABLE admin_queue_notifications
  MODIFY notification_type ENUM(
    'assigned','overdue','due_soon','escalated','reopened','review_flag','digest',
    'auto_routed','sla_breach','auto_escalated','workload_balance','playbook_applied',
    'template_used','checklist_completed','case_comment','case_comment_pinned','timeline_viewed',
    'automation_summary','automation_failed','quality_review'
  ) NOT NULL;

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
