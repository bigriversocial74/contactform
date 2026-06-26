-- Stage 18V Admin Queue Automation
-- Adds durable automation run logs and notification support for scheduled queue maintenance.

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
  KEY idx_admin_queue_automation_runs_actor_started (actor_user_id,started_at),
  CONSTRAINT fk_admin_queue_automation_runs_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE admin_queue_notifications
  MODIFY notification_type ENUM('assigned','overdue','due_soon','escalated','reopened','review_flag','digest','auto_routed','sla_breach','auto_escalated','workload_balance','playbook_applied','template_used','checklist_completed','case_comment','case_comment_pinned','timeline_viewed','automation_summary','automation_failed','quality_review') NOT NULL;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.queue_automation.view','View admin queue automation','View scheduled queue automation run logs, summaries, and automation health.',NOW()),
('admin.queue_automation.run','Run admin queue automation','Run queue automation jobs for alerts, SLA, routing, reporting, and quality flags.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.queue_automation.view','admin.queue_automation.run')
WHERE r.slug IN ('admin','super_admin');
