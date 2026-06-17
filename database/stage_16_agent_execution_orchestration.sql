CREATE TABLE IF NOT EXISTS agent_strategies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  agent_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  objective VARCHAR(500) NOT NULL,
  status ENUM('draft','active','paused','retired') NOT NULL DEFAULT 'draft',
  trigger_type ENUM('manual','demand_signal','schedule','event') NOT NULL DEFAULT 'manual',
  trigger_config_json JSON NOT NULL,
  policy_json JSON NOT NULL,
  action_catalog_json JSON NOT NULL,
  max_actions_per_run SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  requires_approval TINYINT(1) NOT NULL DEFAULT 1,
  version_no INT UNSIGNED NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agent_strategies_public_id (public_id),
  KEY idx_agent_strategies_owner_status (owner_user_id,status,updated_at),
  KEY idx_agent_strategies_agent_status (agent_id,status),
  CONSTRAINT fk_agent_strategies_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE RESTRICT,
  CONSTRAINT fk_agent_strategies_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_agent_strategies_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_workflow_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  strategy_id BIGINT UNSIGNED NOT NULL,
  agent_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  trigger_type VARCHAR(80) NOT NULL,
  trigger_reference VARCHAR(190) NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  status ENUM('queued','planning','approval_pending','approved','executing','completed','partially_completed','failed','canceled') NOT NULL DEFAULT 'queued',
  input_json JSON NOT NULL,
  plan_json JSON NULL,
  result_json JSON NULL,
  failure_message VARCHAR(1000) NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_at DATETIME NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agent_runs_public_id (public_id),
  UNIQUE KEY uq_agent_runs_idempotency (owner_user_id,idempotency_key),
  KEY idx_agent_runs_queue (status,requested_at),
  KEY idx_agent_runs_owner_status (owner_user_id,status,updated_at),
  CONSTRAINT fk_agent_runs_strategy FOREIGN KEY (strategy_id) REFERENCES agent_strategies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_agent_runs_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE RESTRICT,
  CONSTRAINT fk_agent_runs_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_workflow_actions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  run_id BIGINT UNSIGNED NOT NULL,
  sequence_no SMALLINT UNSIGNED NOT NULL,
  action_type ENUM('acknowledge_demand_signal','resolve_demand_signal','pause_distribution_program','resume_distribution_program','create_operational_alert') NOT NULL,
  target_type ENUM('demand_signal','distribution_program','user') NOT NULL,
  target_reference VARCHAR(190) NOT NULL,
  status ENUM('proposed','approval_pending','approved','executing','completed','failed','rejected','canceled','skipped') NOT NULL DEFAULT 'proposed',
  risk_level ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  requires_approval TINYINT(1) NOT NULL DEFAULT 1,
  idempotency_key VARCHAR(190) NOT NULL,
  request_json JSON NOT NULL,
  result_json JSON NULL,
  failure_message VARCHAR(1000) NULL,
  approved_by_user_id BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agent_actions_public_id (public_id),
  UNIQUE KEY uq_agent_actions_run_sequence (run_id,sequence_no),
  UNIQUE KEY uq_agent_actions_idempotency (idempotency_key),
  KEY idx_agent_actions_queue (status,created_at),
  CONSTRAINT fk_agent_actions_run FOREIGN KEY (run_id) REFERENCES agent_workflow_runs(id) ON DELETE CASCADE,
  CONSTRAINT fk_agent_actions_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_approval_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  run_id BIGINT UNSIGNED NOT NULL,
  action_id BIGINT UNSIGNED NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','approved','rejected','expired','canceled') NOT NULL DEFAULT 'pending',
  requested_reason VARCHAR(1000) NOT NULL,
  decision_reason VARCHAR(1000) NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_at DATETIME NULL,
  decided_by_user_id BIGINT UNSIGNED NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agent_approvals_public_id (public_id),
  UNIQUE KEY uq_agent_approvals_action (action_id),
  KEY idx_agent_approvals_owner_status (owner_user_id,status,requested_at),
  CONSTRAINT fk_agent_approvals_run FOREIGN KEY (run_id) REFERENCES agent_workflow_runs(id) ON DELETE CASCADE,
  CONSTRAINT fk_agent_approvals_action FOREIGN KEY (action_id) REFERENCES agent_workflow_actions(id) ON DELETE CASCADE,
  CONSTRAINT fk_agent_approvals_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_agent_approvals_decider FOREIGN KEY (decided_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_execution_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  run_id BIGINT UNSIGNED NOT NULL,
  action_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(100) NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agent_execution_events_public_id (public_id),
  KEY idx_agent_execution_events_run (run_id,created_at,id),
  CONSTRAINT fk_agent_execution_events_run FOREIGN KEY (run_id) REFERENCES agent_workflow_runs(id) ON DELETE CASCADE,
  CONSTRAINT fk_agent_execution_events_action FOREIGN KEY (action_id) REFERENCES agent_workflow_actions(id) ON DELETE CASCADE,
  CONSTRAINT fk_agent_execution_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('agent.strategies.manage','Manage agent strategies','Create and manage owned agent execution strategies.',NOW()),
('agent.workflows.run','Run agent workflows','Create idempotent agent workflow runs and proposed actions.',NOW()),
('agent.approvals.decide','Decide agent approvals','Approve or reject owned agent workflow actions.',NOW()),
('agent.executions.process','Process agent executions','Execute approved agent actions through canonical authorities.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN ('agent.strategies.manage','agent.workflows.run','agent.approvals.decide')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='agent.executions.process'
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_16_agent_execution_orchestration','Agent strategies, workflow runs, approval queues, canonical action adapters, and execution audit events.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
