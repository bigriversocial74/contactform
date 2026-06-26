-- Stage 18Z Admin Incident Analytics
-- Adds incident analytics notification types and view permission.

ALTER TABLE admin_queue_notifications
  MODIFY notification_type ENUM('assigned','overdue','due_soon','escalated','reopened','review_flag','digest','auto_routed','sla_breach','auto_escalated','workload_balance','playbook_applied','template_used','checklist_completed','case_comment','case_comment_pinned','timeline_viewed','automation_summary','automation_failed','quality_review','incident_declared','incident_updated','incident_resolved','incident_review_required','incident_review_completed','incident_review_followup_due','repeat_incident_detected','prevention_task_overdue','incident_trend_worsening') NOT NULL;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.operations_analytics.view','View admin incident analytics','View incident analytics, repeat issue detection, prevention score, and trend intelligence.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.operations_analytics.view')
WHERE r.slug IN ('admin','super_admin');
