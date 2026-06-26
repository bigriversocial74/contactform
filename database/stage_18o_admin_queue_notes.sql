-- Stage 18O Admin Queue Notes
-- Adds cross-user queue metadata for admin user notes.

ALTER TABLE admin_user_notes
  ADD COLUMN assigned_admin_user_id BIGINT UNSIGNED NULL AFTER admin_user_id,
  ADD COLUMN due_at DATETIME NULL AFTER flag_state,
  ADD COLUMN closed_at DATETIME NULL AFTER resolved_at,
  ADD KEY idx_admin_user_notes_assigned_status (assigned_admin_user_id,status,due_at),
  ADD KEY idx_admin_user_notes_due_status (due_at,status),
  ADD CONSTRAINT fk_admin_user_notes_assigned_admin FOREIGN KEY (assigned_admin_user_id) REFERENCES users(id) ON DELETE SET NULL;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.support_queue.view','View admin support queue','View internal user-note queue across support, billing, onboarding, catalog, CRM, and review work.',NOW()),
('admin.support_queue.manage','Manage admin support queue','Update internal note lifecycle, assignment, due date, and review state from the admin queue.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.support_queue.view','admin.support_queue.manage')
WHERE r.slug IN ('admin','super_admin');
