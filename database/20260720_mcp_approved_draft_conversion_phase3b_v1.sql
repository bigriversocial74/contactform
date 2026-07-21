-- Microgifter MCP approved-draft conversion Phase 3B.
-- Import after database/20260720_mcp_approval_gated_drafts_phase3a_v1.sql.
-- Conversion is owner-triggered and creates inactive native drafts only.
-- No publish, send, purchase, schedule, payment, fulfillment, or worker queue path is created.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS mcp_agent_draft_conversions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  draft_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  conversion_type ENUM('gift_draft','campaign_draft','reward_template_draft','message_draft') NOT NULL,
  status ENUM('prepared','created','opened','canceled') NOT NULL DEFAULT 'prepared',
  native_public_id VARCHAR(190) NULL,
  native_url VARCHAR(700) NULL,
  snapshot_json JSON NOT NULL,
  prepared_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  native_created_at DATETIME NULL,
  opened_at DATETIME NULL,
  canceled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mcp_agent_draft_conversions_public_id (public_id),
  UNIQUE KEY uq_mcp_agent_draft_conversions_draft (draft_id),
  KEY idx_mcp_agent_draft_conversions_owner_status (owner_user_id,status,updated_at,id),
  KEY idx_mcp_agent_draft_conversions_native (conversion_type,native_public_id),
  CONSTRAINT fk_mcp_agent_draft_conversions_draft FOREIGN KEY (draft_id) REFERENCES mcp_agent_drafts(id) ON DELETE CASCADE,
  CONSTRAINT fk_mcp_agent_draft_conversions_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mcp_agent_draft_conversion_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  conversion_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('prepared','duplicate_returned','native_created','opened','canceled') NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  evidence_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mcp_agent_draft_conversion_events_public_id (public_id),
  KEY idx_mcp_agent_draft_conversion_events_conversion (conversion_id,created_at,id),
  KEY idx_mcp_agent_draft_conversion_events_actor (actor_user_id,created_at),
  CONSTRAINT fk_mcp_agent_draft_conversion_events_conversion FOREIGN KEY (conversion_id) REFERENCES mcp_agent_draft_conversions(id) ON DELETE CASCADE,
  CONSTRAINT fk_mcp_agent_draft_conversion_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  '20260720_mcp_approved_draft_conversion_phase3b_v1',
  'Owner-triggered conversion of approved MCP drafts into inactive native Microgifter drafts with immutable handoff evidence.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
