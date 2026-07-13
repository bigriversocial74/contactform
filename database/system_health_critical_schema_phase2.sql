-- Microgifter System Health Critical Schema Remediation — Phase 2
-- Repairs the Admin Ops notification type registry detected by System Health.
-- Loyalty Quest integrity and merchant-location foundations remain owned by their
-- existing canonical migrations and are intentionally not duplicated here.

SET @mg_phase2_enum = "enum('assigned','overdue','due_soon','escalated','reopened','review_flag','digest','auto_routed','sla_breach','auto_escalated','workload_balance','playbook_applied','template_used','checklist_completed','case_comment','case_comment_pinned','timeline_viewed','automation_summary','automation_failed','quality_review','incident_declared','incident_updated','incident_resolved','incident_review_required','incident_review_completed','incident_review_followup_due','repeat_incident_detected','prevention_task_overdue','incident_trend_worsening','risk_forecast_high','forecasted_sla_breach','queue_overload_predicted')";

SET @mg_phase2_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_queue_notifications'),
  CONCAT('ALTER TABLE admin_queue_notifications MODIFY notification_type ', @mg_phase2_enum, " NOT NULL DEFAULT 'digest'"),
  'SELECT 1'
);
PREPARE mg_phase2_stmt FROM @mg_phase2_sql;
EXECUTE mg_phase2_stmt;
DEALLOCATE PREPARE mg_phase2_stmt;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('system_health_critical_schema_phase2','System Health Phase 2 Admin Ops notification enum remediation.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
