-- Stage 18L: Admin Commerce Operations Center
START TRANSACTION;

CREATE TABLE IF NOT EXISTS commerce_operation_cases (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  subject_type ENUM('order','refund','dispute','subscription','tip','microgift') NOT NULL,
  subject_reference VARCHAR(190) NOT NULL,
  status ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  summary VARCHAR(240) NOT NULL,
  latest_note VARCHAR(1000) NULL,
  opened_by_user_id BIGINT UNSIGNED NOT NULL,
  assigned_user_id BIGINT UNSIGNED NULL,
  resolved_by_user_id BIGINT UNSIGNED NULL,
  resolution_code VARCHAR(80) NULL,
  opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_commerce_operation_cases_public_id (public_id),
  KEY idx_commerce_operation_cases_queue (status,priority,updated_at,id),
  KEY idx_commerce_operation_cases_subject (subject_type,subject_reference,status,updated_at),
  KEY idx_commerce_operation_cases_assignee (assigned_user_id,status,updated_at),
  CONSTRAINT fk_commerce_operation_cases_opened_by FOREIGN KEY (opened_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_commerce_operation_cases_assigned FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_commerce_operation_cases_resolved_by FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commerce_operation_case_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  case_id BIGINT UNSIGNED NOT NULL,
  action_type VARCHAR(80) NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status VARCHAR(30) NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  note VARCHAR(1000) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_commerce_operation_case_events_public_id (public_id),
  KEY idx_commerce_operation_case_events_case (case_id,created_at,id),
  CONSTRAINT fk_commerce_operation_case_events_case FOREIGN KEY (case_id) REFERENCES commerce_operation_cases(id) ON DELETE CASCADE,
  CONSTRAINT fk_commerce_operation_case_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name) VALUES
  ('admin.commerce.view','View commerce operations'),
  ('admin.commerce.manage','Manage commerce operations');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.slug IN ('admin.commerce.view','admin.commerce.manage')
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_18l_admin_commerce_operations','Protected cross-domain commerce operations center, review cases, timelines, and canonical tip reversal controls.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
