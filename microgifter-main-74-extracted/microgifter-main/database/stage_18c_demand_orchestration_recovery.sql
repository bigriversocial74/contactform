-- Stage 18C: safe retry attempts and incident linkage for demand orchestration.

CREATE TABLE IF NOT EXISTS demand_signal_orchestration_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  orchestration_id BIGINT UNSIGNED NOT NULL,
  attempt_no SMALLINT UNSIGNED NOT NULL,
  strategy_id BIGINT UNSIGNED NULL,
  strategy_version INT UNSIGNED NULL,
  team_id BIGINT UNSIGNED NULL,
  orchestration_type ENUM('workflow','swarm','alert_only') NOT NULL,
  workflow_run_id BIGINT UNSIGNED NULL,
  swarm_run_id BIGINT UNSIGNED NULL,
  status ENUM('claimed','awaiting_approval','running','completed','failed','review_required') NOT NULL,
  dispatch_key CHAR(64) NOT NULL,
  input_fingerprint CHAR(64) NOT NULL,
  request_idempotency_key VARCHAR(190) NULL,
  requested_by_user_id BIGINT UNSIGNED NULL,
  requested_reason VARCHAR(1000) NULL,
  last_error VARCHAR(1000) NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_demand_orchestration_attempts_public_id (public_id),
  UNIQUE KEY uq_demand_orchestration_attempts_number (orchestration_id,attempt_no),
  UNIQUE KEY uq_demand_orchestration_attempts_dispatch (dispatch_key),
  UNIQUE KEY uq_demand_orchestration_attempts_request (orchestration_id,request_idempotency_key),
  KEY idx_demand_orchestration_attempts_status (status,updated_at,id),
  CONSTRAINT fk_demand_orchestration_attempts_root FOREIGN KEY (orchestration_id) REFERENCES demand_signal_orchestrations(id) ON DELETE CASCADE,
  CONSTRAINT fk_demand_orchestration_attempts_strategy FOREIGN KEY (strategy_id) REFERENCES agent_strategies(id) ON DELETE SET NULL,
  CONSTRAINT fk_demand_orchestration_attempts_team FOREIGN KEY (team_id) REFERENCES agent_teams(id) ON DELETE SET NULL,
  CONSTRAINT fk_demand_orchestration_attempts_workflow FOREIGN KEY (workflow_run_id) REFERENCES agent_workflow_runs(id) ON DELETE SET NULL,
  CONSTRAINT fk_demand_orchestration_attempts_swarm FOREIGN KEY (swarm_run_id) REFERENCES agent_swarm_runs(id) ON DELETE SET NULL,
  CONSTRAINT fk_demand_orchestration_attempts_requester FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demand_signal_orchestration_incidents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  orchestration_id BIGINT UNSIGNED NOT NULL,
  incident_id BIGINT UNSIGNED NOT NULL,
  escalation_status ENUM('active','resolved') NOT NULL DEFAULT 'active',
  last_orchestration_status VARCHAR(40) NOT NULL,
  escalated_at DATETIME NOT NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_demand_orchestration_incidents_public_id (public_id),
  UNIQUE KEY uq_demand_orchestration_incidents_root (orchestration_id),
  UNIQUE KEY uq_demand_orchestration_incidents_incident (incident_id),
  KEY idx_demand_orchestration_incidents_status (escalation_status,updated_at,id),
  CONSTRAINT fk_demand_orchestration_incidents_root FOREIGN KEY (orchestration_id) REFERENCES demand_signal_orchestrations(id) ON DELETE CASCADE,
  CONSTRAINT fk_demand_orchestration_incidents_incident FOREIGN KEY (incident_id) REFERENCES operational_incidents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('operations.orchestrations.retry','Retry demand orchestrations','Retry failed or review-required demand orchestrations through canonical agent authorities.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='operations.orchestrations.retry'
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_18c_demand_orchestration_recovery','Safe demand orchestration retry attempts, incident escalation, and completed-only event retention.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
