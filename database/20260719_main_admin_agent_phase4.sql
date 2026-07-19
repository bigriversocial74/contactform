-- Main Admin Agent Phase 4
-- Reliability governance, maintenance windows, deployment change risk,
-- historical reliability scorecards, capacity forecasts, incident learning,
-- and review-gated prevention follow-ups.

CREATE TABLE IF NOT EXISTS admin_agent_maintenance_windows (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  window_key CHAR(64) NOT NULL,
  service_id BIGINT UNSIGNED NULL,
  environment_key VARCHAR(40) NOT NULL DEFAULT 'production',
  title VARCHAR(240) NOT NULL,
  reason VARCHAR(2000) NOT NULL,
  status ENUM('scheduled','active','completed','canceled') NOT NULL DEFAULT 'scheduled',
  suppression_mode ENUM('observe_only','suppress_expected') NOT NULL DEFAULT 'observe_only',
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  canceled_by_user_id BIGINT UNSIGNED NULL,
  canceled_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_maintenance_windows_public (public_id),
  UNIQUE KEY uq_admin_agent_maintenance_windows_key (window_key),
  KEY idx_admin_agent_maintenance_windows_active (status,starts_at,ends_at),
  KEY idx_admin_agent_maintenance_windows_service (service_id,status,starts_at),
  CONSTRAINT fk_admin_agent_maintenance_windows_service FOREIGN KEY (service_id) REFERENCES admin_agent_services(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_maintenance_windows_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_maintenance_windows_canceler FOREIGN KEY (canceled_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_change_risk_assessments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  assessment_key CHAR(64) NOT NULL,
  deployment_id BIGINT UNSIGNED NULL,
  environment_key VARCHAR(40) NOT NULL DEFAULT 'production',
  risk_level ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
  risk_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  impacted_services_json JSON NOT NULL,
  factors_json JSON NOT NULL,
  recommendations_json JSON NULL,
  maintenance_window_id BIGINT UNSIGNED NULL,
  evaluated_by_user_id BIGINT UNSIGNED NULL,
  evaluated_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_change_risk_public (public_id),
  UNIQUE KEY uq_admin_agent_change_risk_key (assessment_key),
  KEY idx_admin_agent_change_risk_environment (environment_key,evaluated_at),
  KEY idx_admin_agent_change_risk_level (risk_level,evaluated_at),
  CONSTRAINT fk_admin_agent_change_risk_deployment FOREIGN KEY (deployment_id) REFERENCES admin_agent_deployments(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_change_risk_window FOREIGN KEY (maintenance_window_id) REFERENCES admin_agent_maintenance_windows(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_change_risk_actor FOREIGN KEY (evaluated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_reliability_scorecards (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  scorecard_key CHAR(64) NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  period_days SMALLINT UNSIGNED NOT NULL,
  period_start DATETIME NOT NULL,
  period_end DATETIME NOT NULL,
  objective_percent DECIMAL(7,4) NOT NULL,
  availability_percent DECIMAL(9,5) NOT NULL,
  error_budget_remaining_percent DECIMAL(9,5) NOT NULL,
  warning_snapshot_total INT UNSIGNED NOT NULL DEFAULT 0,
  critical_snapshot_total INT UNSIGNED NOT NULL DEFAULT 0,
  incident_total INT UNSIGNED NOT NULL DEFAULT 0,
  reliability_score TINYINT UNSIGNED NOT NULL DEFAULT 100,
  status ENUM('healthy','watch','attention','critical') NOT NULL DEFAULT 'healthy',
  evidence_json JSON NULL,
  generated_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_reliability_public (public_id),
  UNIQUE KEY uq_admin_agent_reliability_key (scorecard_key),
  KEY idx_admin_agent_reliability_service_period (service_id,period_days,period_end),
  KEY idx_admin_agent_reliability_status (status,generated_at),
  CONSTRAINT fk_admin_agent_reliability_service FOREIGN KEY (service_id) REFERENCES admin_agent_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_capacity_forecasts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  forecast_key CHAR(64) NOT NULL,
  domain VARCHAR(80) NOT NULL,
  metric_key VARCHAR(140) NOT NULL,
  current_value DECIMAL(30,8) NOT NULL DEFAULT 0,
  trend_per_day DECIMAL(30,8) NOT NULL DEFAULT 0,
  predicted_7d DECIMAL(30,8) NOT NULL DEFAULT 0,
  predicted_30d DECIMAL(30,8) NOT NULL DEFAULT 0,
  capacity_limit DECIMAL(30,8) NULL,
  utilization_percent DECIMAL(12,5) NULL,
  risk_level ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
  evidence_json JSON NULL,
  generated_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_capacity_forecasts_public (public_id),
  UNIQUE KEY uq_admin_agent_capacity_forecasts_key (forecast_key),
  KEY idx_admin_agent_capacity_forecasts_domain (domain,risk_level,generated_at),
  KEY idx_admin_agent_capacity_forecasts_metric (metric_key,generated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_incident_learning (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  learning_key CHAR(64) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  ops_incident_id BIGINT UNSIGNED NULL,
  review_id BIGINT UNSIGNED NULL,
  status ENUM('draft','review_ready','completed','dismissed') NOT NULL DEFAULT 'draft',
  summary_text TEXT NOT NULL,
  impact_text TEXT NOT NULL,
  root_cause_hypothesis TEXT NOT NULL,
  contributing_factors_json JSON NULL,
  prevention_actions_json JSON NULL,
  evidence_json JSON NULL,
  completed_by_user_id BIGINT UNSIGNED NULL,
  completed_at DATETIME NULL,
  generated_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_incident_learning_public (public_id),
  UNIQUE KEY uq_admin_agent_incident_learning_key (learning_key),
  KEY idx_admin_agent_incident_learning_status (status,generated_at),
  KEY idx_admin_agent_incident_learning_workspace (workspace_id,status),
  CONSTRAINT fk_admin_agent_incident_learning_workspace FOREIGN KEY (workspace_id) REFERENCES admin_agent_incident_workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_agent_incident_learning_ops FOREIGN KEY (ops_incident_id) REFERENCES admin_ops_incidents(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_incident_learning_review FOREIGN KEY (review_id) REFERENCES admin_ops_incident_reviews(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_incident_learning_actor FOREIGN KEY (completed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_prevention_followups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  followup_key CHAR(64) NOT NULL,
  learning_id BIGINT UNSIGNED NOT NULL,
  review_id BIGINT UNSIGNED NULL,
  title VARCHAR(240) NOT NULL,
  description VARCHAR(2000) NOT NULL,
  priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  status ENUM('proposed','approved','in_progress','completed','dismissed') NOT NULL DEFAULT 'proposed',
  owner_user_id BIGINT UNSIGNED NULL,
  due_at DATETIME NULL,
  approved_by_user_id BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  completed_at DATETIME NULL,
  evidence_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_prevention_followups_public (public_id),
  UNIQUE KEY uq_admin_agent_prevention_followups_key (followup_key),
  KEY idx_admin_agent_prevention_followups_queue (status,priority,due_at),
  KEY idx_admin_agent_prevention_followups_owner (owner_user_id,status,due_at),
  CONSTRAINT fk_admin_agent_prevention_followups_learning FOREIGN KEY (learning_id) REFERENCES admin_agent_incident_learning(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_agent_prevention_followups_review FOREIGN KEY (review_id) REFERENCES admin_ops_incident_reviews(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_prevention_followups_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_prevention_followups_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permission_catalog (public_id,permission_key,category,label,description,is_sensitive,created_at,updated_at)
SELECT UUID(),'admin.admin_agent.maintenance','admin','Admin Agent maintenance windows','Create and manage planned maintenance windows.',1,NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM permission_catalog WHERE permission_key='admin.admin_agent.maintenance');

INSERT INTO permission_catalog (public_id,permission_key,category,label,description,is_sensitive,created_at,updated_at)
SELECT UUID(),'admin.admin_agent.reliability','admin','Admin Agent reliability governance','View and generate reliability scorecards and capacity forecasts.',0,NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM permission_catalog WHERE permission_key='admin.admin_agent.reliability');

INSERT INTO permission_catalog (public_id,permission_key,category,label,description,is_sensitive,created_at,updated_at)
SELECT UUID(),'admin.admin_agent.learning','admin','Admin Agent incident learning','Review incident learning drafts and prevention follow-ups.',1,NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM permission_catalog WHERE permission_key='admin.admin_agent.learning');

INSERT INTO permission_catalog (public_id,permission_key,category,label,description,is_sensitive,created_at,updated_at)
SELECT UUID(),'admin.admin_agent.forecasts','admin','Admin Agent capacity forecasts','View deterministic capacity and operating-risk forecasts.',0,NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM permission_catalog WHERE permission_key='admin.admin_agent.forecasts');

INSERT INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permission_catalog p ON p.permission_key IN (
  'admin.admin_agent.maintenance','admin.admin_agent.reliability','admin.admin_agent.learning','admin.admin_agent.forecasts'
) WHERE r.slug='super_admin'
ON DUPLICATE KEY UPDATE role_id=VALUES(role_id);

INSERT INTO admin_agent_remediation_adapters (
  public_id,adapter_key,label,domain,description,risk_level,execution_mode,enabled,requires_confirmation,configuration_json,created_at,updated_at
)
VALUES (
  UUID(),'create_prevention_followup','Create prevention follow-up','operations',
  'Creates a deterministic incident-review draft and linked prevention follow-up after explicit approval.',
  'medium','in_process',1,1,JSON_OBJECT('phase',4,'review_required',true),NOW(),NOW()
)
ON DUPLICATE KEY UPDATE
  label=VALUES(label),description=VALUES(description),risk_level='medium',execution_mode='in_process',enabled=1,
  requires_confirmation=1,configuration_json=VALUES(configuration_json),updated_at=NOW();
