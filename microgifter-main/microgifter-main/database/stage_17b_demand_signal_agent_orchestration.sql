-- Stage 17B: durable Stage 15 demand signal orchestration into Stage 16 workflows and Stage 17 swarms.

CREATE TABLE IF NOT EXISTS demand_signal_orchestrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  demand_signal_id BIGINT UNSIGNED NOT NULL,
  strategy_id BIGINT UNSIGNED NULL,
  strategy_version INT UNSIGNED NULL,
  team_id BIGINT UNSIGNED NULL,
  orchestration_type ENUM('workflow','swarm','alert_only') NOT NULL,
  workflow_run_id BIGINT UNSIGNED NULL,
  swarm_run_id BIGINT UNSIGNED NULL,
  status ENUM('claimed','awaiting_approval','running','completed','failed','review_required') NOT NULL DEFAULT 'claimed',
  recommendation_action VARCHAR(100) NULL,
  dispatch_key CHAR(64) NOT NULL,
  input_fingerprint CHAR(64) NOT NULL,
  attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  last_error VARCHAR(1000) NULL,
  claimed_at DATETIME NOT NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_demand_signal_orchestrations_public_id (public_id),
  UNIQUE KEY uq_demand_signal_orchestrations_signal (demand_signal_id),
  UNIQUE KEY uq_demand_signal_orchestrations_dispatch (dispatch_key),
  KEY idx_demand_signal_orchestrations_status (status,updated_at,id),
  KEY idx_demand_signal_orchestrations_strategy (strategy_id,status,updated_at),
  KEY idx_demand_signal_orchestrations_team (team_id,status,updated_at),
  CONSTRAINT fk_demand_signal_orchestrations_signal FOREIGN KEY (demand_signal_id) REFERENCES demand_agent_signals(id) ON DELETE CASCADE,
  CONSTRAINT fk_demand_signal_orchestrations_strategy FOREIGN KEY (strategy_id) REFERENCES agent_strategies(id) ON DELETE SET NULL,
  CONSTRAINT fk_demand_signal_orchestrations_team FOREIGN KEY (team_id) REFERENCES agent_teams(id) ON DELETE SET NULL,
  CONSTRAINT fk_demand_signal_orchestrations_workflow FOREIGN KEY (workflow_run_id) REFERENCES agent_workflow_runs(id) ON DELETE SET NULL,
  CONSTRAINT fk_demand_signal_orchestrations_swarm FOREIGN KEY (swarm_run_id) REFERENCES agent_swarm_runs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demand_signal_orchestration_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  orchestration_id BIGINT UNSIGNED NOT NULL,
  event_key VARCHAR(120) NOT NULL,
  event_type VARCHAR(120) NOT NULL,
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_demand_signal_orchestration_events_public_id (public_id),
  UNIQUE KEY uq_demand_signal_orchestration_events_key (orchestration_id,event_key),
  KEY idx_demand_signal_orchestration_events_run (orchestration_id,created_at,id),
  CONSTRAINT fk_demand_signal_orchestration_events_run FOREIGN KEY (orchestration_id) REFERENCES demand_signal_orchestrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  'stage_17b_demand_signal_agent_orchestration',
  'Durably route Stage 15 demand signals into canonical Stage 16 workflows and Stage 17 swarms with replay-safe reconciliation.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);
