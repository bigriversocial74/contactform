-- Main Admin Agent Phase 5
-- Business continuity, recovery objectives, backup evidence, restore drills,
-- dependency-aware recovery plans, continuity scorecards, recovery gaps,
-- and review-gated approval of externally executed drill records.

CREATE TABLE IF NOT EXISTS admin_agent_recovery_objectives (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  objective_key CHAR(64) NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  environment_key VARCHAR(40) NOT NULL DEFAULT 'production',
  criticality ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  rto_minutes INT UNSIGNED NOT NULL DEFAULT 240,
  rpo_minutes INT UNSIGNED NOT NULL DEFAULT 1440,
  backup_max_age_minutes INT UNSIGNED NOT NULL DEFAULT 1440,
  drill_interval_days SMALLINT UNSIGNED NOT NULL DEFAULT 90,
  status ENUM('active','needs_review','retired') NOT NULL DEFAULT 'active',
  owner_user_id BIGINT UNSIGNED NULL,
  evidence_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_recovery_objectives_public (public_id),
  UNIQUE KEY uq_admin_agent_recovery_objectives_key (objective_key),
  UNIQUE KEY uq_admin_agent_recovery_objectives_service (service_id,environment_key),
  KEY idx_admin_agent_recovery_objectives_status (status,criticality),
  CONSTRAINT fk_admin_agent_recovery_objectives_service FOREIGN KEY (service_id) REFERENCES admin_agent_services(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_agent_recovery_objectives_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_backup_evidence (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  evidence_key CHAR(64) NOT NULL,
  source_key VARCHAR(120) NOT NULL DEFAULT 'database_backup_restore_validator',
  environment_key VARCHAR(40) NOT NULL DEFAULT 'production',
  scope_key VARCHAR(120) NOT NULL DEFAULT 'database',
  run_id VARCHAR(160) NOT NULL,
  status ENUM('passed','failed','incomplete') NOT NULL DEFAULT 'incomplete',
  backup_created_at DATETIME NULL,
  restore_completed_at DATETIME NULL,
  backup_size_bytes BIGINT UNSIGNED NULL,
  backup_sha256 CHAR(64) NULL,
  source_table_count INT UNSIGNED NULL,
  restore_table_count INT UNSIGNED NULL,
  source_migration_count INT UNSIGNED NULL,
  restore_migration_count INT UNSIGNED NULL,
  canary_verified TINYINT(1) NOT NULL DEFAULT 0,
  manifest_verified TINYINT(1) NOT NULL DEFAULT 0,
  migration_status_verified TINYINT(1) NOT NULL DEFAULT 0,
  report_path VARCHAR(500) NULL,
  details_json JSON NULL,
  recorded_by_user_id BIGINT UNSIGNED NULL,
  recorded_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_backup_evidence_public (public_id),
  UNIQUE KEY uq_admin_agent_backup_evidence_key (evidence_key),
  KEY idx_admin_agent_backup_evidence_scope (environment_key,scope_key,recorded_at),
  KEY idx_admin_agent_backup_evidence_status (status,recorded_at),
  CONSTRAINT fk_admin_agent_backup_evidence_actor FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_restore_drills (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  drill_key CHAR(64) NOT NULL,
  objective_id BIGINT UNSIGNED NULL,
  evidence_id BIGINT UNSIGNED NULL,
  environment_key VARCHAR(40) NOT NULL DEFAULT 'production',
  scope_key VARCHAR(120) NOT NULL DEFAULT 'database',
  title VARCHAR(240) NOT NULL,
  status ENUM('planned','running','review_ready','passed','failed','canceled') NOT NULL DEFAULT 'planned',
  target_rto_minutes INT UNSIGNED NULL,
  target_rpo_minutes INT UNSIGNED NULL,
  actual_rto_minutes INT UNSIGNED NULL,
  actual_rpo_minutes INT UNSIGNED NULL,
  executed_externally TINYINT(1) NOT NULL DEFAULT 1,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  summary_text TEXT NULL,
  gaps_json JSON NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  approved_by_user_id BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_restore_drills_public (public_id),
  UNIQUE KEY uq_admin_agent_restore_drills_key (drill_key),
  KEY idx_admin_agent_restore_drills_status (status,environment_key,completed_at),
  KEY idx_admin_agent_restore_drills_objective (objective_id,status,completed_at),
  CONSTRAINT fk_admin_agent_restore_drills_objective FOREIGN KEY (objective_id) REFERENCES admin_agent_recovery_objectives(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_restore_drills_evidence FOREIGN KEY (evidence_id) REFERENCES admin_agent_backup_evidence(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_restore_drills_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_restore_drills_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_recovery_plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  plan_key CHAR(64) NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  environment_key VARCHAR(40) NOT NULL DEFAULT 'production',
  plan_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('draft','ready','needs_review','retired') NOT NULL DEFAULT 'draft',
  recovery_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  prerequisites_json JSON NULL,
  validation_steps_json JSON NULL,
  runbook_path VARCHAR(500) NULL,
  owner_user_id BIGINT UNSIGNED NULL,
  last_reviewed_by_user_id BIGINT UNSIGNED NULL,
  last_reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_recovery_plans_public (public_id),
  UNIQUE KEY uq_admin_agent_recovery_plans_key (plan_key),
  UNIQUE KEY uq_admin_agent_recovery_plans_service (service_id,environment_key,plan_version),
  KEY idx_admin_agent_recovery_plans_status (status,recovery_order),
  CONSTRAINT fk_admin_agent_recovery_plans_service FOREIGN KEY (service_id) REFERENCES admin_agent_services(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_agent_recovery_plans_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_recovery_plans_reviewer FOREIGN KEY (last_reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_recovery_gaps (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  gap_key CHAR(64) NOT NULL,
  service_id BIGINT UNSIGNED NULL,
  objective_id BIGINT UNSIGNED NULL,
  evidence_id BIGINT UNSIGNED NULL,
  drill_id BIGINT UNSIGNED NULL,
  gap_type ENUM('missing_objective','stale_backup','failed_backup','missing_drill','overdue_drill','rto_miss','rpo_miss','missing_plan','plan_review','evidence_incomplete') NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  status ENUM('open','acknowledged','under_review','resolved','dismissed') NOT NULL DEFAULT 'open',
  title VARCHAR(240) NOT NULL,
  details_text TEXT NOT NULL,
  recommendation_text TEXT NULL,
  occurrence_count INT UNSIGNED NOT NULL DEFAULT 1,
  owner_user_id BIGINT UNSIGNED NULL,
  acknowledged_by_user_id BIGINT UNSIGNED NULL,
  acknowledged_at DATETIME NULL,
  resolved_by_user_id BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  evidence_json JSON NULL,
  first_seen_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_recovery_gaps_public (public_id),
  UNIQUE KEY uq_admin_agent_recovery_gaps_key (gap_key),
  KEY idx_admin_agent_recovery_gaps_queue (status,severity,last_seen_at),
  KEY idx_admin_agent_recovery_gaps_service (service_id,status,severity),
  CONSTRAINT fk_admin_agent_recovery_gaps_service FOREIGN KEY (service_id) REFERENCES admin_agent_services(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_recovery_gaps_objective FOREIGN KEY (objective_id) REFERENCES admin_agent_recovery_objectives(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_recovery_gaps_evidence FOREIGN KEY (evidence_id) REFERENCES admin_agent_backup_evidence(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_recovery_gaps_drill FOREIGN KEY (drill_id) REFERENCES admin_agent_restore_drills(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_recovery_gaps_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_recovery_gaps_ack FOREIGN KEY (acknowledged_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_recovery_gaps_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_continuity_scorecards (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  scorecard_key CHAR(64) NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  environment_key VARCHAR(40) NOT NULL DEFAULT 'production',
  continuity_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('healthy','watch','attention','critical') NOT NULL DEFAULT 'attention',
  objective_compliant TINYINT(1) NOT NULL DEFAULT 0,
  backup_fresh TINYINT(1) NOT NULL DEFAULT 0,
  last_backup_age_minutes INT UNSIGNED NULL,
  last_passed_drill_age_days INT UNSIGNED NULL,
  open_gap_total INT UNSIGNED NOT NULL DEFAULT 0,
  critical_gap_total INT UNSIGNED NOT NULL DEFAULT 0,
  evidence_json JSON NULL,
  generated_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_continuity_scorecards_public (public_id),
  UNIQUE KEY uq_admin_agent_continuity_scorecards_key (scorecard_key),
  KEY idx_admin_agent_continuity_scorecards_service (service_id,generated_at),
  KEY idx_admin_agent_continuity_scorecards_status (status,generated_at),
  CONSTRAINT fk_admin_agent_continuity_scorecards_service FOREIGN KEY (service_id) REFERENCES admin_agent_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.admin_agent.continuity','View Admin Agent continuity assurance','View recovery objectives, backup evidence, restore drills, continuity scorecards, and recovery gaps.',NOW()),
('admin.admin_agent.recovery','Manage Admin Agent recovery governance','Manage recovery objectives, plans, drill records, and recovery-gap lifecycle without executing infrastructure recovery.',NOW()),
('admin.admin_agent.evidence','Record Admin Agent recovery evidence','Record evidence produced by approved external backup and restore validation procedures.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.admin_agent.continuity','admin.admin_agent.recovery','admin.admin_agent.evidence')
WHERE r.slug IN ('admin','super_admin');

INSERT IGNORE INTO admin_agent_recovery_objectives
(public_id,objective_key,service_id,environment_key,criticality,rto_minutes,rpo_minutes,backup_max_age_minutes,drill_interval_days,status,evidence_json)
SELECT
  REPLACE(UUID(),'-',''),
  SHA2(CONCAT('production|',s.service_key),256),
  s.id,
  'production',
  CASE s.tier WHEN 'critical' THEN 'critical' WHEN 'high' THEN 'high' WHEN 'medium' THEN 'medium' ELSE 'low' END,
  CASE s.tier WHEN 'critical' THEN 60 WHEN 'high' THEN 120 WHEN 'medium' THEN 240 ELSE 480 END,
  CASE s.tier WHEN 'critical' THEN 15 WHEN 'high' THEN 60 WHEN 'medium' THEN 240 ELSE 1440 END,
  CASE s.tier WHEN 'critical' THEN 60 WHEN 'high' THEN 240 WHEN 'medium' THEN 720 ELSE 1440 END,
  CASE s.tier WHEN 'critical' THEN 30 WHEN 'high' THEN 60 ELSE 90 END,
  'needs_review',
  JSON_OBJECT('seeded_from_service_tier',true,'administrator_review_required',true)
FROM admin_agent_services s
WHERE s.status='active';

INSERT IGNORE INTO admin_agent_recovery_plans
(public_id,plan_key,service_id,environment_key,plan_version,status,recovery_order,prerequisites_json,validation_steps_json,runbook_path)
SELECT
  REPLACE(UUID(),'-',''),
  SHA2(CONCAT('production|',s.service_key,'|v1'),256),
  s.id,
  'production',
  1,
  'draft',
  CASE s.tier WHEN 'critical' THEN 10 WHEN 'high' THEN 30 WHEN 'medium' THEN 60 ELSE 90 END,
  JSON_ARRAY('Confirm approved incident commander','Confirm current backup evidence','Confirm dependent services and credentials are available'),
  JSON_ARRAY('Validate application health','Validate migration state','Validate canary transaction','Record observed RTO and RPO'),
  'docs/operations/UPGRADE_ROLLBACK_RESTORE_RUNBOOK.md'
FROM admin_agent_services s
WHERE s.status='active';

INSERT IGNORE INTO admin_agent_remediation_adapters
(public_id,adapter_key,label,domain,description,risk_level,execution_mode,enabled,requires_confirmation,configuration_json)
VALUES
('01ADMINAGENTP5ADAPTER00001','approve_recovery_drill_record','Approve recovery drill record','operations','Approves an already completed external restore-drill record only after passed evidence, explicit review, approval, and typed confirmation. It does not execute a backup, restore, failover, rollback, database import, or infrastructure command.','medium','in_process',1,1,JSON_OBJECT('phase',5,'review_required',true,'external_execution_only',true,'financial',false,'destructive',false));

UPDATE admin_agent_remediation_adapters
SET label='Approve recovery drill record',domain='operations',
    description='Approves an already completed external restore-drill record only after passed evidence, explicit review, approval, and typed confirmation. It does not execute a backup, restore, failover, rollback, database import, or infrastructure command.',
    risk_level='medium',execution_mode='in_process',enabled=1,requires_confirmation=1,
    configuration_json=JSON_OBJECT('phase',5,'review_required',true,'external_execution_only',true,'financial',false,'destructive',false),updated_at=NOW()
WHERE adapter_key='approve_recovery_drill_record';

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('20260719_main_admin_agent_phase5','Main Admin Agent recovery objectives, backup evidence, restore drills, recovery plans, continuity scorecards, recovery gaps, and controlled drill-record approval.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);