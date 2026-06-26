-- Stage 18Y Admin Incident Reviews

CREATE TABLE IF NOT EXISTS admin_ops_incident_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  incident_id BIGINT UNSIGNED NOT NULL,
  review_summary TEXT NOT NULL,
  customer_impact TEXT NOT NULL,
  merchant_impact TEXT NOT NULL,
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
  CONSTRAINT fk_admin_ops_incident_reviews_incident FOREIGN KEY (incident_id) REFERENCES admin_ops_incidents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
