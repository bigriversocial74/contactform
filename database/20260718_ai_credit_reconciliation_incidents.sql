-- Automated AI Credit Reconciliation and Incident Queue
-- Adds scheduled reconciliation runs, idempotent accounting incidents, action history,
-- and dedicated admin permissions for review, resolution, dismissal, and controlled debit retry.

CREATE TABLE IF NOT EXISTS ai_credit_reconciliation_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  trigger_source VARCHAR(40) NOT NULL DEFAULT 'scheduled',
  provider_key VARCHAR(80) NOT NULL DEFAULT 'anthropic',
  status ENUM('running','completed','failed') NOT NULL DEFAULT 'running',
  window_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  merchants_scanned INT UNSIGNED NOT NULL DEFAULT 0,
  provider_events_scanned INT UNSIGNED NOT NULL DEFAULT 0,
  ledger_entries_scanned INT UNSIGNED NOT NULL DEFAULT 0,
  incidents_created INT UNSIGNED NOT NULL DEFAULT 0,
  incidents_updated INT UNSIGNED NOT NULL DEFAULT 0,
  incidents_auto_resolved INT UNSIGNED NOT NULL DEFAULT 0,
  token_difference_total BIGINT NOT NULL DEFAULT 0,
  initiated_by_user_id BIGINT UNSIGNED NULL,
  failure_message VARCHAR(1000) NULL,
  metadata_json JSON NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ai_credit_reconciliation_runs_public (public_id),
  KEY idx_ai_credit_reconciliation_runs_status_started (status,started_at),
  KEY idx_ai_credit_reconciliation_runs_provider_started (provider_key,started_at),
  CONSTRAINT fk_ai_credit_reconciliation_runs_actor FOREIGN KEY (initiated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_credit_reconciliation_incidents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  incident_key CHAR(64) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  provider_key VARCHAR(80) NOT NULL DEFAULT 'anthropic',
  incident_type VARCHAR(80) NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  status ENUM('open','under_review','resolved','dismissed') NOT NULL DEFAULT 'open',
  source_type VARCHAR(80) NULL,
  source_reference VARCHAR(190) NULL,
  provider_usage_event_id BIGINT UNSIGNED NULL,
  ledger_entry_id BIGINT UNSIGNED NULL,
  model_id BIGINT UNSIGNED NULL,
  provider_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
  debited_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
  token_difference BIGINT NOT NULL DEFAULT 0,
  evidence_json JSON NULL,
  first_detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  occurrence_count INT UNSIGNED NOT NULL DEFAULT 1,
  assigned_admin_user_id BIGINT UNSIGNED NULL,
  resolution_note VARCHAR(1000) NULL,
  retry_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_retry_at DATETIME NULL,
  resolved_by_user_id BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  dismissed_at DATETIME NULL,
  last_run_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ai_credit_reconciliation_incidents_public (public_id),
  UNIQUE KEY uq_ai_credit_reconciliation_incidents_key (incident_key),
  KEY idx_ai_credit_reconciliation_incidents_queue (status,severity,last_detected_at),
  KEY idx_ai_credit_reconciliation_incidents_user (user_id,status,last_detected_at),
  KEY idx_ai_credit_reconciliation_incidents_type (incident_type,status,last_detected_at),
  KEY idx_ai_credit_reconciliation_incidents_reference (provider_key,source_reference),
  KEY idx_ai_credit_reconciliation_incidents_assignment (assigned_admin_user_id,status),
  CONSTRAINT fk_ai_credit_reconciliation_incidents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_credit_reconciliation_incidents_usage FOREIGN KEY (provider_usage_event_id) REFERENCES ai_usage_events(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_credit_reconciliation_incidents_ledger FOREIGN KEY (ledger_entry_id) REFERENCES ai_credit_ledger(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_credit_reconciliation_incidents_model FOREIGN KEY (model_id) REFERENCES ai_models(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_credit_reconciliation_incidents_assignee FOREIGN KEY (assigned_admin_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_credit_reconciliation_incidents_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_credit_reconciliation_incidents_run FOREIGN KEY (last_run_id) REFERENCES ai_credit_reconciliation_runs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_credit_reconciliation_actions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  incident_id BIGINT UNSIGNED NOT NULL,
  action_type VARCHAR(60) NOT NULL,
  admin_user_id BIGINT UNSIGNED NULL,
  note VARCHAR(1000) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ai_credit_reconciliation_actions_public (public_id),
  KEY idx_ai_credit_reconciliation_actions_incident (incident_id,created_at),
  KEY idx_ai_credit_reconciliation_actions_admin (admin_user_id,created_at),
  CONSTRAINT fk_ai_credit_reconciliation_actions_incident FOREIGN KEY (incident_id) REFERENCES ai_credit_reconciliation_incidents(id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_credit_reconciliation_actions_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES
('admin.ai_credit_incidents.view','View AI credit accounting incidents','View automated AI credit reconciliation results, incident evidence, merchant balances, and accounting history.',NOW()),
('admin.ai_credit_incidents.manage','Manage AI credit accounting incidents','Assign, review, resolve, dismiss, reopen, and manually run AI credit reconciliation incidents.',NOW()),
('admin.ai_credit_incidents.retry','Retry AI credit accounting debit','Perform controlled idempotent AI credit debit recovery using the original provider response reference.',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.slug IN ('admin.ai_credit_incidents.view','admin.ai_credit_incidents.manage','admin.ai_credit_incidents.retry')
WHERE r.slug IN ('admin','super_admin');

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES ('20260718_ai_credit_reconciliation_incidents','Automated AI credit reconciliation runs, idempotent accounting incident queue, action history, and controlled debit retry permissions.',NULL,NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);