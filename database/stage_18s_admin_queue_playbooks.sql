-- Stage 18S Admin Queue Playbooks
-- Adds repeatable support playbooks, resolution templates, and checklist state to admin follow-up notes.

ALTER TABLE admin_user_notes
  ADD COLUMN playbook_slug VARCHAR(80) NULL AFTER sla_policy_json,
  ADD COLUMN resolution_template_slug VARCHAR(80) NULL AFTER playbook_slug,
  ADD COLUMN playbook_checklist_json JSON NULL AFTER resolution_template_slug,
  ADD COLUMN playbook_applied_at DATETIME NULL AFTER playbook_checklist_json,
  ADD KEY idx_admin_user_notes_playbook (playbook_slug,status,updated_at),
  ADD KEY idx_admin_user_notes_template (resolution_template_slug,status,updated_at);

CREATE TABLE IF NOT EXISTS admin_queue_playbook_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  note_id BIGINT UNSIGNED NOT NULL,
  target_user_id BIGINT UNSIGNED NOT NULL,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  playbook_slug VARCHAR(80) NULL,
  template_slug VARCHAR(80) NULL,
  event_type ENUM('playbook_applied','template_used','checklist_updated','checklist_completed') NOT NULL,
  checklist_json JSON NULL,
  reason VARCHAR(240) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_queue_playbook_events_public_id (public_id),
  KEY idx_admin_queue_playbook_events_note (note_id,created_at),
  KEY idx_admin_queue_playbook_events_playbook (playbook_slug,event_type,created_at),
  CONSTRAINT fk_admin_queue_playbook_events_note FOREIGN KEY (note_id) REFERENCES admin_user_notes(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_queue_playbook_events_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_queue_playbook_events_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE admin_queue_notifications
  MODIFY notification_type ENUM('assigned','overdue','due_soon','escalated','reopened','review_flag','digest','auto_routed','sla_breach','auto_escalated','workload_balance','playbook_applied','template_used','checklist_completed') NOT NULL;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.queue_playbooks.view','View admin queue playbooks','View support playbooks, resolution templates, and queue checklist guidance.',NOW()),
('admin.queue_playbooks.manage','Manage admin queue playbooks','Apply playbooks, resolution templates, and checklist updates to admin follow-up notes.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.queue_playbooks.view','admin.queue_playbooks.manage')
WHERE r.slug IN ('admin','super_admin');
