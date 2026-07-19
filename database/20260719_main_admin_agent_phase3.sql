-- Main Admin Agent Phase 3
-- Service topology, SLO/error budgets, deterministic cause analysis, incident
-- workspaces, release gates, scheduled briefs, and controlled incident declaration.

CREATE TABLE IF NOT EXISTS admin_agent_services (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  service_key VARCHAR(100) NOT NULL,
  label VARCHAR(180) NOT NULL,
  domain VARCHAR(80) NOT NULL,
  tier ENUM('critical','high','standard') NOT NULL DEFAULT 'standard',
  owner_label VARCHAR(180) NULL,
  description VARCHAR(1000) NOT NULL,
  status ENUM('active','maintenance','retired') NOT NULL DEFAULT 'active',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_services_public (public_id),
  UNIQUE KEY uq_admin_agent_services_key (service_key),
  KEY idx_admin_agent_services_domain (domain,status,tier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_service_dependencies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  depends_on_service_id BIGINT UNSIGNED NOT NULL,
  dependency_type ENUM('hard','soft','data','delivery') NOT NULL DEFAULT 'hard',
  criticality ENUM('critical','high','standard') NOT NULL DEFAULT 'standard',
  description VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_service_dependencies_public (public_id),
  UNIQUE KEY uq_admin_agent_service_dependencies_pair (service_id,depends_on_service_id,dependency_type),
  KEY idx_admin_agent_service_dependencies_upstream (depends_on_service_id,criticality),
  CONSTRAINT fk_admin_agent_service_dependencies_service FOREIGN KEY (service_id) REFERENCES admin_agent_services(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_agent_service_dependencies_upstream FOREIGN KEY (depends_on_service_id) REFERENCES admin_agent_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_slo_policies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  policy_key VARCHAR(120) NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(180) NOT NULL,
  signal_type ENUM('event_error_rate','finding_pressure','monitor_availability') NOT NULL DEFAULT 'event_error_rate',
  objective_percent DECIMAL(7,4) NOT NULL DEFAULT 99.0000,
  window_minutes INT UNSIGNED NOT NULL DEFAULT 1440,
  warning_burn_rate DECIMAL(10,4) NOT NULL DEFAULT 2.0000,
  critical_burn_rate DECIMAL(10,4) NOT NULL DEFAULT 5.0000,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  configuration_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_slo_policies_public (public_id),
  UNIQUE KEY uq_admin_agent_slo_policies_key (policy_key),
  KEY idx_admin_agent_slo_policies_service (service_id,enabled),
  CONSTRAINT fk_admin_agent_slo_policies_service FOREIGN KEY (service_id) REFERENCES admin_agent_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_slo_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  policy_id BIGINT UNSIGNED NOT NULL,
  window_start DATETIME NOT NULL,
  window_end DATETIME NOT NULL,
  good_events BIGINT UNSIGNED NOT NULL DEFAULT 0,
  bad_events BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_events BIGINT UNSIGNED NOT NULL DEFAULT 0,
  availability_percent DECIMAL(10,6) NOT NULL DEFAULT 100.000000,
  error_budget_remaining_percent DECIMAL(10,6) NOT NULL DEFAULT 100.000000,
  burn_rate DECIMAL(18,6) NOT NULL DEFAULT 0,
  severity ENUM('healthy','warning','critical') NOT NULL DEFAULT 'healthy',
  evidence_json JSON NULL,
  generated_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_slo_snapshots_public (public_id),
  UNIQUE KEY uq_admin_agent_slo_snapshots_window (policy_id,window_start,window_end),
  KEY idx_admin_agent_slo_snapshots_severity (severity,generated_at),
  CONSTRAINT fk_admin_agent_slo_snapshots_policy FOREIGN KEY (policy_id) REFERENCES admin_agent_slo_policies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_incident_workspaces (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  workspace_key CHAR(64) NOT NULL,
  correlation_id BIGINT UNSIGNED NULL,
  ops_incident_id BIGINT UNSIGNED NULL,
  service_id BIGINT UNSIGNED NULL,
  title VARCHAR(240) NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'high',
  status ENUM('watching','declared','investigating','mitigating','monitoring','resolved','dismissed') NOT NULL DEFAULT 'watching',
  incident_commander_user_id BIGINT UNSIGNED NULL,
  summary VARCHAR(2000) NOT NULL,
  runbook_key VARCHAR(120) NULL,
  recommended_action_key VARCHAR(120) NULL,
  started_at DATETIME NOT NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_incident_workspaces_public (public_id),
  UNIQUE KEY uq_admin_agent_incident_workspaces_key (workspace_key),
  KEY idx_admin_agent_incident_workspaces_queue (status,severity,updated_at),
  CONSTRAINT fk_admin_agent_incident_workspaces_correlation FOREIGN KEY (correlation_id) REFERENCES admin_agent_correlations(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_incident_workspaces_ops FOREIGN KEY (ops_incident_id) REFERENCES admin_ops_incidents(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_incident_workspaces_service FOREIGN KEY (service_id) REFERENCES admin_agent_services(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_incident_workspaces_commander FOREIGN KEY (incident_commander_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_incident_timeline (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  title VARCHAR(240) NOT NULL,
  message VARCHAR(2000) NULL,
  source_table VARCHAR(100) NULL,
  source_id VARCHAR(100) NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  evidence_json JSON NULL,
  occurred_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_incident_timeline_public (public_id),
  KEY idx_admin_agent_incident_timeline_workspace (workspace_id,occurred_at),
  CONSTRAINT fk_admin_agent_incident_timeline_workspace FOREIGN KEY (workspace_id) REFERENCES admin_agent_incident_workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_agent_incident_timeline_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_cause_candidates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  candidate_key CHAR(64) NOT NULL,
  cause_type ENUM('deployment','anomaly','finding','dependency','change_activity','unknown') NOT NULL DEFAULT 'unknown',
  title VARCHAR(240) NOT NULL,
  explanation VARCHAR(2000) NOT NULL,
  confidence_percent DECIMAL(7,3) NOT NULL DEFAULT 0,
  rank_order INT UNSIGNED NOT NULL DEFAULT 1,
  source_table VARCHAR(100) NULL,
  source_public_id VARCHAR(100) NULL,
  evidence_json JSON NULL,
  first_detected_at DATETIME NOT NULL,
  last_detected_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_cause_candidates_public (public_id),
  UNIQUE KEY uq_admin_agent_cause_candidates_key (workspace_id,candidate_key),
  KEY idx_admin_agent_cause_candidates_rank (workspace_id,rank_order,confidence_percent),
  CONSTRAINT fk_admin_agent_cause_candidates_workspace FOREIGN KEY (workspace_id) REFERENCES admin_agent_incident_workspaces(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_release_gates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  gate_key CHAR(64) NOT NULL,
  deployment_id BIGINT UNSIGNED NULL,
  environment_key VARCHAR(40) NOT NULL DEFAULT 'production',
  status ENUM('pass','warn','block') NOT NULL DEFAULT 'pass',
  score TINYINT UNSIGNED NOT NULL DEFAULT 100,
  health_score TINYINT UNSIGNED NULL,
  critical_slo_total INT UNSIGNED NOT NULL DEFAULT 0,
  active_incident_total INT UNSIGNED NOT NULL DEFAULT 0,
  blocking_reasons_json JSON NULL,
  evidence_json JSON NULL,
  evaluated_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_release_gates_public (public_id),
  UNIQUE KEY uq_admin_agent_release_gates_key (gate_key),
  KEY idx_admin_agent_release_gates_environment (environment_key,evaluated_at),
  CONSTRAINT fk_admin_agent_release_gates_deployment FOREIGN KEY (deployment_id) REFERENCES admin_agent_deployments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_brief_subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  cadence ENUM('daily','weekly') NOT NULL DEFAULT 'daily',
  channel ENUM('notification_center') NOT NULL DEFAULT 'notification_center',
  hour_utc TINYINT UNSIGNED NOT NULL DEFAULT 13,
  weekday_utc TINYINT UNSIGNED NOT NULL DEFAULT 1,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  last_delivered_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_brief_subscriptions_public (public_id),
  UNIQUE KEY uq_admin_agent_brief_subscriptions_user (admin_user_id,cadence,channel),
  KEY idx_admin_agent_brief_subscriptions_due (enabled,cadence,hour_utc,weekday_utc),
  CONSTRAINT fk_admin_agent_brief_subscriptions_user FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_brief_deliveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  delivery_key CHAR(64) NOT NULL,
  subscription_id BIGINT UNSIGNED NOT NULL,
  summary_id BIGINT UNSIGNED NULL,
  status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  notification_public_id VARCHAR(64) NULL,
  failure_message VARCHAR(1000) NULL,
  delivered_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_brief_deliveries_public (public_id),
  UNIQUE KEY uq_admin_agent_brief_deliveries_key (delivery_key),
  KEY idx_admin_agent_brief_deliveries_status (status,created_at),
  CONSTRAINT fk_admin_agent_brief_deliveries_subscription FOREIGN KEY (subscription_id) REFERENCES admin_agent_brief_subscriptions(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_agent_brief_deliveries_summary FOREIGN KEY (summary_id) REFERENCES admin_agent_executive_summaries(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO admin_agent_services
(public_id,service_key,label,domain,tier,owner_label,description,status,metadata_json)
VALUES
('01ADMINAGENTP3SERVICE00001','identity_access','Identity and access','governance','critical','Platform administration','Authentication, sessions, roles, permissions, and administrator access controls.','active',JSON_OBJECT()),
('01ADMINAGENTP3SERVICE00002','database_core','Database core','database','critical','Platform engineering','Canonical schema, migrations, data integrity, and durable application state.','active',JSON_OBJECT()),
('01ADMINAGENTP3SERVICE00003','commerce_payments','Commerce and payments','commerce','critical','Commerce operations','Checkout, orders, payments, refunds, disputes, subscriptions, and tips.','active',JSON_OBJECT()),
('01ADMINAGENTP3SERVICE00004','claims_redemption','Claims and redemption','claims','critical','Lifecycle operations','Claim codes, QR flows, gift claims, redemption, and merchant verification.','active',JSON_OBJECT()),
('01ADMINAGENTP3SERVICE00005','notification_delivery','Notification delivery','notifications','high','Messaging operations','Customer, merchant, and administrator delivery queues and provider outcomes.','active',JSON_OBJECT()),
('01ADMINAGENTP3SERVICE00006','admin_automation','Administrative automation','automation','high','Operations administration','Scheduled automation, queue processing, SLA routing, and operational workflows.','active',JSON_OBJECT()),
('01ADMINAGENTP3SERVICE00007','admin_operations','Admin operations','operations','high','Operations administration','Command center, incidents, reviews, escalations, and support queue coordination.','active',JSON_OBJECT()),
('01ADMINAGENTP3SERVICE00008','security_observability','Security observability','security','critical','Security administration','Security logging, suspicious activity, permission denials, and evidence preservation.','active',JSON_OBJECT()),
('01ADMINAGENTP3SERVICE00009','ai_accounting','AI accounting','ai_accounting','high','AI administration','Provider usage, credit reconciliation, limits, and accounting incident management.','active',JSON_OBJECT()),
('01ADMINAGENTP3SERVICE00010','campaign_delivery','Campaign delivery','campaigns','high','Merchant success','Campaigns, rewards, embeds, messages, and scheduled merchant promotion delivery.','active',JSON_OBJECT()),
('01ADMINAGENTP3SERVICE00011','storefront_experience','Storefront experience','storefront','high','Merchant platform','Merchant catalogs, storefront pages, media, discovery, and customer purchase entry points.','active',JSON_OBJECT());

INSERT IGNORE INTO admin_agent_service_dependencies
(public_id,service_id,depends_on_service_id,dependency_type,criticality,description)
SELECT '01ADMINAGENTP3DEPEND000001',s.id,d.id,'hard','critical','All durable application services depend on the primary database.'
FROM admin_agent_services s JOIN admin_agent_services d ON d.service_key='database_core' WHERE s.service_key='identity_access';
INSERT IGNORE INTO admin_agent_service_dependencies
(public_id,service_id,depends_on_service_id,dependency_type,criticality,description)
SELECT '01ADMINAGENTP3DEPEND000002',s.id,d.id,'hard','critical','Commerce transactions require identity and access authority.'
FROM admin_agent_services s JOIN admin_agent_services d ON d.service_key='identity_access' WHERE s.service_key='commerce_payments';
INSERT IGNORE INTO admin_agent_service_dependencies
(public_id,service_id,depends_on_service_id,dependency_type,criticality,description)
SELECT '01ADMINAGENTP3DEPEND000003',s.id,d.id,'hard','critical','Claims and redemption require durable database state.'
FROM admin_agent_services s JOIN admin_agent_services d ON d.service_key='database_core' WHERE s.service_key='claims_redemption';
INSERT IGNORE INTO admin_agent_service_dependencies
(public_id,service_id,depends_on_service_id,dependency_type,criticality,description)
SELECT '01ADMINAGENTP3DEPEND000004',s.id,d.id,'delivery','high','Campaign delivery depends on notification delivery.'
FROM admin_agent_services s JOIN admin_agent_services d ON d.service_key='notification_delivery' WHERE s.service_key='campaign_delivery';
INSERT IGNORE INTO admin_agent_service_dependencies
(public_id,service_id,depends_on_service_id,dependency_type,criticality,description)
SELECT '01ADMINAGENTP3DEPEND000005',s.id,d.id,'hard','high','Administrative automation depends on database state.'
FROM admin_agent_services s JOIN admin_agent_services d ON d.service_key='database_core' WHERE s.service_key='admin_automation';
INSERT IGNORE INTO admin_agent_service_dependencies
(public_id,service_id,depends_on_service_id,dependency_type,criticality,description)
SELECT '01ADMINAGENTP3DEPEND000006',s.id,d.id,'data','high','AI accounting depends on database usage and ledger records.'
FROM admin_agent_services s JOIN admin_agent_services d ON d.service_key='database_core' WHERE s.service_key='ai_accounting';
INSERT IGNORE INTO admin_agent_service_dependencies
(public_id,service_id,depends_on_service_id,dependency_type,criticality,description)
SELECT '01ADMINAGENTP3DEPEND000007',s.id,d.id,'hard','critical','Storefront commerce depends on checkout and payment services.'
FROM admin_agent_services s JOIN admin_agent_services d ON d.service_key='commerce_payments' WHERE s.service_key='storefront_experience';

INSERT IGNORE INTO admin_agent_slo_policies
(public_id,policy_key,service_id,label,signal_type,objective_percent,window_minutes,warning_burn_rate,critical_burn_rate,enabled,configuration_json)
SELECT CONCAT('P3SLO',LPAD(s.id,21,'0')),CONCAT(s.service_key,'_daily_availability'),s.id,CONCAT(s.label,' daily availability'),'event_error_rate',
CASE WHEN s.tier='critical' THEN 99.9000 WHEN s.tier='high' THEN 99.5000 ELSE 99.0000 END,1440,2.0000,5.0000,1,JSON_OBJECT('domain',s.domain)
FROM admin_agent_services s;

INSERT IGNORE INTO admin_agent_brief_subscriptions
(public_id,admin_user_id,cadence,channel,hour_utc,weekday_utc,enabled)
SELECT CONCAT('P3BRIEF',LPAD(u.id,19,'0')),u.id,'daily','notification_center',13,1,1
FROM users u
JOIN user_roles ur ON ur.user_id=u.id
JOIN roles r ON r.id=ur.role_id
WHERE r.slug='super_admin';

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.admin_agent.incidents','Manage Admin Agent incident workspaces','Create, assign, update, and resolve deterministic Admin Agent incident workspaces.',NOW()),
('admin.admin_agent.releases','Manage Admin Agent release gates','Evaluate release readiness and record release-gate decisions.',NOW()),
('admin.admin_agent.briefs','Manage Admin Agent brief delivery','Manage scheduled database-only executive brief delivery.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.admin_agent.incidents','admin.admin_agent.releases','admin.admin_agent.briefs')
WHERE r.slug IN ('admin','super_admin');

UPDATE admin_agent_remediation_adapters
SET execution_mode='in_process',enabled=1,requires_confirmation=1,
    description='Declare an operations incident through the existing incident service after explicit review, approval, and typed confirmation.',
    configuration_json=JSON_OBJECT('allowed_modes',JSON_ARRAY('payment_outage','fulfillment_backlog','claim_redemption_issue','notification_delivery_issue','fraud_risk_spike','merchant_onboarding_backlog','catalog_publishing_issue')),
    updated_at=NOW()
WHERE adapter_key='declare_operations_incident';

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('20260719_main_admin_agent_phase3','Main Admin Agent service topology, SLO/error budgets, incident workspaces, cause analysis, release gates, brief delivery, and controlled incident declaration.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);