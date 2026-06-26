-- Stage 18U Admin Resolution Reporting
-- Adds structured support resolution outcomes and quality review fields for queue reporting.

ALTER TABLE admin_user_notes
  ADD COLUMN resolution_outcome ENUM('resolved_successfully','escalated_externally','merchant_action_required','customer_action_required','billing_adjustment','risk_restriction','catalog_correction','no_action_needed') NULL AFTER resolution_template_slug,
  ADD COLUMN resolution_confidence ENUM('high','medium','low','unknown') NOT NULL DEFAULT 'unknown' AFTER resolution_outcome,
  ADD COLUMN followup_required TINYINT(1) NOT NULL DEFAULT 0 AFTER resolution_confidence,
  ADD COLUMN reopened_after_resolution TINYINT(1) NOT NULL DEFAULT 0 AFTER followup_required,
  ADD COLUMN notes_incomplete TINYINT(1) NOT NULL DEFAULT 0 AFTER reopened_after_resolution,
  ADD COLUMN resolution_reviewed_at DATETIME NULL AFTER notes_incomplete,
  ADD KEY idx_admin_user_notes_resolution_outcome (resolution_outcome,status,updated_at),
  ADD KEY idx_admin_user_notes_resolution_quality (resolution_confidence,followup_required,notes_incomplete,status);

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.queue_reporting.view','View admin queue reporting','View support resolution, SLA, playbook, outcome, and aging reports.',NOW()),
('admin.queue_reporting.manage','Manage admin queue reporting','Set resolution outcome and quality review fields on queue notes.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.queue_reporting.view','admin.queue_reporting.manage')
WHERE r.slug IN ('admin','super_admin');
