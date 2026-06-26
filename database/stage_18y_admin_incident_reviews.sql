-- Stage 18Y Admin Incident Reviews

CREATE TABLE IF NOT EXISTS admin_ops_incident_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  incident_id BIGINT UNSIGNED NOT NULL,
  cause_summary TEXT NOT NULL,
  customer_impact TEXT NOT NULL,
  merchant_impact TEXT NOT NULL,
  effective_steps TEXT NOT NULL,
  improvement_areas TEXT NOT NULL,
  prevention_steps TEXT NOT NULL,
  action_items TEXT NOT NULL,
  followup_owner_user_id BIGINT UNSIGNED NULL,
  followup_due_at DATETIME NULL,
  status ENUM('draft','completed','followup_open','followup_complete') NOT NULL DEFAULT 'draft',
  completed_by_user_id BIGINT UNSIGNED NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_ops_incident_reviews_public_id (public_id),
  UNIQUE KEY uq_admin_ops_incident_reviews_incident (incident_id),
  KEY idx_admin_ops_incident_reviews_status_due (status,followup_due_at),
  KEY idx_admin_ops_incident_reviews_owner_due (followup_owner_user_id,followup_due_at),
  CONSTRAINT fk_admin_ops_incident_reviews_incident FOREIGN KEY (incident_id) REFERENCES admin_ops_incidents(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_ops_incident_reviews_owner FOREIGN KEY (followup_owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_ops_incident_reviews_completed_by FOREIGN KEY (completed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE admin_queue_notifications
  MODIFY notification_type ENUM('assigned','overdue','due_soon','escalated','reopened','review_flag','digest','auto_routed','sla_breach','auto_escalated','workload_balance','playbook_applied','template_used','checklist_completed','case_comment','case_comment_pinned','timeline_viewed','automation_summary','automation_failed','quality_review','incident_declared','incident_updated','incident_resolved','incident_review_required','incident_review_completed','incident_review_followup_due') NOT NULL;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.operations_reviews.view','View admin incident reviews','View incident timelines, structured reviews, action items, and follow-up status.',NOW()),
('admin.operations_reviews.manage','Manage admin incident reviews','Create and complete incident reviews and follow-up action items.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.operations_reviews.view','admin.operations_reviews.manage')
WHERE r.slug IN ('admin','super_admin');
