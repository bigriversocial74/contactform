-- Microgifter MCP approval-gated drafts Phase 3A.
-- Import after database/20260720_mcp_external_agent_authorization_phase2a_v1.sql.
-- Draft records are review-only. This migration creates no publish, send, purchase, schedule, or worker execution path.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS mcp_agent_drafts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  connection_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  workspace_type VARCHAR(40) NULL,
  workspace_id BIGINT UNSIGNED NULL,
  draft_type ENUM('gift','campaign','reward','message') NOT NULL,
  status ENUM('pending_review','approved','rejected','canceled','expired') NOT NULL DEFAULT 'pending_review',
  title VARCHAR(190) NOT NULL,
  summary VARCHAR(500) NOT NULL,
  payload_json JSON NOT NULL,
  payload_fingerprint CHAR(64) NOT NULL,
  risk_level ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  idempotency_key VARCHAR(190) NOT NULL,
  source_request_id CHAR(36) NOT NULL,
  requested_reason VARCHAR(1000) NOT NULL,
  approval_expires_at DATETIME NULL,
  decided_by_user_id BIGINT UNSIGNED NULL,
  decision_reason VARCHAR(1000) NULL,
  decided_at DATETIME NULL,
  canceled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mcp_agent_drafts_public_id (public_id),
  UNIQUE KEY uq_mcp_agent_drafts_connection_idempotency (connection_id,idempotency_key),
  UNIQUE KEY uq_mcp_agent_drafts_source_request (source_request_id),
  KEY idx_mcp_agent_drafts_owner_status (owner_user_id,status,updated_at,id),
  KEY idx_mcp_agent_drafts_connection_status (connection_id,status,updated_at,id),
  KEY idx_mcp_agent_drafts_workspace (workspace_type,workspace_id,status,updated_at),
  KEY idx_mcp_agent_drafts_expiry (status,approval_expires_at),
  CONSTRAINT fk_mcp_agent_drafts_connection FOREIGN KEY (connection_id) REFERENCES mcp_connections(id) ON DELETE CASCADE,
  CONSTRAINT fk_mcp_agent_drafts_client FOREIGN KEY (client_id) REFERENCES mcp_clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_mcp_agent_drafts_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_mcp_agent_drafts_decider FOREIGN KEY (decided_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mcp_agent_draft_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  draft_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('created','duplicate_returned','approved','rejected','canceled','expired') NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  connection_id BIGINT UNSIGNED NULL,
  evidence_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mcp_agent_draft_events_public_id (public_id),
  KEY idx_mcp_agent_draft_events_draft (draft_id,created_at,id),
  KEY idx_mcp_agent_draft_events_actor (actor_user_id,created_at),
  KEY idx_mcp_agent_draft_events_connection (connection_id,created_at),
  CONSTRAINT fk_mcp_agent_draft_events_draft FOREIGN KEY (draft_id) REFERENCES mcp_agent_drafts(id) ON DELETE CASCADE,
  CONSTRAINT fk_mcp_agent_draft_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_mcp_agent_draft_events_connection FOREIGN KEY (connection_id) REFERENCES mcp_connections(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO mcp_scope_catalog (scope_key,display_name,description,operation_class,active,grantable)
VALUES
  ('gift:draft','Create gift drafts','Create reviewable gift drafts without purchase, issuance, delivery, or payment.','draft',1,1),
  ('campaign:draft','Create campaign drafts','Create reviewable merchant campaign drafts without publication or scheduling.','draft',1,1),
  ('reward:draft','Create reward drafts','Create reviewable merchant reward drafts without activation or fulfillment.','draft',1,1),
  ('message:draft','Create message drafts','Create reviewable merchant message drafts without sending or scheduling.','draft',1,1)
ON DUPLICATE KEY UPDATE
  display_name=VALUES(display_name),
  description=VALUES(description),
  operation_class=VALUES(operation_class),
  active=VALUES(active),
  grantable=VALUES(grantable);

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  '20260720_mcp_approval_gated_drafts_phase3a_v1',
  'MCP review-only gift, campaign, reward, and message drafts with owner decisions, idempotency, expiry, and audit events.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
