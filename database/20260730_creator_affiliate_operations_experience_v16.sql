-- Creator Affiliate Operations & Experience v16
-- Scope: merchant payout policy, persistent reconciliation cases, and operator workflow state.
-- Provider transfers, banking credentials, tax filing, and autonomous payout execution remain out of scope.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS creator_campaign_payout_policies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  status ENUM('active','paused') NOT NULL DEFAULT 'active',
  cadence ENUM('manual','weekly','biweekly','monthly') NOT NULL DEFAULT 'manual',
  payout_weekday TINYINT UNSIGNED NULL,
  payout_day_of_month TINYINT UNSIGNED NULL,
  hold_days SMALLINT UNSIGNED NOT NULL DEFAULT 7,
  minimum_payout_minor BIGINT UNSIGNED NOT NULL DEFAULT 2500,
  method_label VARCHAR(120) NULL,
  payment_instructions VARCHAR(2000) NULL,
  dispute_window_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  manual_approval_required TINYINT(1) NOT NULL DEFAULT 1,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_payout_policy_public (public_id),
  UNIQUE KEY uq_cc_payout_policy_workspace_currency (workspace_id,currency),
  KEY idx_cc_payout_policy_status (workspace_id,status,updated_at,id),
  CONSTRAINT fk_cc_payout_policy_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cc_payout_policy_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT chk_cc_payout_policy_weekday CHECK (payout_weekday IS NULL OR payout_weekday BETWEEN 1 AND 7),
  CONSTRAINT chk_cc_payout_policy_monthday CHECK (payout_day_of_month IS NULL OR payout_day_of_month BETWEEN 1 AND 28),
  CONSTRAINT chk_cc_payout_policy_hold CHECK (hold_days <= 90),
  CONSTRAINT chk_cc_payout_policy_dispute CHECK (dispute_window_days <= 120),
  CONSTRAINT chk_cc_payout_policy_manual CHECK (manual_approval_required = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_campaign_reconciliation_cases (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  workspace_id BIGINT UNSIGNED NOT NULL,
  fingerprint CHAR(64) NOT NULL,
  issue_type VARCHAR(80) NOT NULL,
  severity ENUM('info','warning','high','critical') NOT NULL DEFAULT 'warning',
  source_type VARCHAR(80) NOT NULL,
  source_public_id VARCHAR(120) NOT NULL,
  campaign_public_id VARCHAR(40) NULL,
  status ENUM('open','acknowledged','resolved','ignored') NOT NULL DEFAULT 'open',
  summary VARCHAR(255) NOT NULL,
  detail_json JSON NULL,
  operator_note VARCHAR(2000) NULL,
  scan_token CHAR(32) NULL,
  assigned_user_id BIGINT UNSIGNED NULL,
  acknowledged_at DATETIME NULL,
  resolved_at DATETIME NULL,
  first_seen_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_reconciliation_public (public_id),
  UNIQUE KEY uq_cc_reconciliation_fingerprint (workspace_id,fingerprint),
  KEY idx_cc_reconciliation_queue (workspace_id,status,severity,last_seen_at,id),
  KEY idx_cc_reconciliation_source (source_type,source_public_id,status,id),
  CONSTRAINT fk_cc_reconciliation_assignee FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
