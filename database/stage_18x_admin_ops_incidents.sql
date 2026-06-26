-- Stage 18X Admin Ops Incidents
-- Adds incident mode, incident updates, runbook state, and command center incident notifications.

CREATE TABLE IF NOT EXISTS admin_ops_incidents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  mode_slug ENUM('payment_outage','fulfillment_backlog','claim_redemption_issue','notification_delivery_issue','fraud_risk_spike','merchant_onboarding_backlog','catalog_publishing_issue') NOT NULL,
  title VARCHAR(180) NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  status ENUM('declared','investigating','mitigating','monitoring','resolved') NOT NULL DEFAULT 'declared',
  owner_user_id BIGINT UNSIGNED NULL,
  declared_by_user_id BIGINT UNSIGNED NOT NULL,
  impact_summary VARCHAR(600) NOT NULL,
  runbook_checklist_json JSON NULL,
  declared_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_ops_incidents_public_id (public_id),
  KEY idx_admin_ops_incidents_status_severity (status,severity,updated_at),
  KEY idx_admin_ops_incidents_mode_status (mode_slug,status,declared_at),
  KEY idx_admin_ops_incidents_owner (owner_user_id,status),
  CONSTRAINT fk_admin_ops_incidents_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_ops_incidents_declared_by FOREIGN KEY (declared_by_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_ops_incident_updates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  incident_id BIGINT UNSIGNED NOT NULL,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  update_type ENUM('declared','status_update','severity_changed','owner_assigned','runbook_updated','resolved') NOT NULL,
  message TEXT NOT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_ops_incident_updates_public_id (public_id),
  KEY idx_admin_ops_incident_updates_incident (incident_id,created_at),
  CONSTRAINT fk_admin_ops_incident_updates_incident FOREIGN KEY (incident_id) REFERENCES admin_ops_incidents(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_ops_incident_updates_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE admin_queue_notifications
  MODIFY notification_type ENUM('assigned','overdue','due_soon','escalated','reopened','review_flag','digest','auto_routed','sla_breach','auto_escalated','workload_balance','playbook_applied','template_used','checklist_completed','case_comment','case_comment_pinned','timeline_viewed','automation_summary','automation_failed','quality_review','incident_declared','incident_updated','incident_resolved') NOT NULL;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.operations_incidents.view','View admin operations incidents','View active operations incidents, runbooks, incident updates, and command center incident state.',NOW()),
('admin.operations_incidents.manage','Manage admin operations incidents','Declare, update, assign, runbook-check, and resolve operations incidents.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.operations_incidents.view','admin.operations_incidents.manage')
WHERE r.slug IN ('admin','super_admin');
