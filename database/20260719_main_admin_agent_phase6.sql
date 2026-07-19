-- Main Admin Agent Phase 6
-- Final operational readiness, continuity escalation, drill scheduling,
-- evidence attestations, scheduler health, readiness exports, and retention previews.

CREATE TABLE IF NOT EXISTS admin_agent_phase6_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  environment_key VARCHAR(40) NOT NULL DEFAULT 'production',
  continuity_alerts_enabled TINYINT(1) NOT NULL DEFAULT 1,
  daily_brief_enabled TINYINT(1) NOT NULL DEFAULT 1,
  daily_brief_hour_utc TINYINT UNSIGNED NOT NULL DEFAULT 15,
  weekly_brief_enabled TINYINT(1) NOT NULL DEFAULT 1,
  weekly_brief_day_utc TINYINT UNSIGNED NOT NULL DEFAULT 1,
  weekly_brief_hour_utc TINYINT UNSIGNED NOT NULL DEFAULT 15,
  expected_runner_interval_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  scheduler_stale_after_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  scorecard_retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 365,
  event_retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 180,
  resolved_alert_retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 365,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_phase6_settings_public (public_id),
  UNIQUE KEY uq_admin_agent_phase6_settings_environment (environment_key),
  CONSTRAINT fk_admin_agent_phase6_settings_actor FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_scheduler_heartbeats (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  runner_key VARCHAR(100) NOT NULL DEFAULT 'main_admin_agent_phase6',
  environment_key VARCHAR(40) NOT NULL DEFAULT 'production',
  trigger_source ENUM('scheduled','manual','workspace','setup') NOT NULL DEFAULT 'manual',
  status ENUM('running','succeeded','failed') NOT NULL DEFAULT 'running',
  started_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  duration_ms INT UNSIGNED NULL,
  initiated_by_user_id BIGINT UNSIGNED NULL,
  summary_json JSON NULL,
  error_class VARCHAR(160) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_scheduler_heartbeats_public (public_id),
  KEY idx_admin_agent_scheduler_heartbeats_runner (runner_key,environment_key,trigger_source,status,started_at),
  CONSTRAINT fk_admin_agent_scheduler_heartbeats_actor FOREIGN KEY (initiated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_continuity_alerts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  alert_key CHAR(64) NOT NULL,
  environment_key VARCHAR(40) NOT NULL DEFAULT 'production',
  gap_id BIGINT UNSIGNED NULL,
  objective_id BIGINT UNSIGNED NULL,
  drill_id BIGINT UNSIGNED NULL,
  alert_type ENUM('recovery_gap','objective_breach','drill_due','drill_overdue','scheduler_missed','continuity_critical','evidence_failed','evidence_stale') NOT NULL,
  severity ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
  status ENUM('open','acknowledged','escalated','resolved','dismissed') NOT NULL DEFAULT 'open',
  title VARCHAR(180) NOT NULL,
  message VARCHAR(500) NOT NULL,
  owner_user_id BIGINT UNSIGNED NULL,
  notification_id BIGINT UNSIGNED NULL,
  due_at DATETIME NULL,
  acknowledged_by_user_id BIGINT UNSIGNED NULL,
  acknowledged_at DATETIME NULL,
  escalated_at DATETIME NULL,
  resolved_at DATETIME NULL,
  occurrence_count INT UNSIGNED NOT NULL DEFAULT 1,
  evidence_json JSON NULL,
  first_seen_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_continuity_alerts_public (public_id),
  UNIQUE KEY uq_admin_agent_continuity_alerts_key (alert_key),
  KEY idx_admin_agent_continuity_alerts_queue (status,severity,due_at,last_seen_at),
  KEY idx_admin_agent_continuity_alerts_owner (owner_user_id,status,severity),
  CONSTRAINT fk_admin_agent_continuity_alerts_gap FOREIGN KEY (gap_id) REFERENCES admin_agent_recovery_gaps(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_continuity_alerts_objective FOREIGN KEY (objective_id) REFERENCES admin_agent_recovery_objectives(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_continuity_alerts_drill FOREIGN KEY (drill_id) REFERENCES admin_agent_restore_drills(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_continuity_alerts_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_continuity_alerts_notification FOREIGN KEY (notification_id) REFERENCES admin_queue_notifications(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_continuity_alerts_ack FOREIGN KEY (acknowledged_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_drill_schedules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  schedule_key CHAR(64) NOT NULL,
  objective_id BIGINT UNSIGNED NOT NULL,
  environment_key VARCHAR(40) NOT NULL DEFAULT 'production',
  status ENUM('active','paused','completed') NOT NULL DEFAULT 'active',
  next_due_at DATETIME NOT NULL,
  reminder_days_json JSON NULL,
  last_reminder_at DATETIME NULL,
  owner_user_id BIGINT UNSIGNED NULL,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_drill_schedules_public (public_id),
  UNIQUE KEY uq_admin_agent_drill_schedules_key (schedule_key),
  UNIQUE KEY uq_admin_agent_drill_schedules_objective (objective_id,environment_key),
  KEY idx_admin_agent_drill_schedules_due (status,next_due_at),
  CONSTRAINT fk_admin_agent_drill_schedules_objective FOREIGN KEY (objective_id) REFERENCES admin_agent_recovery_objectives(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_agent_drill_schedules_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_drill_schedules_actor FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_attestations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  attestation_key CHAR(64) NOT NULL,
  subject_type ENUM('objective','backup_evidence','restore_drill','recovery_plan','readiness_export') NOT NULL,
  subject_public_id CHAR(26) NOT NULL,
  attestation_type ENUM('reviewed','approved','verified','accepted_risk') NOT NULL DEFAULT 'reviewed',
  statement_text VARCHAR(500) NOT NULL,
  attested_by_user_id BIGINT UNSIGNED NOT NULL,
  attested_at DATETIME NOT NULL,
  evidence_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_attestations_public (public_id),
  UNIQUE KEY uq_admin_agent_attestations_key (attestation_key),
  KEY idx_admin_agent_attestations_subject (subject_type,subject_public_id,attested_at),
  CONSTRAINT fk_admin_agent_attestations_actor FOREIGN KEY (attested_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_readiness_checks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  environment_key VARCHAR(40) NOT NULL DEFAULT 'production',
  check_key VARCHAR(100) NOT NULL,
  title VARCHAR(180) NOT NULL,
  category ENUM('configuration','monitoring','evidence','drills','alerting','governance','automation') NOT NULL,
  required_for_production TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('passed','warning','failed','not_configured') NOT NULL DEFAULT 'not_configured',
  details_text VARCHAR(500) NOT NULL,
  action_text VARCHAR(500) NULL,
  evidence_json JSON NULL,
  checked_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_readiness_checks_public (public_id),
  UNIQUE KEY uq_admin_agent_readiness_checks_key (environment_key,check_key),
  KEY idx_admin_agent_readiness_checks_status (environment_key,required_for_production,status),
  KEY idx_admin_agent_readiness_checks_category (environment_key,category,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_continuity_brief_deliveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  delivery_key CHAR(64) NOT NULL,
  environment_key VARCHAR(40) NOT NULL DEFAULT 'production',
  period_type ENUM('daily','weekly','manual') NOT NULL,
  period_key VARCHAR(32) NOT NULL,
  status ENUM('generated','delivered','failed') NOT NULL DEFAULT 'generated',
  notification_id BIGINT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  summary_text TEXT NOT NULL,
  payload_json JSON NULL,
  generated_by_user_id BIGINT UNSIGNED NULL,
  generated_at DATETIME NOT NULL,
  delivered_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_continuity_briefs_public (public_id),
  UNIQUE KEY uq_admin_agent_continuity_briefs_key (delivery_key),
  KEY idx_admin_agent_continuity_briefs_period (environment_key,period_type,period_key),
  CONSTRAINT fk_admin_agent_continuity_briefs_notification FOREIGN KEY (notification_id) REFERENCES admin_queue_notifications(id) ON DELETE SET NULL,
  CONSTRAINT fk_admin_agent_continuity_briefs_actor FOREIGN KEY (generated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_readiness_exports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  export_key CHAR(64) NOT NULL,
  environment_key VARCHAR(40) NOT NULL DEFAULT 'production',
  export_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  readiness_status ENUM('production_ready','attention_required','not_ready') NOT NULL,
  readiness_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  summary_json JSON NOT NULL,
  export_json MEDIUMTEXT NOT NULL,
  generated_by_user_id BIGINT UNSIGNED NULL,
  generated_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_readiness_exports_public (public_id),
  UNIQUE KEY uq_admin_agent_readiness_exports_key (export_key),
  KEY idx_admin_agent_readiness_exports_environment (environment_key,generated_at),
  CONSTRAINT fk_admin_agent_readiness_exports_actor FOREIGN KEY (generated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_agent_retention_previews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  preview_key CHAR(64) NOT NULL,
  environment_key VARCHAR(40) NOT NULL DEFAULT 'production',
  scorecards_eligible BIGINT UNSIGNED NOT NULL DEFAULT 0,
  events_eligible BIGINT UNSIGNED NOT NULL DEFAULT 0,
  resolved_alerts_eligible BIGINT UNSIGNED NOT NULL DEFAULT 0,
  policy_json JSON NOT NULL,
  generated_by_user_id BIGINT UNSIGNED NULL,
  generated_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_agent_retention_previews_public (public_id),
  UNIQUE KEY uq_admin_agent_retention_previews_key (preview_key),
  KEY idx_admin_agent_retention_previews_environment (environment_key,generated_at),
  CONSTRAINT fk_admin_agent_retention_previews_actor FOREIGN KEY (generated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE admin_queue_notifications
  MODIFY notification_type ENUM('assigned','overdue','due_soon','escalated','reopened','review_flag','digest','auto_routed','sla_breach','auto_escalated','workload_balance','playbook_applied','template_used','checklist_completed','case_comment','case_comment_pinned','timeline_viewed','automation_summary','automation_failed','quality_review','incident_declared','incident_updated','incident_resolved','incident_review_required','incident_review_completed','incident_review_followup_due','repeat_incident_detected','prevention_task_overdue','incident_trend_worsening','risk_forecast_high','forecasted_sla_breach','queue_overload_predicted','continuity_alert','recovery_drill_due','recovery_objective_breach','continuity_digest','scheduler_missed') NOT NULL;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.admin_agent.readiness','View Admin Agent production readiness','View final readiness checks, scheduler health, continuity alerts, brief delivery, attestations, exports, and retention previews.',NOW()),
('admin.admin_agent.setup','Manage Admin Agent production readiness','Configure final readiness settings, drill schedules, continuity alert lifecycle, and readiness checks.',NOW()),
('admin.admin_agent.export','Generate Admin Agent readiness exports','Generate database-only continuity and production-readiness JSON export packages.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.admin_agent.readiness','admin.admin_agent.setup','admin.admin_agent.export')
WHERE r.slug IN ('admin','super_admin');

INSERT IGNORE INTO admin_agent_phase6_settings
(public_id,environment_key,continuity_alerts_enabled,daily_brief_enabled,daily_brief_hour_utc,weekly_brief_enabled,weekly_brief_day_utc,weekly_brief_hour_utc,expected_runner_interval_minutes,scheduler_stale_after_minutes,scorecard_retention_days,event_retention_days,resolved_alert_retention_days)
VALUES
(LEFT(REPLACE(UUID(),'-',''),26),'production',1,1,15,1,1,15,5,15,365,180,365);

INSERT IGNORE INTO admin_agent_drill_schedules
(public_id,schedule_key,objective_id,environment_key,status,next_due_at,reminder_days_json,owner_user_id)
SELECT
  LEFT(REPLACE(UUID(),'-',''),26),
  SHA2(CONCAT('production|',o.public_id,'|drill_schedule'),256),
  o.id,
  o.environment_key,
  'active',
  DATE_ADD(COALESCE(MAX(d.completed_at),o.updated_at),INTERVAL o.drill_interval_days DAY),
  JSON_ARRAY(30,14,7,1),
  o.owner_user_id
FROM admin_agent_recovery_objectives o
LEFT JOIN admin_agent_restore_drills d ON d.objective_id=o.id AND d.status='passed'
WHERE o.status<>'retired'
GROUP BY o.id,o.public_id,o.environment_key,o.drill_interval_days,o.owner_user_id,o.updated_at;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('20260719_main_admin_agent_phase6','Main Admin Agent final readiness, scheduler health, continuity escalation, drill scheduling, attestations, briefs, exports, and retention previews.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);