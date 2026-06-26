-- Stage 18R Admin Queue SLA Routing
-- Adds SLA, routing, and auto-escalation metadata to admin follow-up notes.

ALTER TABLE admin_user_notes
  ADD COLUMN routed_lane ENUM('support','risk','billing','merchant_onboarding','product_catalog','crm_campaigns','general') NOT NULL DEFAULT 'general' AFTER flag_state,
  ADD COLUMN sla_due_at DATETIME NULL AFTER due_at,
  ADD COLUMN sla_status ENUM('compliant','at_risk','breached','paused','resolved') NOT NULL DEFAULT 'compliant' AFTER sla_due_at,
  ADD COLUMN auto_escalated_at DATETIME NULL AFTER closed_at,
  ADD COLUMN last_routed_at DATETIME NULL AFTER auto_escalated_at,
  ADD COLUMN sla_policy_json JSON NULL AFTER last_routed_at,
  ADD KEY idx_admin_user_notes_sla_status_due (sla_status,sla_due_at,status),
  ADD KEY idx_admin_user_notes_lane_status (routed_lane,status,priority),
  ADD KEY idx_admin_user_notes_auto_escalated (auto_escalated_at,status);

ALTER TABLE admin_queue_notifications
  MODIFY notification_type ENUM('assigned','overdue','due_soon','escalated','reopened','review_flag','digest','auto_routed','sla_breach','auto_escalated','workload_balance') NOT NULL;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.queue_sla.view','View admin queue SLA','View admin follow-up queue SLA, routing, workload, and breach metrics.',NOW()),
('admin.queue_sla.manage','Manage admin queue SLA','Run SLA routing, auto-escalation, and workload routing actions.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.queue_sla.view','admin.queue_sla.manage')
WHERE r.slug IN ('admin','super_admin');
