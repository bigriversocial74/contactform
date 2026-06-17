CREATE TABLE IF NOT EXISTS agent_teams (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  objective VARCHAR(500) NOT NULL,
  status ENUM('draft','active','paused','retired') NOT NULL DEFAULT 'draft',
  coordination_mode ENUM('manager_worker','peer_review','pipeline','consensus') NOT NULL DEFAULT 'manager_worker',
  conflict_policy ENUM('owner_decides','lead_agent','majority','highest_confidence') NOT NULL DEFAULT 'owner_decides',
  max_parallel_tasks SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  default_budget_units BIGINT UNSIGNED NOT NULL DEFAULT 100000,
  metadata_json JSON NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agent_teams_public_id (public_id),
  KEY idx_agent_teams_owner_status (owner_user_id,status,updated_at),
  CONSTRAINT fk_agent_teams_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_agent_teams_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_team_members (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  team_id BIGINT UNSIGNED NOT NULL,
  agent_id BIGINT UNSIGNED NOT NULL,
  role_key VARCHAR(100) NOT NULL,
  role_type ENUM('lead','planner','researcher','analyst','executor','reviewer','specialist') NOT NULL DEFAULT 'specialist',
  priority SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  capabilities_json JSON NOT NULL,
  routing_profile_json JSON NOT NULL,
  max_concurrent_tasks SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('active','paused','removed') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agent_team_member (team_id,agent_id),
  UNIQUE KEY uq_agent_team_role (team_id,role_key),
  KEY idx_agent_team_members_status (team_id,status,priority),
  CONSTRAINT fk_agent_team_members_team FOREIGN KEY (team_id) REFERENCES agent_teams(id) ON DELETE CASCADE,
  CONSTRAINT fk_agent_team_members_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_provider_routes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  team_id BIGINT UNSIGNED NOT NULL,
  route_key VARCHAR(100) NOT NULL,
  provider_key VARCHAR(100) NOT NULL,
  model_key VARCHAR(190) NOT NULL,
  capability_key VARCHAR(100) NOT NULL,
  priority SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  max_input_units BIGINT UNSIGNED NULL,
  max_output_units BIGINT UNSIGNED NULL,
  estimated_unit_cost_micros BIGINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('active','paused','retired') NOT NULL DEFAULT 'active',
  config_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agent_provider_route (team_id,route_key),
  KEY idx_agent_provider_routes_capability (team_id,capability_key,status,priority),
  CONSTRAINT fk_agent_provider_routes_team FOREIGN KEY (team_id) REFERENCES agent_teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_swarm_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  team_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  objective VARCHAR(1000) NOT NULL,
  status ENUM('queued','planning','running','blocked','approval_pending','completed','partially_completed','failed','canceled') NOT NULL DEFAULT 'queued',
  budget_units BIGINT UNSIGNED NOT NULL,
  reserved_units BIGINT UNSIGNED NOT NULL DEFAULT 0,
  consumed_units BIGINT UNSIGNED NOT NULL DEFAULT 0,
  input_json JSON NOT NULL,
  result_json JSON NULL,
  failure_message VARCHAR(1000) NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agent_swarm_runs_public_id (public_id),
  UNIQUE KEY uq_agent_swarm_runs_idempotency (owner_user_id,idempotency_key),
  KEY idx_agent_swarm_runs_queue (status,created_at),
  KEY idx_agent_swarm_runs_owner (owner_user_id,status,updated_at),
  CONSTRAINT fk_agent_swarm_runs_team FOREIGN KEY (team_id) REFERENCES agent_teams(id) ON DELETE RESTRICT,
  CONSTRAINT fk_agent_swarm_runs_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT chk_agent_swarm_budget CHECK (consumed_units <= budget_units AND reserved_units <= budget_units)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_swarm_tasks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  swarm_run_id BIGINT UNSIGNED NOT NULL,
  team_member_id BIGINT UNSIGNED NULL,
  strategy_id BIGINT UNSIGNED NULL,
  workflow_run_id BIGINT UNSIGNED NULL,
  task_key VARCHAR(100) NOT NULL,
  task_type VARCHAR(100) NOT NULL,
  capability_key VARCHAR(100) NOT NULL,
  objective VARCHAR(1000) NOT NULL,
  status ENUM('pending','ready','routed','running','review_pending','completed','failed','blocked','canceled') NOT NULL DEFAULT 'pending',
  priority SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  requires_review TINYINT(1) NOT NULL DEFAULT 0,
  estimated_units BIGINT UNSIGNED NOT NULL DEFAULT 0,
  reserved_units BIGINT UNSIGNED NOT NULL DEFAULT 0,
  consumed_units BIGINT UNSIGNED NOT NULL DEFAULT 0,
  route_json JSON NULL,
  input_json JSON NOT NULL,
  output_json JSON NULL,
  failure_message VARCHAR(1000) NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agent_swarm_tasks_public_id (public_id),
  UNIQUE KEY uq_agent_swarm_tasks_key (swarm_run_id,task_key),
  KEY idx_agent_swarm_tasks_queue (status,priority,created_at),
  KEY idx_agent_swarm_tasks_member (team_member_id,status),
  CONSTRAINT fk_agent_swarm_tasks_run FOREIGN KEY (swarm_run_id) REFERENCES agent_swarm_runs(id) ON DELETE CASCADE,
  CONSTRAINT fk_agent_swarm_tasks_member FOREIGN KEY (team_member_id) REFERENCES agent_team_members(id) ON DELETE SET NULL,
  CONSTRAINT fk_agent_swarm_tasks_strategy FOREIGN KEY (strategy_id) REFERENCES agent_strategies(id) ON DELETE SET NULL,
  CONSTRAINT fk_agent_swarm_tasks_workflow FOREIGN KEY (workflow_run_id) REFERENCES agent_workflow_runs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_swarm_task_dependencies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  swarm_run_id BIGINT UNSIGNED NOT NULL,
  task_id BIGINT UNSIGNED NOT NULL,
  depends_on_task_id BIGINT UNSIGNED NOT NULL,
  dependency_type ENUM('completion','success','review') NOT NULL DEFAULT 'success',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agent_swarm_dependency (task_id,depends_on_task_id),
  KEY idx_agent_swarm_dependencies_run (swarm_run_id,task_id),
  CONSTRAINT fk_agent_swarm_dependencies_run FOREIGN KEY (swarm_run_id) REFERENCES agent_swarm_runs(id) ON DELETE CASCADE,
  CONSTRAINT fk_agent_swarm_dependencies_task FOREIGN KEY (task_id) REFERENCES agent_swarm_tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_agent_swarm_dependencies_parent FOREIGN KEY (depends_on_task_id) REFERENCES agent_swarm_tasks(id) ON DELETE CASCADE,
  CONSTRAINT chk_agent_swarm_dependency_not_self CHECK (task_id <> depends_on_task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_swarm_conflicts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  swarm_run_id BIGINT UNSIGNED NOT NULL,
  task_id BIGINT UNSIGNED NULL,
  conflict_type ENUM('routing','result','budget','dependency','policy') NOT NULL,
  status ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
  summary VARCHAR(1000) NOT NULL,
  candidates_json JSON NOT NULL,
  resolution_json JSON NULL,
  resolution_method VARCHAR(100) NULL,
  resolved_by_user_id BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agent_swarm_conflicts_public_id (public_id),
  KEY idx_agent_swarm_conflicts_open (swarm_run_id,status,created_at),
  CONSTRAINT fk_agent_swarm_conflicts_run FOREIGN KEY (swarm_run_id) REFERENCES agent_swarm_runs(id) ON DELETE CASCADE,
  CONSTRAINT fk_agent_swarm_conflicts_task FOREIGN KEY (task_id) REFERENCES agent_swarm_tasks(id) ON DELETE SET NULL,
  CONSTRAINT fk_agent_swarm_conflicts_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_swarm_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  swarm_run_id BIGINT UNSIGNED NOT NULL,
  task_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(120) NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agent_swarm_events_public_id (public_id),
  KEY idx_agent_swarm_events_run (swarm_run_id,created_at,id),
  CONSTRAINT fk_agent_swarm_events_run FOREIGN KEY (swarm_run_id) REFERENCES agent_swarm_runs(id) ON DELETE CASCADE,
  CONSTRAINT fk_agent_swarm_events_task FOREIGN KEY (task_id) REFERENCES agent_swarm_tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_agent_swarm_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('agent.teams.manage','Manage agent teams','Create and manage owned multi-agent teams.',NOW()),
('agent.swarms.run','Run agent swarms','Create and coordinate owned multi-agent swarm runs.',NOW()),
('agent.swarms.resolve','Resolve swarm conflicts','Resolve owned swarm conflicts and arbitration decisions.',NOW()),
('agent.swarms.observe','View swarm observability','View swarm task, budget, routing, and event telemetry.',NOW()),
('agent.swarms.process','Process swarm tasks','Route ready swarm tasks into Stage 16 workflows.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug IN ('agent.teams.manage','agent.swarms.run','agent.swarms.resolve','agent.swarms.observe')
WHERE r.slug IN ('merchant','admin','super_admin');

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.slug='agent.swarms.process'
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('stage_17_multi_agent_swarms','Multi-agent teams, provider routing, budgets, dependency graphs, conflict arbitration, and swarm observability.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
