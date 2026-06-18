-- Stage 18C2: backfill initial attempts and register safe event retention.

INSERT IGNORE INTO demand_signal_orchestration_attempts
(public_id,orchestration_id,attempt_no,strategy_id,strategy_version,team_id,orchestration_type,workflow_run_id,swarm_run_id,status,dispatch_key,input_fingerprint,requested_reason,last_error,started_at,completed_at,created_at,updated_at)
SELECT UUID(),o.id,1,o.strategy_id,o.strategy_version,o.team_id,o.orchestration_type,o.workflow_run_id,o.swarm_run_id,o.status,o.dispatch_key,o.input_fingerprint,'Initial demand orchestration attempt.',o.last_error,o.started_at,o.completed_at,o.created_at,o.updated_at
FROM demand_signal_orchestrations o
LEFT JOIN demand_signal_orchestration_attempts a ON a.orchestration_id=o.id AND a.attempt_no=1
WHERE a.id IS NULL;

INSERT IGNORE INTO retention_policies
(policy_key,table_name,timestamp_column,retention_days,batch_size,action_type,status,policy_json)
VALUES ('demand_orchestration_events_365d','demand_signal_orchestration_events','created_at',365,1000,'delete','active',JSON_OBJECT('completed_orchestrations_only',TRUE,'preserve_latest_event',TRUE));

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_18c2_demand_orchestration_retention','Backfill orchestration attempt history and register completed-only event retention.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
