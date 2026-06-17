CREATE TABLE IF NOT EXISTS operational_incidents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  incident_key VARCHAR(120) NOT NULL,
  title VARCHAR(240) NOT NULL,
  severity ENUM('sev1','sev2','sev3','sev4') NOT NULL,
  status ENUM('open','investigating','mitigated','resolved','closed') NOT NULL DEFAULT 'open',
  service_key VARCHAR(120) NOT NULL DEFAULT 'microgifter',
  summary TEXT NOT NULL,
  impact_summary TEXT NULL,
  mitigation_summary TEXT NULL,
  root_cause_summary TEXT NULL,
  opened_by_user_id BIGINT UNSIGNED NOT NULL,
  commander_user_id BIGINT UNSIGNED NULL,
  opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  mitigated_at DATETIME NULL,
  resolved_at DATETIME NULL,
  closed_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_operational_incidents_public_id (public_id),
  UNIQUE KEY uq_operational_incidents_key (incident_key),
  KEY idx_operational_incidents_status (status,severity,opened_at),
  CONSTRAINT fk_operational_incidents_opener FOREIGN KEY (opened_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_operational_incidents_commander FOREIGN KEY (commander_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operational_incident_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  incident_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(120) NOT NULL,
  from_status VARCHAR(40) NULL,
  to_status VARCHAR(40) NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  note TEXT NULL,
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_operational_incident_events_public_id (public_id),
  KEY idx_operational_incident_events_incident (incident_id,created_at,id),
  CONSTRAINT fk_operational_incident_events_incident FOREIGN KEY (incident_id) REFERENCES operational_incidents(id) ON DELETE CASCADE,
  CONSTRAINT fk_operational_incident_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deployment_releases (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  release_version VARCHAR(100) NOT NULL,
  git_commit_sha CHAR(40) NOT NULL,
  environment ENUM('staging','production') NOT NULL,
  status ENUM('planned','validating','approved','deploying','deployed','failed','rolled_back','canceled') NOT NULL DEFAULT 'planned',
  validation_summary_json JSON NULL,
  artifact_manifest_json JSON NULL,
  rollback_plan_json JSON NOT NULL,
  approved_by_user_id BIGINT UNSIGNED NULL,
  deployed_by_user_id BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  deployment_started_at DATETIME NULL,
  deployed_at DATETIME NULL,
  rolled_back_at DATETIME NULL,
  failure_message VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_deployment_releases_public_id (public_id),
  UNIQUE KEY uq_deployment_releases_version_environment (release_version,environment),
  KEY idx_deployment_releases_status (environment,status,created_at),
  CONSTRAINT fk_deployment_releases_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_deployment_releases_deployer FOREIGN KEY (deployed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS release_gate_results (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  deployment_release_id BIGINT UNSIGNED NOT NULL,
  gate_key VARCHAR(120) NOT NULL,
  status ENUM('pending','passed','failed','waived') NOT NULL DEFAULT 'pending',
  required_flag TINYINT(1) NOT NULL DEFAULT 1,
  evidence_json JSON NULL,
  failure_message VARCHAR(1000) NULL,
  evaluated_at DATETIME NULL,
  waived_by_user_id BIGINT UNSIGNED NULL,
  waived_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_release_gate_results_public_id (public_id),
  UNIQUE KEY uq_release_gate_results_gate (deployment_release_id,gate_key),
  KEY idx_release_gate_results_status (deployment_release_id,status,required_flag),
  CONSTRAINT fk_release_gate_results_release FOREIGN KEY (deployment_release_id) REFERENCES deployment_releases(id) ON DELETE CASCADE,
  CONSTRAINT fk_release_gate_results_waiver FOREIGN KEY (waived_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS retention_policies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  policy_key VARCHAR(120) NOT NULL,
  table_name VARCHAR(190) NOT NULL,
  timestamp_column VARCHAR(190) NOT NULL,
  retention_days INT UNSIGNED NOT NULL,
  batch_size INT UNSIGNED NOT NULL DEFAULT 1000,
  action_type ENUM('delete','anonymize','archive') NOT NULL DEFAULT 'delete',
  status ENUM('active','paused','retired') NOT NULL DEFAULT 'active',
  policy_json JSON NULL,
  last_executed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_retention_policies_key (policy_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS retention_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  policy_id BIGINT UNSIGNED NOT NULL,
  status ENUM('running','completed','failed','canceled') NOT NULL DEFAULT 'running',
  cutoff_at DATETIME NOT NULL,
  scanned_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  affected_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  failure_message VARCHAR(1000) NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_retention_runs_public_id (public_id),
  KEY idx_retention_runs_policy (policy_id,status,started_at),
  CONSTRAINT fk_retention_runs_policy FOREIGN KEY (policy_id) REFERENCES retention_policies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operational_check_results (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  check_key VARCHAR(120) NOT NULL,
  check_scope VARCHAR(120) NOT NULL DEFAULT 'platform',
  status ENUM('pass','warn','fail') NOT NULL,
  summary VARCHAR(500) NOT NULL,
  details_json JSON NULL,
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_operational_check_results_public_id (public_id),
  KEY idx_operational_check_results_latest (check_key,checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('operations.incidents.manage','Manage operational incidents','Create and manage incident response records.',NOW()),
('operations.releases.manage','Manage deployment releases','Create, validate, approve, deploy, and roll back releases.',NOW()),
('operations.retention.manage','Manage retention policies','Execute approved retention and archival policies.',NOW()),
('operations.readiness.view','View readiness status','View deep readiness, release gates, and operational checks.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN ('operations.incidents.manage','operations.releases.manage','operations.retention.manage','operations.readiness.view')
WHERE r.slug IN ('admin','super_admin');

INSERT IGNORE INTO retention_policies (policy_key,table_name,timestamp_column,retention_days,batch_size,action_type,status,policy_json) VALUES
('security_logs_365d','security_logs','created_at',365,1000,'delete','active',JSON_OBJECT('preserve_severity',JSON_ARRAY('critical'))),
('delivery_events_180d','delivery_events','created_at',180,1000,'delete','active',JSON_OBJECT()),
('payment_webhook_events_730d','payment_webhook_events','received_at',730,1000,'delete','active',JSON_OBJECT()),
('agent_execution_events_365d','agent_execution_events','created_at',365,1000,'delete','active',JSON_OBJECT()),
('agent_swarm_events_365d','agent_swarm_events','created_at',365,1000,'delete','active',JSON_OBJECT());

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_18_production_hardening_launch_readiness','Operational incidents, release gates, retention policies, readiness checks, deployment records, and launch governance.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
