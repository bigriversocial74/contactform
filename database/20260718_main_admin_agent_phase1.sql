-- Main Admin Agent Phase 1
-- Unified monitor registry, normalized system event stream, durable findings,
-- admin chat history, and review-gated action requests.

CREATE TABLE IF NOT EXISTS admin_agent_monitors (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  monitor_key VARCHAR(100) NOT NULL,
  label VARCHAR(160) NOT NULL,
  domain VARCHAR(80) NOT NULL,
  description VARCHAR(500) NULL,
  schedule_seconds INT UNSIGNED NOT NULL DEFAULT 300,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  severity_on_failure ENUM('low','medium','high','critical') NOT NULL DEFAULT 'high',
  last_status ENUM('never','running','healthy','warning','critical','failed','disabled') NOT NULL DEFAULT 'never',
  last_started_at DATETIME NULL,
  last_completed_at DATETIME NULL,
  last_success_at DATETIME NULL,
  consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(1000) NULL,
  configuration_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_monitors_public (public_id),
  UNIQUE KEY uq_admin_agent_monitors_key (monitor_key),
  KEY idx_admin_agent_monitors_due (enabled,last_completed_at,schedule_seconds),
  KEY idx_admin_agent_monitors_domain_status (domain,last_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_scans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  trigger_source ENUM('scheduled','manual','workspace_load','api') NOT NULL DEFAULT 'scheduled',
  status ENUM('running','completed','failed') NOT NULL DEFAULT 'running',
  initiated_by_user_id BIGINT UNSIGNED NULL,
  monitors_run INT UNSIGNED NOT NULL DEFAULT 0,
  events_ingested INT UNSIGNED NOT NULL DEFAULT 0,
  findings_created INT UNSIGNED NOT NULL DEFAULT 0,
  findings_updated INT UNSIGNED NOT NULL DEFAULT 0,
  findings_resolved INT UNSIGNED NOT NULL DEFAULT 0,
  health_score TINYINT UNSIGNED NULL,
  failure_message VARCHAR(1000) NULL,
  metrics_json JSON NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_scans_public (public_id),
  KEY idx_admin_agent_scans_status_started (status,started_at),
  KEY idx_admin_agent_scans_trigger_started (trigger_source,started_at),
  CONSTRAINT fk_admin_agent_scans_actor FOREIGN KEY (initiated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  event_key CHAR(64) NOT NULL,
  monitor_key VARCHAR(100) NOT NULL,
  domain VARCHAR(80) NOT NULL,
  severity ENUM('debug','info','warning','error','critical') NOT NULL DEFAULT 'info',
  event_type VARCHAR(160) NOT NULL,
  title VARCHAR(240) NOT NULL,
  message VARCHAR(2000) NULL,
  source_table VARCHAR(100) NULL,
  source_id VARCHAR(190) NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  entity_type VARCHAR(100) NULL,
  entity_id VARCHAR(190) NULL,
  evidence_json JSON NULL,
  occurred_at DATETIME NOT NULL,
  ingested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_events_public (public_id),
  UNIQUE KEY uq_admin_agent_events_key (event_key),
  KEY idx_admin_agent_events_live (id,occurred_at),
  KEY idx_admin_agent_events_domain_time (domain,occurred_at),
  KEY idx_admin_agent_events_severity_time (severity,occurred_at),
  KEY idx_admin_agent_events_monitor_time (monitor_key,occurred_at),
  CONSTRAINT fk_admin_agent_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_findings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  finding_key CHAR(64) NOT NULL,
  monitor_key VARCHAR(100) NOT NULL,
  domain VARCHAR(80) NOT NULL,
  finding_type VARCHAR(120) NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  status ENUM('open','acknowledged','under_review','resolved','dismissed') NOT NULL DEFAULT 'open',
  title VARCHAR(240) NOT NULL,
  summary VARCHAR(2000) NOT NULL,
  source_reference VARCHAR(190) NULL,
  evidence_json JSON NULL,
  first_detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  occurrence_count INT UNSIGNED NOT NULL DEFAULT 1,
  recurrence_count INT UNSIGNED NOT NULL DEFAULT 0,
  assigned_admin_user_id BIGINT UNSIGNED NULL,
  acknowledged_by_user_id BIGINT UNSIGNED NULL,
  acknowledged_at DATETIME NULL,
  resolved_by_user_id BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  resolution_note VARCHAR(1000) NULL,
  last_scan_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_findings_public (public_id),
  UNIQUE KEY uq_admin_agent_findings_key (finding_key),
  KEY idx_admin_agent_findings_queue (status,severity,last_detected_at),
  KEY idx_admin_agent_findings_domain (domain,status,last_detected_at),
  KEY idx_admin_agent_findings_assignment (assigned_admin_user_id,status),
  CONSTRAINT fk_admin_agent_findings_assignee FOREIGN KEY (assigned_admin_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_findings_ack FOREIGN KEY (acknowledged_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_findings_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_findings_scan FOREIGN KEY (last_scan_id) REFERENCES admin_agent_scans(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_finding_actions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  finding_id BIGINT UNSIGNED NOT NULL,
  action_type VARCHAR(80) NOT NULL,
  admin_user_id BIGINT UNSIGNED NULL,
  note VARCHAR(1000) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_finding_actions_public (public_id),
  KEY idx_admin_agent_finding_actions_finding (finding_id,created_at),
  KEY idx_admin_agent_finding_actions_actor (admin_user_id,created_at),
  CONSTRAINT fk_admin_agent_finding_actions_finding FOREIGN KEY (finding_id) REFERENCES admin_agent_findings(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_agent_finding_actions_actor FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_threads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL DEFAULT 'Current system chat',
  status ENUM('active','saved','archived') NOT NULL DEFAULT 'active',
  last_message_at DATETIME NULL,
  archived_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_threads_public (public_id),
  KEY idx_admin_agent_threads_admin (admin_user_id,status,updated_at),
  CONSTRAINT fk_admin_agent_threads_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  thread_id BIGINT UNSIGNED NOT NULL,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('user','assistant','system') NOT NULL,
  message_type VARCHAR(80) NOT NULL DEFAULT 'chat',
  content LONGTEXT NOT NULL,
  blocks_json JSON NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_messages_public (public_id),
  KEY idx_admin_agent_messages_thread (thread_id,id),
  KEY idx_admin_agent_messages_admin (admin_user_id,created_at),
  CONSTRAINT fk_admin_agent_messages_thread FOREIGN KEY (thread_id) REFERENCES admin_agent_threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_agent_messages_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_action_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  idempotency_key CHAR(64) NOT NULL,
  requested_by_user_id BIGINT UNSIGNED NOT NULL,
  finding_id BIGINT UNSIGNED NULL,
  action_key VARCHAR(120) NOT NULL,
  domain VARCHAR(80) NOT NULL,
  title VARCHAR(240) NOT NULL,
  rationale VARCHAR(2000) NULL,
  payload_json JSON NULL,
  risk_level ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  status ENUM('pending','approved','rejected','executed','canceled') NOT NULL DEFAULT 'pending',
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  review_note VARCHAR(1000) NULL,
  reviewed_at DATETIME NULL,
  executed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_action_reviews_public (public_id),
  UNIQUE KEY uq_admin_agent_action_reviews_idempotency (idempotency_key),
  KEY idx_admin_agent_action_reviews_queue (status,risk_level,created_at),
  KEY idx_admin_agent_action_reviews_requester (requested_by_user_id,created_at),
  CONSTRAINT fk_admin_agent_action_reviews_requester FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_agent_action_reviews_finding FOREIGN KEY (finding_id) REFERENCES admin_agent_findings(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_action_reviews_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO admin_agent_monitors
(public_id,monitor_key,label,domain,description,schedule_seconds,severity_on_failure,last_status,configuration_json)
VALUES
('01ADMINAGENTMONITOR000001','security_events','Security events','security','Normalizes recent warning, error, and critical security events.',60,'critical','never',JSON_OBJECT('source','security_logs')),
('01ADMINAGENTMONITOR000002','audit_activity','Audit activity','governance','Normalizes recent administrative and platform audit activity.',120,'high','never',JSON_OBJECT('source','audit_logs')),
('01ADMINAGENTMONITOR000003','operations_incidents','Operations incidents','operations','Tracks active incident mode records, ownership, severity, and recovery state.',60,'critical','never',JSON_OBJECT('source','admin_ops_incidents')),
('01ADMINAGENTMONITOR000004','support_queue_sla','Queue and SLA','support','Tracks breached, overdue, escalated, aging, and unassigned administrative work.',120,'high','never',JSON_OBJECT('source','admin_user_notes')),
('01ADMINAGENTMONITOR000005','notification_delivery','Admin notifications','notifications','Tracks unread critical alerts, automation failures, and operational notification pressure.',120,'high','never',JSON_OBJECT('source','admin_queue_notifications')),
('01ADMINAGENTMONITOR000006','automation_freshness','Automation freshness','automation','Tracks failed and stale administrative automation runs.',300,'high','never',JSON_OBJECT('source','admin_queue_automation_runs')),
('01ADMINAGENTMONITOR000007','ai_credit_accounting','AI credit accounting','ai_accounting','Tracks active AI credit reconciliation incidents and unresolved token differences.',300,'critical','never',JSON_OBJECT('source','ai_credit_reconciliation_incidents')),
('01ADMINAGENTMONITOR000008','migration_readiness','Migration readiness','database','Tracks canonical migration application and schema readiness.',300,'critical','never',JSON_OBJECT('source','schema_migrations'));

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.admin_agent.view','View Main Admin Agent','View unified system monitoring, live events, findings, snapshots, and Admin Agent chat.',NOW()),
('admin.admin_agent.chat','Use Main Admin Agent chat','Use database-first system reports and persistent admin chat threads.',NOW()),
('admin.admin_agent.manage','Manage Main Admin Agent findings','Run scans, acknowledge, assign, review, resolve, dismiss, and reopen system findings.',NOW()),
('admin.admin_agent.actions','Request Admin Agent actions','Create review-gated remediation requests. This does not permit autonomous execution.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.admin_agent.view','admin.admin_agent.chat','admin.admin_agent.manage','admin.admin_agent.actions')
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('20260718_main_admin_agent_phase1','Main Admin Agent monitor registry, event stream, findings, chat history, and review-gated action requests.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);