-- Main Admin Agent Phase 2
-- Historical metrics, anomaly baselines, cross-system correlations, deployment
-- awareness, escalation delivery, executive summaries, runbooks, and controlled
-- remediation execution adapters.

CREATE TABLE IF NOT EXISTS admin_agent_metric_samples (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  scan_id BIGINT UNSIGNED NULL,
  monitor_key VARCHAR(100) NOT NULL,
  domain VARCHAR(80) NOT NULL,
  metric_key VARCHAR(140) NOT NULL,
  dimension_key CHAR(64) NOT NULL,
  dimensions_json JSON NULL,
  metric_value DECIMAL(30,8) NOT NULL,
  occurred_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_metric_samples_public (public_id),
  KEY idx_admin_agent_metric_samples_series (monitor_key,metric_key,dimension_key,occurred_at),
  KEY idx_admin_agent_metric_samples_domain (domain,occurred_at),
  CONSTRAINT fk_admin_agent_metric_samples_scan FOREIGN KEY (scan_id) REFERENCES admin_agent_scans(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_metric_baselines (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  monitor_key VARCHAR(100) NOT NULL,
  domain VARCHAR(80) NOT NULL,
  metric_key VARCHAR(140) NOT NULL,
  dimension_key CHAR(64) NOT NULL,
  dimensions_json JSON NULL,
  sample_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  mean_value DECIMAL(30,8) NOT NULL DEFAULT 0,
  m2_value DECIMAL(38,12) NOT NULL DEFAULT 0,
  variance_value DECIMAL(30,8) NOT NULL DEFAULT 0,
  stddev_value DECIMAL(30,8) NOT NULL DEFAULT 0,
  min_value DECIMAL(30,8) NULL,
  max_value DECIMAL(30,8) NULL,
  latest_value DECIMAL(30,8) NULL,
  anomaly_threshold DECIMAL(8,3) NOT NULL DEFAULT 3.000,
  minimum_samples INT UNSIGNED NOT NULL DEFAULT 8,
  baseline_window_hours INT UNSIGNED NOT NULL DEFAULT 168,
  last_sample_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_metric_baselines_public (public_id),
  UNIQUE KEY uq_admin_agent_metric_baselines_series (monitor_key,metric_key,dimension_key),
  KEY idx_admin_agent_metric_baselines_domain (domain,updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_anomalies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  anomaly_key CHAR(64) NOT NULL,
  baseline_id BIGINT UNSIGNED NULL,
  monitor_key VARCHAR(100) NOT NULL,
  domain VARCHAR(80) NOT NULL,
  metric_key VARCHAR(140) NOT NULL,
  dimension_key CHAR(64) NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  status ENUM('open','acknowledged','under_review','resolved','dismissed') NOT NULL DEFAULT 'open',
  observed_value DECIMAL(30,8) NOT NULL,
  baseline_mean DECIMAL(30,8) NOT NULL,
  baseline_stddev DECIMAL(30,8) NOT NULL,
  z_score DECIMAL(18,6) NULL,
  deviation_ratio DECIMAL(18,6) NULL,
  threshold_value DECIMAL(8,3) NOT NULL,
  evidence_json JSON NULL,
  first_detected_at DATETIME NOT NULL,
  last_detected_at DATETIME NOT NULL,
  occurrence_count INT UNSIGNED NOT NULL DEFAULT 1,
  recurrence_count INT UNSIGNED NOT NULL DEFAULT 0,
  acknowledged_by_user_id BIGINT UNSIGNED NULL,
  acknowledged_at DATETIME NULL,
  resolved_by_user_id BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  resolution_note VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_anomalies_public (public_id),
  UNIQUE KEY uq_admin_agent_anomalies_key (anomaly_key),
  KEY idx_admin_agent_anomalies_queue (status,severity,last_detected_at),
  KEY idx_admin_agent_anomalies_domain (domain,status,last_detected_at),
  CONSTRAINT fk_admin_agent_anomalies_baseline FOREIGN KEY (baseline_id) REFERENCES admin_agent_metric_baselines(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_anomalies_ack FOREIGN KEY (acknowledged_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_anomalies_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_deployments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  deployment_key CHAR(64) NOT NULL,
  environment_key VARCHAR(40) NOT NULL DEFAULT 'production',
  branch_name VARCHAR(190) NOT NULL,
  commit_sha VARCHAR(64) NOT NULL,
  source_type ENUM('manual','cli','environment','github','release') NOT NULL DEFAULT 'manual',
  release_label VARCHAR(240) NULL,
  metadata_json JSON NULL,
  recorded_by_user_id BIGINT UNSIGNED NULL,
  deployed_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_deployments_public (public_id),
  UNIQUE KEY uq_admin_agent_deployments_key (deployment_key),
  KEY idx_admin_agent_deployments_environment (environment_key,deployed_at),
  KEY idx_admin_agent_deployments_commit (commit_sha),
  CONSTRAINT fk_admin_agent_deployments_actor FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_runbooks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  runbook_key VARCHAR(120) NOT NULL,
  domain VARCHAR(80) NOT NULL,
  title VARCHAR(240) NOT NULL,
  summary VARCHAR(1000) NOT NULL,
  steps_json JSON NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_runbooks_public (public_id),
  UNIQUE KEY uq_admin_agent_runbooks_key (runbook_key),
  KEY idx_admin_agent_runbooks_domain (domain,enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_correlations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  correlation_key CHAR(64) NOT NULL,
  correlation_type VARCHAR(120) NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'high',
  status ENUM('open','acknowledged','under_review','resolved','dismissed') NOT NULL DEFAULT 'open',
  title VARCHAR(240) NOT NULL,
  summary VARCHAR(2000) NOT NULL,
  domains_json JSON NOT NULL,
  finding_ids_json JSON NULL,
  anomaly_ids_json JSON NULL,
  deployment_id BIGINT UNSIGNED NULL,
  evidence_json JSON NULL,
  runbook_key VARCHAR(120) NULL,
  recommended_action_key VARCHAR(120) NULL,
  first_detected_at DATETIME NOT NULL,
  last_detected_at DATETIME NOT NULL,
  occurrence_count INT UNSIGNED NOT NULL DEFAULT 1,
  recurrence_count INT UNSIGNED NOT NULL DEFAULT 0,
  assigned_admin_user_id BIGINT UNSIGNED NULL,
  acknowledged_by_user_id BIGINT UNSIGNED NULL,
  acknowledged_at DATETIME NULL,
  resolved_by_user_id BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  resolution_note VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_correlations_public (public_id),
  UNIQUE KEY uq_admin_agent_correlations_key (correlation_key),
  KEY idx_admin_agent_correlations_queue (status,severity,last_detected_at),
  KEY idx_admin_agent_correlations_type (correlation_type,status,last_detected_at),
  CONSTRAINT fk_admin_agent_correlations_deploy FOREIGN KEY (deployment_id) REFERENCES admin_agent_deployments(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_correlations_assignee FOREIGN KEY (assigned_admin_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_correlations_ack FOREIGN KEY (acknowledged_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_correlations_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_escalation_policies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  policy_key VARCHAR(120) NOT NULL,
  source_type ENUM('finding','anomaly','correlation') NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL,
  initial_delay_minutes INT UNSIGNED NOT NULL DEFAULT 15,
  repeat_interval_minutes INT UNSIGNED NOT NULL DEFAULT 180,
  maximum_level TINYINT UNSIGNED NOT NULL DEFAULT 2,
  notify_admin_center TINYINT(1) NOT NULL DEFAULT 1,
  create_operations_incident TINYINT(1) NOT NULL DEFAULT 0,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  configuration_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_escalation_policies_public (public_id),
  UNIQUE KEY uq_admin_agent_escalation_policies_key (policy_key),
  KEY idx_admin_agent_escalation_policies_match (source_type,severity,enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_escalations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  escalation_key CHAR(64) NOT NULL,
  policy_id BIGINT UNSIGNED NOT NULL,
  source_type ENUM('finding','anomaly','correlation') NOT NULL,
  source_id BIGINT UNSIGNED NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL,
  escalation_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('scheduled','sent','acknowledged','suppressed','resolved') NOT NULL DEFAULT 'scheduled',
  due_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  acknowledged_by_user_id BIGINT UNSIGNED NULL,
  acknowledged_at DATETIME NULL,
  notification_public_id VARCHAR(64) NULL,
  evidence_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_escalations_public (public_id),
  UNIQUE KEY uq_admin_agent_escalations_key (escalation_key),
  KEY idx_admin_agent_escalations_due (status,due_at),
  KEY idx_admin_agent_escalations_source (source_type,source_id,status),
  CONSTRAINT fk_admin_agent_escalations_policy FOREIGN KEY (policy_id) REFERENCES admin_agent_escalation_policies(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_agent_escalations_ack FOREIGN KEY (acknowledged_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_executive_summaries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  summary_key CHAR(64) NOT NULL,
  period_type ENUM('daily','weekly','manual') NOT NULL DEFAULT 'daily',
  period_start DATETIME NOT NULL,
  period_end DATETIME NOT NULL,
  health_score TINYINT UNSIGNED NULL,
  title VARCHAR(240) NOT NULL,
  summary_text TEXT NOT NULL,
  blocks_json JSON NULL,
  generated_by_user_id BIGINT UNSIGNED NULL,
  generated_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_executive_summaries_public (public_id),
  UNIQUE KEY uq_admin_agent_executive_summaries_key (summary_key),
  KEY idx_admin_agent_executive_summaries_period (period_type,period_end),
  CONSTRAINT fk_admin_agent_executive_summaries_actor FOREIGN KEY (generated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_remediation_adapters (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  adapter_key VARCHAR(120) NOT NULL,
  label VARCHAR(240) NOT NULL,
  domain VARCHAR(80) NOT NULL,
  description VARCHAR(1000) NOT NULL,
  risk_level ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  execution_mode ENUM('in_process','disabled') NOT NULL DEFAULT 'disabled',
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  requires_confirmation TINYINT(1) NOT NULL DEFAULT 1,
  configuration_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_remediation_adapters_public (public_id),
  UNIQUE KEY uq_admin_agent_remediation_adapters_key (adapter_key),
  KEY idx_admin_agent_remediation_adapters_queue (enabled,risk_level,domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_remediation_executions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  idempotency_key CHAR(64) NOT NULL,
  review_id BIGINT UNSIGNED NOT NULL,
  adapter_id BIGINT UNSIGNED NOT NULL,
  approved_by_user_id BIGINT UNSIGNED NOT NULL,
  executed_by_user_id BIGINT UNSIGNED NULL,
  status ENUM('approved','running','succeeded','failed','rejected','canceled') NOT NULL DEFAULT 'approved',
  approval_note VARCHAR(1000) NULL,
  result_json JSON NULL,
  failure_code VARCHAR(120) NULL,
  failure_message VARCHAR(1000) NULL,
  approved_at DATETIME NOT NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_remediation_executions_public (public_id),
  UNIQUE KEY uq_admin_agent_remediation_executions_idempotency (idempotency_key),
  UNIQUE KEY uq_admin_agent_remediation_executions_review (review_id),
  KEY idx_admin_agent_remediation_executions_queue (status,approved_at),
  CONSTRAINT fk_admin_agent_remediation_executions_review FOREIGN KEY (review_id) REFERENCES admin_agent_action_reviews(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_agent_remediation_executions_adapter FOREIGN KEY (adapter_id) REFERENCES admin_agent_remediation_adapters(id) ON DELETE RESTRICT,
  CONSTRAINT fk_admin_agent_remediation_executions_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_admin_agent_remediation_executions_executor FOREIGN KEY (executed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO admin_agent_monitors
(public_id,monitor_key,label,domain,description,schedule_seconds,severity_on_failure,last_status,configuration_json)
VALUES
('01ADMINAGENTP2MONITOR00001','anomaly_baselines','Anomaly baselines','intelligence','Learns bounded statistical baselines from deterministic monitor metrics and identifies material deviations.',300,'high','never',JSON_OBJECT('source','admin_agent_metric_samples')),
('01ADMINAGENTP2MONITOR00002','cross_system_correlation','Cross-system correlation','intelligence','Correlates findings, anomalies, deployments, and system events into durable multi-domain incidents.',300,'critical','never',JSON_OBJECT('source','admin_agent_findings'));

INSERT IGNORE INTO admin_agent_runbooks (public_id,runbook_key,domain,title,summary,steps_json,enabled) VALUES
('01ADMINAGENTP2RUNBOOK00001','security_governance_change','security','Security and governance change review','Validate whether elevated security activity coincides with an expected administrative change.',JSON_ARRAY('Review correlated security events and audit actors.','Confirm the administrative change was expected.','Inspect affected sessions and permissions.','Escalate to incident mode if activity is unexplained.'),1),
('01ADMINAGENTP2RUNBOOK00002','notification_queue_pressure','notifications','Notification and queue pressure recovery','Restore delivery visibility while protecting the administrative queue from silent backlog growth.',JSON_ARRAY('Review failed or unread critical notifications.','Confirm queue automation freshness.','Prioritize overdue and escalated cases.','Verify delivery recovery before resolving the correlation.'),1),
('01ADMINAGENTP2RUNBOOK00003','automation_sla_degradation','automation','Automation and SLA degradation','Identify why automation is stale or failing while SLA pressure is increasing.',JSON_ARRAY('Review the latest automation run and failure class.','Inspect overdue, breached, and unassigned queue work.','Run approved automation only after review.','Confirm SLA pressure decreases after recovery.'),1),
('01ADMINAGENTP2RUNBOOK00004','deployment_regression','database','Deployment regression review','Compare post-deployment failures with the recorded release and determine whether rollback or repair is required.',JSON_ARRAY('Confirm the recorded branch and commit.','Review new critical or high findings after deployment.','Compare pending migrations and runtime errors.','Prepare rollback or scoped repair through the normal deployment workflow.'),1),
('01ADMINAGENTP2RUNBOOK00005','multi_domain_critical','operations','Multi-domain critical incident','Coordinate a single incident response when critical conditions span more than one platform domain.',JSON_ARRAY('Declare a primary incident owner.','Group affected findings and anomalies.','Establish customer and financial impact boundaries.','Track recovery evidence before resolving the correlation.'),1);

INSERT IGNORE INTO admin_agent_escalation_policies
(public_id,policy_key,source_type,severity,initial_delay_minutes,repeat_interval_minutes,maximum_level,notify_admin_center,create_operations_incident,enabled,configuration_json)
VALUES
('01ADMINAGENTP2POLICY000001','critical_correlation','correlation','critical',0,60,3,1,0,1,JSON_OBJECT('route','admin_notification_center')),
('01ADMINAGENTP2POLICY000002','high_correlation','correlation','high',15,180,2,1,0,1,JSON_OBJECT('route','admin_notification_center')),
('01ADMINAGENTP2POLICY000003','critical_anomaly','anomaly','critical',5,120,2,1,0,1,JSON_OBJECT('route','admin_notification_center')),
('01ADMINAGENTP2POLICY000004','high_finding','finding','high',30,240,2,1,0,1,JSON_OBJECT('route','admin_notification_center'));

INSERT IGNORE INTO admin_agent_remediation_adapters
(public_id,adapter_key,label,domain,description,risk_level,execution_mode,enabled,requires_confirmation,configuration_json)
VALUES
('01ADMINAGENTP2ADAPTER00001','run_admin_agent_scan','Run full Admin Agent analysis','system','Runs the deterministic monitor, anomaly, correlation, escalation, and summary pipeline.','low','in_process',1,1,JSON_OBJECT('financial',false,'destructive',false)),
('01ADMINAGENTP2ADAPTER00002','run_ai_credit_reconciliation','Run AI credit reconciliation','ai_accounting','Runs the existing database-first AI credit reconciliation service without changing provider configuration.','medium','in_process',1,1,JSON_OBJECT('financial',false,'destructive',false)),
('01ADMINAGENTP2ADAPTER00003','generate_migration_plan','Generate migration repair plan','database','Generates a read-only list of canonical migrations that are not recorded as applied.','low','in_process',1,1,JSON_OBJECT('financial',false,'destructive',false)),
('01ADMINAGENTP2ADAPTER00004','investigate_security_events','Generate security investigation package','security','Generates a read-only evidence package from recent normalized security events.','low','in_process',1,1,JSON_OBJECT('financial',false,'destructive',false)),
('01ADMINAGENTP2ADAPTER00005','run_queue_automation','Run queue automation','operations','Reserved for a future controlled queue-automation adapter.','high','disabled',0,1,JSON_OBJECT('reason','No direct execution adapter enabled in Phase 2')),
('01ADMINAGENTP2ADAPTER00006','retry_failed_notifications','Retry failed notifications','notifications','Reserved for a future delivery-specific retry adapter.','high','disabled',0,1,JSON_OBJECT('reason','No direct delivery mutation enabled in Phase 2')),
('01ADMINAGENTP2ADAPTER00007','declare_operations_incident','Declare operations incident','operations','Reserved for a future incident declaration adapter with explicit impact confirmation.','high','disabled',0,1,JSON_OBJECT('reason','Incident declaration remains manual in Phase 2'));

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.admin_agent.escalations','Manage Admin Agent escalations','Acknowledge and manage correlation, anomaly, and finding escalations.',NOW()),
('admin.admin_agent.deployments','Record Admin Agent deployments','Record deployment commit and branch metadata for post-release correlation.',NOW()),
('admin.admin_agent.execute','Execute approved Admin Agent remediation','Approve and execute allowlisted, in-process remediation adapters after explicit confirmation.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.admin_agent.escalations','admin.admin_agent.deployments')
WHERE r.slug IN ('admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug='admin.admin_agent.execute'
WHERE r.slug='super_admin';

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('20260718_main_admin_agent_phase2','Main Admin Agent anomaly baselines, correlation, escalation, deployment awareness, summaries, runbooks, and controlled remediation adapters.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
