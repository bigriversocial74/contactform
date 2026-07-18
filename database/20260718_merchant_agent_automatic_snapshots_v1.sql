-- Merchant Agent Automatic Latest Snapshot v1
-- Database-grounded snapshots generated without an external AI request.

CREATE TABLE IF NOT EXISTS merchant_agent_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  snapshot_key VARCHAR(80) NOT NULL DEFAULT 'latest_merchant_snapshot',
  window_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  status ENUM('complete','failed') NOT NULL DEFAULT 'complete',
  generated_by ENUM('workspace_load','scheduled_runner','manual_refresh') NOT NULL DEFAULT 'workspace_load',
  ai_enrichment_status ENUM('not_requested','skipped','complete','failed') NOT NULL DEFAULT 'not_requested',
  snapshot_json JSON NOT NULL,
  generated_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_agent_snapshot_public_id (public_id),
  KEY idx_merchant_agent_snapshot_latest (merchant_user_id,snapshot_key,status,generated_at),
  KEY idx_merchant_agent_snapshot_due (status,expires_at),
  CONSTRAINT fk_merchant_agent_snapshots_owner FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('20260718_merchant_agent_automatic_snapshots_v1','Automatic system-first Merchant Agent latest snapshots.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);