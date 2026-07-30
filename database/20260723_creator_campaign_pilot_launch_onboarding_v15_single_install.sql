-- Creator Campaign Phase 15 — Pilot Launch & Merchant Onboarding
-- Native merchant onboarding only. No MCP connection, grant, scope, definition, or execution authority is added.
-- Existing Creator Campaign products, campaigns, compensation, budgets, agreements, tracking, pilot controls, and audits remain canonical.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS creator_campaign_merchant_onboarding (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  pilot_id BIGINT UNSIGNED NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('invited','enrolled','in_progress','ready','active','completed') NOT NULL DEFAULT 'invited',
  current_step TINYINT UNSIGNED NOT NULL DEFAULT 1,
  primary_operator_user_id BIGINT UNSIGNED NULL,
  support_contact VARCHAR(255) NULL,
  pilot_goal VARCHAR(1000) NULL,
  expected_campaign_volume VARCHAR(120) NULL,
  intended_launch_date DATE NULL,
  business_defaults_json JSON NULL,
  product_selection_json JSON NULL,
  compensation_defaults_json JSON NULL,
  creator_preferences_json JSON NULL,
  operator_roles_json JSON NULL,
  first_campaign_id BIGINT UNSIGNED NULL,
  readiness_snapshot_json JSON NULL,
  enrolled_at DATETIME NULL,
  ready_at DATETIME NULL,
  activated_at DATETIME NULL,
  completed_at DATETIME NULL,
  last_smoke_test_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_onboarding_public (public_id),
  UNIQUE KEY uq_creator_campaign_onboarding_pilot (pilot_id),
  UNIQUE KEY uq_creator_campaign_onboarding_workspace_owner (workspace_id,owner_user_id),
  KEY idx_creator_campaign_onboarding_status (status,current_step,updated_at),
  KEY idx_creator_campaign_onboarding_campaign (first_campaign_id),
  CONSTRAINT fk_creator_campaign_onboarding_pilot FOREIGN KEY (pilot_id) REFERENCES creator_campaign_operator_pilots(id) ON DELETE CASCADE,
  CONSTRAINT fk_creator_campaign_onboarding_workspace FOREIGN KEY (workspace_id) REFERENCES merchant_workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_creator_campaign_onboarding_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_creator_campaign_onboarding_operator FOREIGN KEY (primary_operator_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_creator_campaign_onboarding_campaign FOREIGN KEY (first_campaign_id) REFERENCES creator_campaigns(id) ON DELETE SET NULL,
  CONSTRAINT chk_creator_campaign_onboarding_step CHECK (current_step BETWEEN 1 AND 9)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_onboarding_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  onboarding_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(120) NOT NULL,
  step_key VARCHAR(80) NULL,
  severity ENUM('info','low','medium','high','critical') NOT NULL DEFAULT 'info',
  note VARCHAR(2000) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_onboarding_event_public (public_id),
  KEY idx_creator_campaign_onboarding_event (onboarding_id,created_at,id),
  KEY idx_creator_campaign_onboarding_event_type (event_type,severity,created_at),
  CONSTRAINT fk_creator_campaign_onboarding_event_onboarding FOREIGN KEY (onboarding_id) REFERENCES creator_campaign_merchant_onboarding(id) ON DELETE CASCADE,
  CONSTRAINT fk_creator_campaign_onboarding_event_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_onboarding_receipts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  onboarding_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  receipt_type ENUM('readiness_smoke_test','launch_activation') NOT NULL DEFAULT 'readiness_smoke_test',
  status ENUM('passed','failed') NOT NULL,
  score TINYINT UNSIGNED NOT NULL,
  checks_json JSON NOT NULL,
  snapshot_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_creator_campaign_onboarding_receipt_public (public_id),
  UNIQUE KEY uq_creator_campaign_onboarding_receipt_snapshot (onboarding_id,receipt_type,snapshot_hash,status),
  KEY idx_creator_campaign_onboarding_receipt (onboarding_id,status,created_at,id),
  KEY idx_creator_campaign_onboarding_receipt_campaign (campaign_id,created_at,id),
  CONSTRAINT fk_creator_campaign_onboarding_receipt_onboarding FOREIGN KEY (onboarding_id) REFERENCES creator_campaign_merchant_onboarding(id) ON DELETE CASCADE,
  CONSTRAINT fk_creator_campaign_onboarding_receipt_campaign FOREIGN KEY (campaign_id) REFERENCES creator_campaigns(id) ON DELETE SET NULL,
  CONSTRAINT fk_creator_campaign_onboarding_receipt_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT chk_creator_campaign_onboarding_receipt_score CHECK (score <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
VALUES (
  '20260723_creator_campaign_pilot_launch_onboarding_v15_single_install',
  'Creator Campaign Phase 15 native merchant enrollment, reusable defaults, first-campaign launch readiness, and immutable smoke-test receipts.',
  NULL,
  NOW()
)
ON DUPLICATE KEY UPDATE description=VALUES(description);

COMMIT;
