-- Creator Campaign Production Pilot & Operator Experience v14
-- Adds operator-only pilot state, incident/recovery evidence, and accepted-review handoff records.
-- Existing Creator Campaign, MCP grant, definition, run, draft, approval, receipt, and security tables remain authoritative.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS creator_campaign_operator_pilots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('setup','ready','active','paused','completed','disabled') NOT NULL DEFAULT 'setup',
  emergency_disabled TINYINT(1) NOT NULL DEFAULT 0,
  emergency_reason VARCHAR(1000) NULL,
  support_contact VARCHAR(255) NULL,
  checklist_json JSON NULL,
  readiness_snapshot_json JSON NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  emergency_disabled_at DATETIME NULL,
  emergency_cleared_at DATETIME NULL,
  last_health_check_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_operator_pilot_public (public_id),
  UNIQUE KEY uq_creator_campaign_operator_pilot_workspace (workspace_id,owner_user_id),
  KEY idx_creator_campaign_operator_pilot_status (status,emergency_disabled,updated_at),
  CONSTRAINT fk_creator_campaign_operator_pilot_workspace FOREIGN KEY (workspace_id) REFERENCES merchant_workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_creator_campaign_operator_pilot_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_operator_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  pilot_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(120) NOT NULL,
  severity ENUM('info','low','medium','high','critical') NOT NULL DEFAULT 'info',
  subject_type VARCHAR(80) NULL,
  subject_public_id VARCHAR(190) NULL,
  note VARCHAR(2000) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_operator_event_public (public_id),
  KEY idx_creator_campaign_operator_event_pilot (pilot_id,created_at),
  KEY idx_creator_campaign_operator_event_type (event_type,severity,created_at),
  CONSTRAINT fk_creator_campaign_operator_event_pilot FOREIGN KEY (pilot_id) REFERENCES creator_campaign_operator_pilots(id) ON DELETE CASCADE,
  CONSTRAINT fk_creator_campaign_operator_event_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_operator_handoffs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  pilot_id BIGINT UNSIGNED NOT NULL,
  source_draft_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('prepared','request_created','cancelled','failed') NOT NULL DEFAULT 'prepared',
  tool_name VARCHAR(190) NOT NULL,
  grant_public_id CHAR(36) NOT NULL,
  input_json JSON NOT NULL,
  input_fingerprint CHAR(64) NOT NULL,
  requested_reason VARCHAR(1000) NOT NULL,
  action_public_id CHAR(36) NULL,
  error_code VARCHAR(120) NULL,
  error_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_operator_handoff_public (public_id),
  UNIQUE KEY uq_creator_campaign_operator_handoff_source (source_draft_id,input_fingerprint),
  KEY idx_creator_campaign_operator_handoff_pilot (pilot_id,status,created_at),
  KEY idx_creator_campaign_operator_handoff_action (action_public_id),
  CONSTRAINT fk_creator_campaign_operator_handoff_pilot FOREIGN KEY (pilot_id) REFERENCES creator_campaign_operator_pilots(id) ON DELETE CASCADE,
  CONSTRAINT fk_creator_campaign_operator_handoff_draft FOREIGN KEY (source_draft_id) REFERENCES mcp_agent_drafts(id) ON DELETE CASCADE,
  CONSTRAINT fk_creator_campaign_operator_handoff_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  '20260722_creator_campaign_production_pilot_v14_single_install',
  'Creator Campaign Phase 14 production pilot state, operator events, emergency controls, and accepted-review action handoffs.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
